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
                    'catalogs' => $result['catalogs'],
                    'business_id' => $result['business_id'],
                    'total' => count($result['catalogs']),
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
     * POST /api/whatsapp/catalog/{catalogId}/products
     * Create a new product in a catalog.
     */
    public function createProduct(Request $request, string $catalogId): JsonResponse
    {
        $request->validate([
            'retailer_id' => 'required|string',
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'currency' => 'required|string|size:3',
            'image_url' => 'required|url',
            'url' => 'required|url',
            'availability' => 'sometimes|string|in:in stock,out of stock,preorder,available for order,discontinued,pending',
            'description' => 'sometimes|string|max:5000',
            'brand' => 'sometimes|string',
            'condition' => 'sometimes|string|in:new,refurbished,used',
            'category' => 'sometimes|string',
        ]);

        try {
            $account = $this->getAccount();

            $data = array_filter($request->only([
                'retailer_id', 'name', 'price', 'currency', 'image_url', 'url',
                'availability', 'description', 'brand', 'condition', 'category',
            ]), fn ($v) => $v !== null);

            $data['availability'] = $data['availability'] ?? 'in stock';

            $result = $this->catalogService->createProduct($account, $catalogId, $data);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'error_code' => $result['error_code'],
                    'message' => $result['error'],
                ], 422);
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
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|integer|min:0',
            'currency' => 'sometimes|string|size:3',
            'image_url' => 'sometimes|url',
            'url' => 'sometimes|url',
            'availability' => 'sometimes|string|in:in stock,out of stock,preorder,available for order,discontinued,pending',
            'description' => 'sometimes|string|max:5000',
            'brand' => 'sometimes|string',
            'condition' => 'sometimes|string|in:new,refurbished,used',
            'category' => 'sometimes|string',
        ]);

        try {
            $account = $this->getAccount();

            $data = array_filter($request->only([
                'name', 'price', 'currency', 'image_url', 'url',
                'availability', 'description', 'brand', 'condition', 'category',
            ]), fn ($v) => $v !== null);

            $result = $this->catalogService->updateProduct($account, $productId, $data);

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
