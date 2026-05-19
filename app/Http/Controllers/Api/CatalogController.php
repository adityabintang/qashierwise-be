<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WhatsAppNotConnectedException;
use App\Http\Controllers\Controller;
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

            return response()->json([
                'success' => true,
                'data' => [
                    'catalog_id' => $catalogId,
                    'products' => $result['products'],
                    'paging' => $result['paging'],
                    'total' => count($result['products']),
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
        'availability', 'inventory',
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
        $request->validate($this->productRules('update'));

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
     * DELETE /api/whatsapp/catalog/products/{productId}
     * Delete a product item.
     */
    public function deleteProduct(string $productId): JsonResponse
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
