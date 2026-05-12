<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CatalogService
{
    protected string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('whatsapp.api_version', 'v22.0');
    }

    protected function baseUrl(): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * Resolve the Meta Business Portfolio ID from a WABA.
     * Returns null when the token lacks business_management scope.
     */
    public function getBusinessId(WhatsAppAccount $account): ?string
    {
        $wabaId = $account->waba_id ?? $account->business_account_id;

        $response = Http::withToken($account->access_token)
            ->get("{$this->baseUrl()}/{$wabaId}", [
                'fields' => 'id,name,owner_business_info',
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['owner_business_info']['id'] ?? null;
        }

        Log::warning('CatalogService: failed to resolve business_id from WABA', [
            'waba_id' => $wabaId,
            'status' => $response->status(),
            'error' => $response->json('error.message'),
        ]);

        return null;
    }

    /**
     * List all product catalogs owned by the business linked to this WhatsApp account.
     *
     * @return array{success: bool, catalogs?: array, error?: string, error_code?: string}
     */
    public function getCatalogs(WhatsAppAccount $account): array
    {
        $businessId = $this->getBusinessId($account);

        if (! $businessId) {
            return [
                'success' => false,
                'error_code' => 'BUSINESS_ID_NOT_FOUND',
                'error' => 'Unable to resolve Meta Business ID. Ensure your WhatsApp account was connected using the Catalog OAuth configuration (Config ID: 3015067632023945) which includes catalog_management permission.',
            ];
        }

        $response = Http::withToken($account->access_token)
            ->get("{$this->baseUrl()}/{$businessId}/product_catalogs", [
                'fields' => 'id,name,product_count,vertical',
            ]);

        if ($response->successful()) {
            $catalogs = $response->json('data', []);

            Log::info('CatalogService: fetched catalogs', [
                'business_id' => $businessId,
                'count' => count($catalogs),
            ]);

            return [
                'success' => true,
                'catalogs' => $catalogs,
                'business_id' => $businessId,
            ];
        }

        $error = $response->json('error', []);
        $errorMessage = $error['message'] ?? 'Failed to fetch catalogs';

        // Detect missing catalog_management permission
        if (($error['code'] ?? 0) === 200 || str_contains($errorMessage, 'catalog')) {
            return [
                'success' => false,
                'error_code' => 'PERMISSION_DENIED',
                'error' => 'The connected WhatsApp account does not have catalog_management permission. Please reconnect using the Catalog OAuth configuration.',
            ];
        }

        Log::error('CatalogService: failed to fetch catalogs', [
            'business_id' => $businessId,
            'error' => $errorMessage,
        ]);

        return [
            'success' => false,
            'error_code' => 'API_ERROR',
            'error' => $errorMessage,
        ];
    }

    /**
     * Get products from a specific catalog.
     *
     * @return array{success: bool, products?: array, paging?: array, error?: string, error_code?: string}
     */
    public function getCatalogProducts(WhatsAppAccount $account, string $catalogId, int $limit = 30, ?string $after = null): array
    {
        $params = [
            'fields' => 'id,retailer_id,name,description,price,currency,image_url,availability,category,brand,condition,url',
            'limit' => $limit,
        ];

        if ($after) {
            $params['after'] = $after;
        }

        $response = Http::withToken($account->access_token)
            ->get("{$this->baseUrl()}/{$catalogId}/products", $params);

        if ($response->successful()) {
            $data = $response->json();

            Log::info('CatalogService: fetched products', [
                'catalog_id' => $catalogId,
                'count' => count($data['data'] ?? []),
            ]);

            return [
                'success' => true,
                'products' => $data['data'] ?? [],
                'paging' => $data['paging'] ?? null,
            ];
        }

        $error = $response->json('error', []);
        $errorMessage = $error['message'] ?? 'Failed to fetch products';

        Log::error('CatalogService: failed to fetch products', [
            'catalog_id' => $catalogId,
            'error' => $errorMessage,
        ]);

        return [
            'success' => false,
            'error_code' => 'API_ERROR',
            'error' => $errorMessage,
        ];
    }
}
