<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WhatsAppNotConnectedException;
use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use App\Services\WhatsAppAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService,
        protected WhatsAppAccountService $whatsAppAccountService,
    ) {}

    /**
     * GET /api/whatsapp/catalog/catalogs
     * List all Meta product catalogs for the authenticated user's business.
     */
    public function getCatalogs(): JsonResponse
    {
        try {
            $account = $this->getAccount();

            $result = $this->catalogService->getCatalogs($account);

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
