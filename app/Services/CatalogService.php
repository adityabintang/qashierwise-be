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
        $filteredOut = 0;
        $lastError = null;
        $anyEdgeOk = false;

        foreach ($edges as $edge) {
            $response = Http::withToken($account->access_token)
                ->get("{$this->baseUrl()}/{$businessId}/{$edge}", [
                    'fields' => $fields,
                    'limit' => 100,
                ]);

            if ($response->successful()) {
                $anyEdgeOk = true;
                foreach ($response->json('data', []) as $catalog) {
                    if (isset($seen[$catalog['id']])) {
                        continue;
                    }

                    // Only include catalogs with vertical=commerce — WhatsApp Cart /
                    // Multi-Product Messages and our POST /{catalog_id}/products
                    // schema only work on commerce-vertical catalogs.
                    $vertical = strtolower((string) ($catalog['vertical'] ?? ''));
                    if ($vertical !== 'commerce') {
                        $seen[$catalog['id']] = true;
                        $filteredOut++;
                        continue;
                    }

                    $seen[$catalog['id']] = true;
                    $catalogs[] = $catalog;
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

        if ($anyEdgeOk) {
            Log::info('CatalogService: fetched catalogs', [
                'business_id'  => $businessId,
                'count'        => count($catalogs),
                'filtered_out' => $filteredOut,
            ]);

            return [
                'success'      => true,
                'catalogs'     => $catalogs,
                'business_id'  => $businessId,
                'filtered_out' => $filteredOut,
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
     * Product fields fetched when listing products. Keep aligned with the
     * frontend's product card and edit modal expectations.
     */
    protected const PRODUCT_FIELDS = 'id,retailer_id,name,description,price,sale_price,currency,image_url,additional_image_urls,availability,inventory,category,google_product_category,brand,condition,url,visibility';

    /**
     * Get products from a specific catalog.
     *
     * @return array{success: bool, products?: array, paging?: array, error?: string, error_code?: string}
     */
    public function getCatalogProducts(WhatsAppAccount $account, string $catalogId, int $limit = 30, ?string $after = null): array
    {
        $params = [
            'fields' => self::PRODUCT_FIELDS,
            'limit'  => $limit,
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

        $translated = $this->translateMetaError($response, 'fetch_products');

        Log::error('CatalogService: failed to fetch products', [
            'catalog_id' => $catalogId,
            'status'     => $response->status(),
            'error'      => $translated['raw'],
        ]);

        return [
            'success'    => false,
            'error_code' => 'API_ERROR',
            'error'      => $translated['message'],
        ];
    }

    /**
     * Create a product in a catalog.
     *
     * Required fields: retailer_id, name, price, currency, image_url, url, availability, condition, description
     * Optional F&B-friendly fields: category, google_product_category, brand, sale_price, inventory, additional_image_link
     *
     * @return array{success: bool, id?: string, error?: string, error_code?: string}
     */
    public function createProduct(WhatsAppAccount $account, string $catalogId, array $data): array
    {
        $response = Http::withToken($account->access_token)
            ->asForm()
            ->post("{$this->baseUrl()}/{$catalogId}/products", $data);

        if ($response->successful()) {
            $id = $response->json('id');

            Log::info('CatalogService: product created', [
                'catalog_id'  => $catalogId,
                'product_id'  => $id,
                'retailer_id' => $data['retailer_id'] ?? null,
            ]);

            return ['success' => true, 'id' => $id];
        }

        $translated = $this->translateMetaError($response, 'create_product');

        Log::error('CatalogService: failed to create product', [
            'catalog_id' => $catalogId,
            'status'     => $response->status(),
            'error'      => $translated['raw'],
            'fbtrace_id' => $translated['fbtrace_id'],
            'data_sent'  => $data,
        ]);

        return [
            'success'    => false,
            'error_code' => $translated['code'],
            'error'      => $translated['message'],
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

        $translated = $this->translateMetaError($response, 'update_product');

        Log::error('CatalogService: failed to update product', [
            'product_id' => $productId,
            'status'     => $response->status(),
            'error'      => $translated['raw'],
            'fbtrace_id' => $translated['fbtrace_id'],
            'data_sent'  => $data,
        ]);

        return [
            'success'    => false,
            'error_code' => $translated['code'],
            'error'      => $translated['message'],
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

        $translated = $this->translateMetaError($response, 'delete_product');

        Log::error('CatalogService: failed to delete product', [
            'product_id' => $productId,
            'status'     => $response->status(),
            'error'      => $translated['raw'],
            'fbtrace_id' => $translated['fbtrace_id'],
        ]);

        return [
            'success'    => false,
            'error_code' => $translated['code'],
            'error'      => $translated['message'],
        ];
    }

    /**
     * Translate a Meta Graph API error response into a localized,
     * user-friendly Indonesian message plus a stable error_code for the FE.
     *
     * Returns: ['code' => string, 'message' => string, 'raw' => string, 'fbtrace_id' => ?string]
     */
    protected function translateMetaError($response, string $context): array
    {
        $error = is_object($response) ? ($response->json('error') ?? []) : [];
        if (! is_array($error)) {
            $error = [];
        }

        $raw      = $error['message']         ?? 'Unknown Meta API error';
        $userMsg  = $error['error_user_msg']  ?? null;
        $code     = $error['code']            ?? null;
        $subcode  = $error['error_subcode']   ?? null;
        $fbtrace  = $error['fbtrace_id']      ?? null;

        // Lowercase for case-insensitive matching against Meta's English variants.
        $haystack = strtolower(($userMsg ?? '') . ' ' . $raw);

        // Vertical mismatch — the catalog isn't commerce.
        if (str_contains($haystack, 'catalog vertical')
            || str_contains($haystack, 'vertikal katalog')) {
            return [
                'code'       => 'WRONG_VERTICAL',
                'message'    => 'Katalog ini bukan bertipe commerce sehingga tidak bisa menyimpan produk umum. Buat katalog baru dengan tipe E-Commerce / Produk Online.',
                'raw'        => $raw,
                'fbtrace_id' => $fbtrace,
            ];
        }

        // Duplicate retailer_id.
        if (str_contains($haystack, 'duplicate') && str_contains($haystack, 'retailer_id')
            || ($code === 100 && str_contains($haystack, 'retailer_id'))) {
            return [
                'code'       => 'DUPLICATE_SKU',
                'message'    => 'SKU (retailer_id) ini sudah digunakan di katalog yang sama. Gunakan SKU lain.',
                'raw'        => $raw,
                'fbtrace_id' => $fbtrace,
            ];
        }

        // Image URL not reachable by Meta crawler.
        if (str_contains($haystack, 'image_url') || str_contains($haystack, 'image url')
            || str_contains($haystack, 'invalid image')) {
            return [
                'code'       => 'INVALID_IMAGE',
                'message'    => 'URL gambar tidak bisa diakses oleh Meta. Pastikan gambar diupload ke storage publik (HTTPS) dan ukuran minimal 500×500 px.',
                'raw'        => $raw,
                'fbtrace_id' => $fbtrace,
            ];
        }

        // Permission / token scope problem.
        if (str_contains($haystack, 'permission') || str_contains($haystack, 'oauth')
            || $code === 200 || $code === 190) {
            return [
                'code'       => 'PERMISSION_DENIED',
                'message'    => 'Token akses tidak memiliki izin yang cukup untuk operasi katalog. Coba hubungkan ulang akun WhatsApp Business Anda.',
                'raw'        => $raw,
                'fbtrace_id' => $fbtrace,
            ];
        }

        // Price-related validation.
        if (str_contains($haystack, 'price')) {
            return [
                'code'       => 'INVALID_PRICE',
                'message'    => 'Harga tidak valid. Pastikan harga lebih besar dari 0 dan dalam unit yang sesuai mata uang (untuk IDR/JPY/VND: nominal langsung, untuk USD/SGD/MYR: dalam sen).',
                'raw'        => $raw,
                'fbtrace_id' => $fbtrace,
            ];
        }

        // Generic "Invalid parameter" from Meta — surface user-friendly hint.
        if (str_contains($haystack, 'invalid parameter')) {
            return [
                'code'       => 'INVALID_PARAMETER',
                'message'    => 'Salah satu kolom produk tidak valid. Periksa kembali data yang Anda masukkan (harga, mata uang, gambar, URL).',
                'raw'        => $raw,
                'fbtrace_id' => $fbtrace,
            ];
        }

        // Rate limit.
        if ($code === 4 || $code === 17 || $code === 32 || str_contains($haystack, 'rate limit')) {
            return [
                'code'       => 'RATE_LIMITED',
                'message'    => 'Terlalu banyak permintaan ke Meta. Coba lagi dalam beberapa saat.',
                'raw'        => $raw,
                'fbtrace_id' => $fbtrace,
            ];
        }

        // Fallback — prefer Meta's user-facing message if present, else raw.
        return [
            'code'       => 'API_ERROR',
            'message'    => $userMsg ?: $raw,
            'raw'        => $raw,
            'fbtrace_id' => $fbtrace,
        ];
    }
}
