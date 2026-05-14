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

    protected function getCatalogAccessToken(WhatsAppAccount $account): string
    {
        return $account->catalog_access_token ?? $account->access_token;
    }

    /**
     * Resolve the Meta Business Portfolio ID from a WABA.
     * Returns null when the token lacks business_management scope.
     */
    public function getBusinessId(WhatsAppAccount $account): ?string
    {
        $accessToken = $this->getCatalogAccessToken($account);
        $wabaId = $account->waba_id ?? $account->business_account_id;

        if (! empty($account->catalog_business_id)) {
            return $account->catalog_business_id;
        }

        // Method 1: owner_business_info on WABA (requires whatsapp_business_management)
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl()}/{$wabaId}", [
                'fields' => 'id,name,owner_business_info',
            ]);

        if ($response->successful()) {
            $businessId = $response->json('owner_business_info.id');
            if ($businessId) {
                return $businessId;
            }
        }

        Log::warning('CatalogService: failed to resolve business_id from WABA', [
            'waba_id' => $wabaId,
            'status' => $response->status(),
            'error' => $response->json('error.message'),
        ]);

        // Method 2: /me/businesses edge (classic Business Manager)
        $meResponse = Http::withToken($accessToken)
            ->get("{$this->baseUrl()}/me/businesses", [
                'fields' => 'id,name',
                'limit' => 10,
            ]);

        if ($meResponse->successful()) {
            $businesses = $meResponse->json('data', []);
            if (! empty($businesses)) {
                $businessId = $businesses[0]['id'];
                Log::info('CatalogService: resolved business_id via /me/businesses', [
                    'business_id' => $businessId,
                ]);

                return $businessId;
            }
        }

        // Method 3: /me?fields=business_users (Personal Business Portfolio structure)
        $bizUsersResponse = Http::withToken($accessToken)
            ->get("{$this->baseUrl()}/me", [
                'fields' => 'business_users{business{id,name}}',
            ]);

        if ($bizUsersResponse->successful()) {
            $businessUsers = $bizUsersResponse->json('business_users.data', []);
            foreach ($businessUsers as $bu) {
                $businessId = $bu['business']['id'] ?? null;
                if ($businessId) {
                    Log::info('CatalogService: resolved business_id via business_users', [
                        'business_id' => $businessId,
                        'business_name' => $bu['business']['name'] ?? null,
                    ]);

                    return $businessId;
                }
            }
        }

        // Method 4: /me?fields=businesses (field variant, different from edge)
        $bizFieldResponse = Http::withToken($accessToken)
            ->get("{$this->baseUrl()}/me", [
                'fields' => 'businesses{id,name}',
            ]);

        if ($bizFieldResponse->successful()) {
            $businesses = $bizFieldResponse->json('businesses.data', []);
            if (! empty($businesses)) {
                $businessId = $businesses[0]['id'];
                Log::info('CatalogService: resolved business_id via businesses field', [
                    'business_id' => $businessId,
                ]);

                return $businessId;
            }
        }

        // Method 5: /me?fields=business — works for System User tokens
        $systemUserBizResponse = Http::withToken($accessToken)
            ->get("{$this->baseUrl()}/me", [
                'fields' => 'id,name,business',
            ]);

        if ($systemUserBizResponse->successful()) {
            $businessId = $systemUserBizResponse->json('business.id');
            if ($businessId) {
                Log::info('CatalogService: resolved business_id via system user business field', [
                    'business_id' => $businessId,
                    'business_name' => $systemUserBizResponse->json('business.name'),
                ]);

                return $businessId;
            }
        }

        Log::warning('CatalogService: all business_id discovery methods failed', [
            'me_businesses_body' => $meResponse->json(),
            'business_users_body' => $bizUsersResponse->json(),
            'businesses_field_body' => $bizFieldResponse->json(),
            'system_user_business_body' => $systemUserBizResponse->json(),
        ]);

        return null;
    }

    /**
     * List all product catalogs owned by the business linked to this WhatsApp account.
     *
     * @return array{success: bool, catalogs?: array, error?: string, error_code?: string}
     */
    public function getCatalogs(WhatsAppAccount $account, ?string $overrideBusinessId = null): array
    {
        $accessToken = $this->getCatalogAccessToken($account);
        $businessId = $overrideBusinessId ?: $this->getBusinessId($account);

        if (! $businessId) {
            return [
                'success' => false,
                'error_code' => 'BUSINESS_ID_NOT_FOUND',
                'error' => 'Unable to resolve Meta Business ID. Ensure your catalog is connected using the Catalog OAuth configuration which includes catalog_management permission.',
            ];
        }

        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl()}/{$businessId}/owned_product_catalogs", [
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
        $accessToken = $this->getCatalogAccessToken($account);
        $params = [
            'fields' => 'id,retailer_id,name,description,price,currency,image_url,availability,category,brand,condition,url',
            'limit' => $limit,
        ];

        if ($after) {
            $params['after'] = $after;
        }

        $response = Http::withToken($accessToken)
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

    /**
     * Create a product in a catalog.
     *
     * Required fields: retailer_id, name, price, currency, image_url, url, availability
     *
     * @return array{success: bool, id?: string, error?: string, error_code?: string}
     */
    public function createProduct(WhatsAppAccount $account, string $catalogId, array $data): array
    {
        $response = Http::withToken($this->getCatalogAccessToken($account))
            ->post("{$this->baseUrl()}/{$catalogId}/products", $data);

        if ($response->successful()) {
            $id = $response->json('id');

            Log::info('CatalogService: product created', [
                'catalog_id' => $catalogId,
                'product_id' => $id,
                'retailer_id' => $data['retailer_id'] ?? null,
            ]);

            return ['success' => true, 'id' => $id];
        }

        $error = $response->json('error', []);
        $errorMessage = $error['message'] ?? 'Failed to create product';

        Log::error('CatalogService: failed to create product', [
            'catalog_id' => $catalogId,
            'error' => $errorMessage,
        ]);

        return [
            'success' => false,
            'error_code' => 'API_ERROR',
            'error' => $errorMessage,
        ];
    }

    /**
     * Update a product item.
     *
     * Update is done on the product item node directly, not on the /products edge.
     *
     * @return array{success: bool, error?: string, error_code?: string}
     */
    public function updateProduct(WhatsAppAccount $account, string $productId, array $data): array
    {
        $response = Http::withToken($this->getCatalogAccessToken($account))
            ->post("{$this->baseUrl()}/{$productId}", $data);

        if ($response->successful()) {
            Log::info('CatalogService: product updated', ['product_id' => $productId]);

            return ['success' => true];
        }

        $error = $response->json('error', []);
        $errorMessage = $error['message'] ?? 'Failed to update product';

        Log::error('CatalogService: failed to update product', [
            'product_id' => $productId,
            'error' => $errorMessage,
        ]);

        return [
            'success' => false,
            'error_code' => 'API_ERROR',
            'error' => $errorMessage,
        ];
    }

    /**
     * Delete a product item.
     *
     * @return array{success: bool, error?: string, error_code?: string}
     */
    public function deleteProduct(WhatsAppAccount $account, string $productId): array
    {
        $response = Http::withToken($this->getCatalogAccessToken($account))
            ->delete("{$this->baseUrl()}/{$productId}");

        if ($response->successful()) {
            Log::info('CatalogService: product deleted', ['product_id' => $productId]);

            return ['success' => true];
        }

        $error = $response->json('error', []);
        $errorMessage = $error['message'] ?? 'Failed to delete product';

        Log::error('CatalogService: failed to delete product', [
            'product_id' => $productId,
            'error' => $errorMessage,
        ]);

        return [
            'success' => false,
            'error_code' => 'API_ERROR',
            'error' => $errorMessage,
        ];
    }
}
