<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\DB;
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
        $accessToken = $account->access_token;
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
                DB::table('whatsapp_accounts')->where('id', $account->id)->update(['catalog_business_id' => $businessId]);
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
                DB::table('whatsapp_accounts')->where('id', $account->id)->update(['catalog_business_id' => $businessId]);

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
                    DB::table('whatsapp_accounts')->where('id', $account->id)->update(['catalog_business_id' => $businessId]);

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
                DB::table('whatsapp_accounts')->where('id', $account->id)->update(['catalog_business_id' => $businessId]);

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
                DB::table('whatsapp_accounts')->where('id', $account->id)->update(['catalog_business_id' => $businessId]);

                return $businessId;
            }
        }

        // Method 6: owned_product_catalogs directly on WABA (last resort)
        // Some setups expose the business through the WABA's product catalog link
        $wabaResponse = Http::withToken($accessToken)
            ->get("{$this->baseUrl()}/{$wabaId}", [
                'fields' => 'id,name,business',
            ]);

        if ($wabaResponse->successful()) {
            $businessId = $wabaResponse->json('business.id');
            if ($businessId) {
                Log::info('CatalogService: resolved business_id via WABA business field', [
                    'business_id' => $businessId,
                    'business_name' => $wabaResponse->json('business.name'),
                ]);
                DB::table('whatsapp_accounts')->where('id', $account->id)->update(['catalog_business_id' => $businessId]);

                return $businessId;
            }
        }

        Log::warning('CatalogService: all business_id discovery methods failed', [
            'waba_id' => $wabaId,
            'method1_status' => $response->status(),
            'method1_error' => $response->json('error.message'),
            'method2_status' => $meResponse->status(),
            'method2_error' => $meResponse->json('error.message'),
            'method5_response' => $systemUserBizResponse->json(),
            'method6_response' => $wabaResponse->json(),
        ]);

        return null;
    }

    /**
     * Create a new product catalog under the business linked to this account.
     *
     * @return array{success: bool, id?: string, name?: string, error?: string, error_code?: string}
     */
    public function createCatalog(WhatsAppAccount $account, string $name, ?string $vertical = null): array
    {
        $businessId = $this->getBusinessId($account);

        if (! $businessId) {
            return [
                'success' => false,
                'error_code' => 'BUSINESS_ID_NOT_FOUND',
                'error' => 'Unable to resolve Meta Business ID.',
            ];
        }

        $payload = ['name' => $name];
        if ($vertical) {
            $payload['vertical'] = $vertical;
        }

        $response = Http::withToken($account->access_token)
            ->post("{$this->baseUrl()}/{$businessId}/owned_product_catalogs", $payload);

        if ($response->successful()) {
            $id = $response->json('id');
            Log::info('CatalogService: catalog created', ['business_id' => $businessId, 'catalog_id' => $id, 'name' => $name]);

            return ['success' => true, 'id' => $id, 'name' => $name];
        }

        $error = $response->json('error', []);
        $errorMessage = $error['message'] ?? 'Failed to create catalog';

        Log::error('CatalogService: failed to create catalog', [
            'business_id' => $businessId,
            'status' => $response->status(),
            'error_code' => $error['code'] ?? null,
            'error_subcode' => $error['error_subcode'] ?? null,
            'error_type' => $error['type'] ?? null,
            'error' => $errorMessage,
            'fbtrace_id' => $error['fbtrace_id'] ?? null,
        ]);

        return ['success' => false, 'error_code' => 'API_ERROR', 'error' => $errorMessage];
    }

    /**
     * List all product catalogs accessible by this account.
     *
     * Merges owned, client (shared-in), and shared (shared-out) catalogs so that
     * catalogs the user did not explicitly select during Embedded Signup but exist
     * under the business are still visible.
     *
     * @return array{success: bool, catalogs?: array, error?: string, error_code?: string}
     */
    public function getCatalogs(WhatsAppAccount $account, ?string $overrideBusinessId = null): array
    {
        $businessId = $overrideBusinessId ?: $this->getBusinessId($account);

        if (! $businessId) {
            return [
                'success' => false,
                'error_code' => 'BUSINESS_ID_NOT_FOUND',
                'error' => 'Unable to resolve Meta Business ID. Pastikan akun WhatsApp Business Anda sudah terhubung dengan benar.',
            ];
        }

        $fields = 'id,name,product_count,vertical';
        $edges = ['owned_product_catalogs', 'client_product_catalogs', 'shared_product_catalogs'];

        $seen = [];
        $catalogs = [];
        $lastError = null;

        foreach ($edges as $edge) {
            $response = Http::withToken($account->access_token)
                ->get("{$this->baseUrl()}/{$businessId}/{$edge}", [
                    'fields' => $fields,
                    'limit' => 100,
                ]);

            if ($response->successful()) {
                foreach ($response->json('data', []) as $catalog) {
                    if (! isset($seen[$catalog['id']])) {
                        $seen[$catalog['id']] = true;
                        $catalogs[] = $catalog;
                    }
                }
            } else {
                $error = $response->json('error', []);
                $lastError = $error['message'] ?? null;
                Log::debug("CatalogService: {$edge} not accessible", [
                    'business_id' => $businessId,
                    'error' => $lastError,
                ]);
            }
        }

        if (! empty($catalogs)) {
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

        // All edges failed — surface the error
        if ($lastError && (str_contains($lastError, 'permission') || str_contains($lastError, 'catalog'))) {
            return [
                'success' => false,
                'error_code' => 'PERMISSION_PENDING_REVIEW',
                'error' => 'Fitur katalog Meta masih dalam proses peninjauan oleh Meta. Saat ini akses katalog hanya tersedia untuk akun penguji yang terdaftar di aplikasi developer kami.',
            ];
        }

        Log::error('CatalogService: failed to fetch catalogs from all edges', [
            'business_id' => $businessId,
            'last_error' => $lastError,
        ]);

        return [
            'success' => false,
            'error_code' => 'API_ERROR',
            'error' => $lastError ?? 'Failed to fetch catalogs',
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

    /**
     * Create a product in a catalog.
     *
     * Required fields: retailer_id, name, price, currency, image_url, url, availability
     *
     * @return array{success: bool, id?: string, error?: string, error_code?: string}
     */
    public function createProduct(WhatsAppAccount $account, string $catalogId, array $data): array
    {
        $response = Http::withToken($account->access_token)
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
     * @return array{success: bool, error?: string, error_code?: string}
     */
    public function updateProduct(WhatsAppAccount $account, string $productId, array $data): array
    {
        $response = Http::withToken($account->access_token)
            ->asForm()
            ->post("{$this->baseUrl()}/{$productId}", $data);

        if ($response->successful()) {
            Log::info('CatalogService: product updated', ['product_id' => $productId]);

            return ['success' => true];
        }

        $error = $response->json('error', []);
        $errorMessage = $error['message'] ?? 'Failed to update product';

        Log::error('CatalogService: failed to update product', [
            'product_id'    => $productId,
            'error_code'    => $error['code'] ?? null,
            'error_type'    => $error['type'] ?? null,
            'error_subcode' => $error['error_subcode'] ?? null,
            'error'         => $errorMessage,
            'data_sent'     => $data,
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
        $response = Http::withToken($account->access_token)
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
