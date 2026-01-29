<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentProviderCredential;
use App\Services\ProviderValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for payment provider credential validation API endpoints.
 *
 * Handles credential validation, manual revalidation, and connection status retrieval.
 * Requirements: 5.1, 5.2, 5.4
 */
class ProviderValidationController extends Controller
{
    public function __construct(
        private ProviderValidationService $validationService,
    ) {}

    /**
     * Validate provider credentials.
     *
     * Requirement 5.1: Validate credentials by making a test API call to the provider
     * Requirement 5.2: Display connection status as "valid" or "invalid"
     */
    public function validate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $credential = PaymentProviderCredential::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $credential) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Provider credential not found',
                ],
            ], 404);
        }

        try {
            $result = $this->validationService->validateCredentials($credential);

            Log::info('Provider credentials validated', [
                'user_id' => $user->id,
                'credential_id' => $credential->id,
                'provider' => $credential->provider,
                'is_valid' => $result->isValid,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'credential_id' => $credential->id,
                    'provider' => $credential->provider,
                    'is_valid' => $result->isValid,
                    'connection_status' => $result->isValid ? 'valid' : 'invalid',
                    'error_message' => $result->errorMessage,
                    'validated_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to validate provider credentials', [
                'user_id' => $user->id,
                'credential_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Failed to validate credentials. Please try again.',
                ],
            ], 500);
        }
    }

    /**
     * Manually revalidate provider credentials.
     *
     * Requirement 5.4: Allow users to manually re-validate credentials at any time
     */
    public function revalidate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $credential = PaymentProviderCredential::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $credential) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Provider credential not found',
                ],
            ], 404);
        }

        try {
            $result = $this->validationService->validateCredentials($credential);

            Log::info('Provider credentials manually revalidated', [
                'user_id' => $user->id,
                'credential_id' => $credential->id,
                'provider' => $credential->provider,
                'is_valid' => $result->isValid,
            ]);

            $credential->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Credentials revalidated successfully',
                'data' => [
                    'credential' => [
                        'id' => $credential->id,
                        'provider' => $credential->provider,
                        'is_active' => $credential->is_active,
                        'connection_status' => $credential->connection_status,
                        'validation_error' => $credential->validation_error,
                        'last_validated_at' => $credential->last_validated_at?->toIso8601String(),
                    ],
                    'validation_result' => [
                        'is_valid' => $result->isValid,
                        'error_message' => $result->errorMessage,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to revalidate provider credentials', [
                'user_id' => $user->id,
                'credential_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REVALIDATION_FAILED',
                    'message' => 'Failed to revalidate credentials. Please try again.',
                ],
            ], 500);
        }
    }

    /**
     * Get connection status for a specific provider credential.
     *
     * Requirement 5.2: Display connection status as "valid" or "invalid"
     */
    public function status(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $credential = PaymentProviderCredential::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $credential) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Provider credential not found',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'credential_id' => $credential->id,
                'provider' => $credential->provider,
                'connection_status' => $credential->connection_status,
                'is_valid' => $credential->connection_status === 'valid',
                'validation_error' => $credential->validation_error,
                'last_validated_at' => $credential->last_validated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get connection status for all configured providers.
     */
    public function statusAll(Request $request): JsonResponse
    {
        $user = $request->user();

        $credentials = PaymentProviderCredential::where('user_id', $user->id)
            ->orderBy('provider')
            ->get();

        $statuses = $credentials->map(function ($credential) {
            return [
                'credential_id' => $credential->id,
                'provider' => $credential->provider,
                'is_active' => $credential->is_active,
                'connection_status' => $credential->connection_status,
                'is_valid' => $credential->connection_status === 'valid',
                'validation_error' => $credential->validation_error,
                'last_validated_at' => $credential->last_validated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'providers' => $statuses,
                'total_configured' => $credentials->count(),
                'total_valid' => $credentials->where('connection_status', 'valid')->count(),
                'total_invalid' => $credentials->where('connection_status', 'invalid')->count(),
            ],
        ]);
    }
}
