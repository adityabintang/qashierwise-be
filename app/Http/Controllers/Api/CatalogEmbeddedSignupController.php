<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WhatsAppNotConnectedException;
use App\Http\Controllers\Controller;
use App\Services\CatalogEmbeddedSignupService;
use App\Services\WhatsAppAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CatalogEmbeddedSignupController extends Controller
{
    public function __construct(
        protected CatalogEmbeddedSignupService $catalogEmbeddedSignupService,
        protected WhatsAppAccountService $whatsAppAccountService
    ) {}

    /**
     * POST /api/whatsapp/catalog/embedded-signup/callback
     */
    public function handleCallback(Request $request): JsonResponse
    {
        if (! $this->catalogEmbeddedSignupService->isEnabled()) {
            return response()->json([
                'success' => false,
                'error_code' => 'CATALOG_EMBEDDED_SIGNUP_DISABLED',
                'message' => 'Catalog Embedded Signup is not configured. Please add CATALOG_CONFIG_ID to your .env file.',
                'data' => null,
            ], 200);
        }

        $request->validate([
            'code' => 'required|string',
            'business_id' => 'nullable|string',
        ]);

        $userId = auth()->user()->getEffectiveUserId();
        $account = $this->whatsAppAccountService->getActiveAccount($userId);

        if (! $account) {
            throw new WhatsAppNotConnectedException('Please connect your WhatsApp account before linking a catalog.');
        }

        $result = $this->catalogEmbeddedSignupService->processSignup(
            $account,
            $request->input('code'),
            ['business_id' => $request->input('business_id')]
        );

        if (! $result['success']) {
            Log::error('Catalog Embedded Signup failed', [
                'user_id' => $userId,
                'error' => $result['error'],
            ]);

            return response()->json([
                'success' => false,
                'error_code' => 'CATALOG_CODE_EXCHANGE_FAILED',
                'message' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Catalog connected successfully',
        ]);
    }

    /**
     * GET /api/whatsapp/catalog/embedded-signup/config
     */
    public function getConfig(): JsonResponse
    {
        if (! $this->catalogEmbeddedSignupService->isEnabled()) {
            return response()->json([
                'success' => false,
                'error_code' => 'CATALOG_EMBEDDED_SIGNUP_DISABLED',
                'message' => 'Catalog Embedded Signup is not configured. Please add CATALOG_CONFIG_ID to your .env file.',
                'data' => null,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'app_id' => config('whatsapp.catalog_embedded_signup.app_id'),
                'config_id' => config('whatsapp.catalog_embedded_signup.config_id'),
                'api_version' => config('whatsapp.api_version', 'v22.0'),
                'es_version' => config('whatsapp.catalog_embedded_signup.es_version', 'v4'),
            ],
        ]);
    }
}
