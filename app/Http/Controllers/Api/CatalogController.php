<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WhatsAppNotConnectedException;
use App\Http\Controllers\Controller;
use App\Models\CatalogProduct;
use App\Services\CatalogService;
use App\Services\WhatsAppAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CatalogController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService,
        protected WhatsAppAccountService $whatsAppAccountService,
    ) {}

    /**
     * POST /api/whatsapp/catalog/catalogs
     * Create a new Meta product catalog.
     */
    public function createCatalog(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'vertical' => 'sometimes|string|in:commerce,destinations,flights,home_listings,hotels,vehicles',
        ]);

        try {
            $account = $this->getAccount();

            $result = $this->catalogService->createCatalog(
                $account,
                $request->input('name'),
                $request->input('vertical'),
            );

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'error_code' => $result['error_code'],
                    'message' => $result['error'],
                ], 422);
            }

            try {
                $this->catalogService->linkCatalogToWaba($account, $result['id']);
            } catch (\Throwable) {}

            return response()->json([
                'success' => true,
                'data' => ['id' => $result['id'], 'name' => $result['name']],
            ], 201);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * GET /api/whatsapp/catalog/catalogs
     * List all Meta product catalogs for the authenticated user's business.
     */
    public function getCatalogs(Request $request): JsonResponse
    {
        try {
            $account = $this->getAccount();

            $result = $this->catalogService->getCatalogs($account, $request->query('business_id'));

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'error_code' => $result['error_code'],
                    'message' => $result['error'],
                ], $result['error_code'] === 'PERMISSION_DENIED' ? 403 : 422);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'catalogs'     => $result['catalogs'],
                    'business_id'  => $result['business_id'],
                    'total'        => count($result['catalogs']),
                    'filtered_out' => $result['filtered_out'] ?? 0,
                ],
            ]);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * GET /api/whatsapp/catalog/{catalogId}/products
     * List products from a specific Meta catalog.
     */
    public function getCatalogProducts(Request $request, string $catalogId): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'after' => 'nullable|string',
        ]);

        try {
            $account = $this->getAccount();

            $result = $this->catalogService->getCatalogProducts(
                $account,
                $catalogId,
                (int) $request->input('limit', 30),
                $request->input('after'),
            );

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'error_code' => $result['error_code'],
                    'message' => $result['error'],
                ], 422);
            }

            $userId = auth()->user()->getEffectiveUserId();
            $products = $result['products'];

            if (! empty($products)) {
                $retailerIds = array_column($products, 'retailer_id');
                $dbRows = CatalogProduct::where('user_id', $userId)
                    ->where('catalog_id', $catalogId)
                    ->whereIn('retailer_id', $retailerIds)
                    ->get()
                    ->keyBy('retailer_id');

                $nameUpdates = [];   // [retailer_id => name] for stub rows missing a name
                $products = array_map(function (array $p) use ($dbRows, &$nameUpdates) {
                    $rid = $p['retailer_id'] ?? null;
                    // Strip Meta's inventory — stock is owned by our DB only.
                    $metaInventory = $p['inventory'] ?? null;
                    unset($p['inventory']);

                    if ($rid && isset($dbRows[$rid])) {
                        $row = $dbRows[$rid];
                        $p['stock_quantity'] = $row->stock_quantity;
                        $p['is_available']   = $row->is_available;
                        if (! $row->is_available) {
                            $p['availability'] = 'out of stock';
                        }
                        // Lazy-sync: backfill name if the row was created as a stub.
                        if ($row->name === null && ! empty($p['name'])) {
                            $nameUpdates[$rid] = $p['name'];
                        }
                    } else {
                        // No local row yet — fall back to what Meta reported so
                        // the UI at least shows something for legacy products.
                        $p['stock_quantity'] = $metaInventory;
                        $p['is_available']   = ($p['availability'] ?? '') !== 'out of stock';
                    }
                    return $p;
                }, $products);

                // Persist any name backfills in a single query per row.
                foreach ($nameUpdates as $rid => $name) {
                    CatalogProduct::where('user_id', $userId)
                        ->where('catalog_id', $catalogId)
                        ->where('retailer_id', $rid)
                        ->update(['name' => $name]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'catalog_id' => $catalogId,
                    'products' => $products,
                    'paging' => $result['paging'],
                    'total' => count($products),
                ],
            ]);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * Fields accepted by Meta's products endpoint. Single source of truth so
     * create/update stay in sync.
     */
    protected const PRODUCT_FIELDS = [
        'retailer_id', 'name', 'description',
        'price', 'sale_price', 'currency',
        'image_url', 'additional_image_link', 'url',
        'availability',
        'category', 'google_product_category',
        'brand', 'condition',
    ];

    protected const AVAILABILITY_VALUES = 'in stock,out of stock,preorder,available for order,discontinued,pending';
    protected const CONDITION_VALUES = 'new,refurbished,used';

    /**
     * Common validation rules for create/update. `$mode` is 'create' or 'update'
     * — required-vs-optional rules switch accordingly.
     */
    protected function productRules(string $mode): array
    {
        $req = $mode === 'create' ? 'required' : 'sometimes';
        $opt = 'sometimes';

        return [
            // Required only on create — retailer_id is immutable after creation.
            'retailer_id'             => $mode === 'create' ? 'required|string|max:100' : 'prohibited',
            'name'                    => "{$req}|string|max:150",
            'description'             => "{$req}|string|min:3|max:5000",
            // Meta rejects price=0; minor unit semantics handled client-side.
            'price'                   => "{$req}|integer|min:1",
            'sale_price'              => "{$opt}|integer|min:1|lt:price",
            'currency'                => "{$req}|string|size:3|regex:/^[A-Z]{3}$/",
            // Meta crawls image_url + url; both must be HTTPS publicly reachable.
            'image_url'               => "{$req}|url|starts_with:https://",
            'additional_image_link'   => "{$opt}|array|max:9",
            'additional_image_link.*' => 'url|starts_with:https://',
            'url'                     => "{$req}|url|starts_with:https://",
            'availability'            => $opt . '|string|in:' . self::AVAILABILITY_VALUES,
            'condition'               => $opt . '|string|in:' . self::CONDITION_VALUES,
            'inventory'               => "{$opt}|integer|min:0",
            'category'                => "{$opt}|string|max:250",
            'google_product_category' => "{$opt}|string|max:250",
            'brand'                   => "{$opt}|string|max:100",
        ];
    }

    /**
     * Extract validated, non-null fields to forward to Meta. Normalizes:
     * - currency → uppercase
     * - additional_image_link → CSV (Meta form-encoded format)
     * - availability/condition defaults applied here, not in service
     */
    protected function preparePayload(Request $request, string $mode): array
    {
        $data = array_filter(
            $request->only(self::PRODUCT_FIELDS),
            fn ($v) => $v !== null && $v !== ''
        );

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        if (isset($data['additional_image_link']) && is_array($data['additional_image_link'])) {
            // Meta accepts a comma-separated string in form-encoded payloads.
            $data['additional_image_link'] = implode(',', $data['additional_image_link']);
        }

        if ($mode === 'create') {
            $data['availability'] = $data['availability'] ?? 'in stock';
            $data['condition']    = $data['condition']    ?? 'new';
        }

        return $data;
    }

    /**
     * POST /api/whatsapp/catalog/{catalogId}/products
     * Create a new product in a catalog.
     */
    public function createProduct(Request $request, string $catalogId): JsonResponse
    {
        $request->validate($this->productRules('create'));

        try {
            $account = $this->getAccount();
            $data = $this->preparePayload($request, 'create');

            $result = $this->catalogService->createProduct($account, $catalogId, $data);

            if (! $result['success']) {
                return response()->json([
                    'success'    => false,
                    'error_code' => $result['error_code'],
                    'message'    => $result['error'],
                ], $this->statusFromErrorCode($result['error_code']));
            }

            try {
                $this->catalogService->linkCatalogToWaba($account, $catalogId);
            } catch (\Throwable) {}

            $userId       = auth()->user()->getEffectiveUserId();
            $availability = $data['availability'] ?? 'in stock';
            CatalogProduct::updateOrCreate(
                ['user_id' => $userId, 'catalog_id' => $catalogId, 'retailer_id' => $data['retailer_id']],
                [
                    'meta_product_id' => $result['id'],
                    'name'            => $data['name'] ?? '',
                    'price'           => isset($data['price']) ? $data['price'] / 100 : 0,
                    'currency'        => $data['currency'] ?? 'IDR',
                    'category'        => $data['category'] ?? null,
                    'availability'    => $availability,
                    'is_available'    => CatalogProduct::isAvailableFromStatus($availability),
                ]
            );

            $this->catalogService->clearProductsCache($catalogId);

            return response()->json([
                'success' => true,
                'data' => ['id' => $result['id']],
            ], 201);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * PUT /api/whatsapp/catalog/products/{productId}
     * Update an existing product item.
     */
    public function updateProduct(Request $request, string $productId): JsonResponse
    {
        $rules = $this->productRules('update');
        $rules['catalog_id'] = 'sometimes|nullable|string';
        $request->validate($rules);

        try {
            $account = $this->getAccount();
            $data = $this->preparePayload($request, 'update');

            if (empty($data)) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'EMPTY_UPDATE',
                    'message'    => 'Tidak ada perubahan yang dikirim.',
                ], 422);
            }

            $result = $this->catalogService->updateProduct($account, $productId, $data);

            if (! $result['success']) {
                return response()->json([
                    'success'    => false,
                    'error_code' => $result['error_code'],
                    'message'    => $result['error'],
                ], $this->statusFromErrorCode($result['error_code']));
            }

            $catalogId = $request->input('catalog_id');

            if ($catalogId) {
                try {
                    $this->catalogService->linkCatalogToWaba($account, $catalogId);
                } catch (\Throwable) {}

                $userId = auth()->user()->getEffectiveUserId();
                $dbUpdates = [];
                if (isset($data['name']))     $dbUpdates['name']     = $data['name'];
                if (isset($data['category'])) $dbUpdates['category'] = $data['category'];
                if (isset($data['currency'])) $dbUpdates['currency'] = $data['currency'];
                if (isset($data['price']))    $dbUpdates['price']    = $data['price'] / 100;
                if (isset($data['availability'])) {
                    $dbUpdates['availability'] = $data['availability'];
                    $dbUpdates['is_available'] = CatalogProduct::isAvailableFromStatus($data['availability']);
                }

                if (! empty($dbUpdates)) {
                    CatalogProduct::where('user_id', $userId)
                        ->where('catalog_id', $catalogId)
                        ->where('meta_product_id', $productId)
                        ->update($dbUpdates);
                }

                $this->catalogService->clearProductsCache($catalogId);
            }

            return response()->json(['success' => true]);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    protected function statusFromErrorCode(string $code): int
    {
        return match ($code) {
            'PERMISSION_DENIED'                  => 403,
            'WRONG_VERTICAL', 'DUPLICATE_SKU',
            'INVALID_IMAGE', 'INVALID_PRICE',
            'INVALID_PARAMETER', 'EMPTY_UPDATE'  => 422,
            'RATE_LIMITED'                       => 429,
            default                              => 422,
        };
    }

    /**
     * DELETE /api/whatsapp/catalog/products/{productId}?catalog_id={catalogId}
     * Delete a product item.
     */
    public function deleteProduct(Request $request, string $productId): JsonResponse
    {
        try {
            $account = $this->getAccount();

            $result = $this->catalogService->deleteProduct($account, $productId);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'error_code' => $result['error_code'],
                    'message' => $result['error'],
                ], 422);
            }

            if ($catalogId = $request->query('catalog_id')) {
                try {
                    $this->catalogService->linkCatalogToWaba($account, $catalogId);
                } catch (\Throwable) {}

                $userId = auth()->user()->getEffectiveUserId();
                CatalogProduct::where('user_id', $userId)
                    ->where('catalog_id', $catalogId)
                    ->where('meta_product_id', $productId)
                    ->delete();

                $this->catalogService->clearProductsCache($catalogId);
            }

            return response()->json(['success' => true]);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * PUT /api/whatsapp/catalog/{catalogId}/products/{retailerId}/stock
     * Update stock_quantity and availability for a product in our local DB.
     * Does NOT call Meta API — stock is self-hosted.
     */
    public function updateStock(Request $request, string $catalogId, string $retailerId): JsonResponse
    {
        $request->validate([
            'stock_quantity' => 'required|integer|min:0',
            'is_available'   => 'sometimes|boolean',
        ]);

        try {
            $userId = auth()->user()->getEffectiveUserId();

            if (! $userId) {
                throw new WhatsAppNotConnectedException('Authentication required.');
            }

            $account  = $this->getAccount();
            $stockQty = (int) $request->input('stock_quantity');

            // Load existing row so we know the current availability before updating.
            $existing = CatalogProduct::where('user_id', $userId)
                ->where('catalog_id', $catalogId)
                ->where('retailer_id', $retailerId)
                ->first();

            $currentAvailability = $existing?->availability ?? 'in stock';
            $newAvailability     = $currentAvailability;
            $newIsAvailable      = $request->has('is_available')
                ? $request->boolean('is_available')
                : ($existing?->is_available ?? true);

            // Sync availability ↔ stock, but ONLY for in stock / out of stock.
            // preorder and discontinued are independent of stock count.
            if ($stockQty === 0 && $currentAvailability === 'in stock') {
                $newAvailability = 'out of stock';
                $newIsAvailable  = false;
                // Push to Meta API so WhatsApp reflects the change immediately.
                $metaProductId = $existing?->meta_product_id;
                if ($metaProductId) {
                    try {
                        $this->catalogService->updateProduct($account, $metaProductId, [
                            'availability' => 'out of stock',
                        ]);
                        $this->catalogService->clearProductsCache($catalogId);
                    } catch (\Throwable) {}
                }
            } elseif ($stockQty > 0 && $currentAvailability === 'out of stock') {
                $newAvailability = 'in stock';
                $newIsAvailable  = true;
                $metaProductId   = $existing?->meta_product_id;
                if ($metaProductId) {
                    try {
                        $this->catalogService->updateProduct($account, $metaProductId, [
                            'availability' => 'in stock',
                        ]);
                        $this->catalogService->clearProductsCache($catalogId);
                    } catch (\Throwable) {}
                }
            }

            $row = CatalogProduct::updateOrCreate(
                ['user_id' => $userId, 'catalog_id' => $catalogId, 'retailer_id' => $retailerId],
                [
                    'stock_quantity' => $stockQty,
                    'is_available'   => $newIsAvailable,
                    'availability'   => $newAvailability,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'retailer_id'    => $retailerId,
                    'stock_quantity' => $row->stock_quantity,
                    'is_available'   => $row->is_available,
                    'availability'   => $row->availability,
                ],
            ]);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * POST /api/whatsapp/catalog/{catalogId}/upload-image
     * Store a product image in public storage and return a publicly accessible URL.
     */
    public function uploadImage(Request $request, string $catalogId): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|image|max:5120|mimes:jpeg,jpg,png,gif,webp',
        ]);

        try {
            $this->getAccount();

            $path = $request->file('image')->store('catalog-images', 'r2');
            $url = Storage::disk('r2')->url($path);

            Log::info('CatalogController: image uploaded', [
                'catalog_id' => $catalogId,
                'path' => $path,
                'url' => $url,
            ]);

            return response()->json([
                'success' => true,
                'data' => ['image_url' => $url],
            ]);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Throwable $e) {
            Log::error('CatalogController: image upload failed', [
                'catalog_id' => $catalogId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error_code' => 'UPLOAD_FAILED',
                'message' => 'Gagal mengupload gambar: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function getAccount()
    {
        $userId = auth()->user()->getEffectiveUserId();

        if (! $userId) {
            throw new WhatsAppNotConnectedException('Authentication required.');
        }

        $account = $this->whatsAppAccountService->getActiveAccount($userId);

        if (! $account) {
            throw new WhatsAppNotConnectedException('No connected WhatsApp account found.');
        }

        return $account;
    }
}
