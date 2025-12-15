<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EmbeddedSignupDisabledException;
use App\Http\Controllers\Controller;
use App\Services\EmbeddedSignupService;
use App\Services\WhatsAppAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for handling WhatsApp Embedded Signup v4 flow.
 * 
 * This controller manages the OAuth callback, configuration retrieval,
 * account status, and disconnection for WhatsApp Business accounts.
 */
class EmbeddedSignupController extends Controller
{
    protected EmbeddedSignupService $embeddedSignupService;
    protected WhatsAppAccountService $whatsAppAccountService;

    public function __construct(
        EmbeddedSignupService $embeddedSignupService,
        WhatsAppAccountService $whatsAppAccountService
    ) {
        $this->embeddedSignupService = $embeddedSignupService;
        $this->whatsAppAccountService = $whatsAppAccountService;
    }

    /**
     * Handle the OAuth callback from Facebook Embedded Signup.
     * 
     * POST /api/whatsapp/embedded-signup/callback
     * 
     * @param Request $request
     * @return JsonResponse
     * @throws EmbeddedSignupDisabledException
     */
    public function handleCallback(Request $request): JsonResponse
    {
        // Check if Embedded Signup is enabled
        if (!$this->embeddedSignupService->isEnabled()) {
            throw new EmbeddedSignupDisabledException();
        }

        $request->validate([
            'code' => 'required|string',
        ]);

        $userId = auth()->id();
        $code = $request->input('code');

        Log::info('Processing Embedded Signup callback', ['user_id' => $userId]);

        // Process the complete signup flow
        $result = $this->embeddedSignupService->processSignup($userId, $code);

        if (!$result['success']) {
            Log::error('Embedded Signup failed', [
                'user_id' => $userId,
                'error' => $result['error'],
            ]);

            return response()->json([
                'success' => false,
                'error_code' => 'CODE_EXCHANGE_FAILED',
                'message' => $result['error'],
            ], 400);
        }

        $account = $result['account'];

        Log::info('Embedded Signup completed successfully', [
            'user_id' => $userId,
            'phone_number_id' => $account->phone_number_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp account connected successfully',
            'data' => [
                'phone_number' => $account->display_phone_number,
                'display_name' => $account->display_phone_number,
                'verified_name' => $account->name,
                'quality_rating' => $account->quality_rating,
                'is_active' => $account->is_active,
                'coexistence_enabled' => $account->coexistence_enabled,
            ],
        ]);
    }

    /**
     * Get the Embedded Signup configuration for the frontend.
     * 
     * GET /api/whatsapp/embedded-signup/config
     * 
     * @return JsonResponse
     * @throws EmbeddedSignupDisabledException
     */
    public function getConfig(): JsonResponse
    {
        // Check if Embedded Signup is enabled
        if (!$this->embeddedSignupService->isEnabled()) {
            throw new EmbeddedSignupDisabledException();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'app_id' => config('whatsapp.embedded_signup.app_id'),
                'config_id' => config('whatsapp.embedded_signup.config_id'),
                'api_version' => config('whatsapp.api_version', 'v22.0'),
                'es_version' => config('whatsapp.embedded_signup.es_version', 'v4'),
            ],
        ]);
    }

    /**
     * Disconnect the user's WhatsApp account.
     * 
     * DELETE /api/whatsapp/account
     * 
     * @return JsonResponse
     */
    public function disconnect(): JsonResponse
    {
        $userId = auth()->id();

        $deactivated = $this->whatsAppAccountService->deactivateAccount($userId);

        if (!$deactivated) {
            return response()->json([
                'success' => false,
                'message' => 'No connected WhatsApp account found',
            ], 404);
        }

        Log::info('WhatsApp account disconnected', ['user_id' => $userId]);

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp account disconnected successfully',
        ]);
    }

    /**
     * Get the current user's WhatsApp account status.
     * 
     * GET /api/whatsapp/account
     * 
     * @return JsonResponse
     */
    public function getAccountStatus(): JsonResponse
    {
        $userId = auth()->id();

        $status = $this->whatsAppAccountService->getAccountStatus($userId);

        if (!$status) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No WhatsApp account connected',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }
}
