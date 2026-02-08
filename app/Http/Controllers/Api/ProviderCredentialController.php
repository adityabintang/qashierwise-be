<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentProviderCredential;
use App\Services\ProviderCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Controller for payment provider credential management API endpoints.
 *
 * Handles provider credential configuration, updates, deletion, and active provider selection.
 * Requirements: 1.6, 1.7, 4.1, 8.1, 8.3
 */
class ProviderCredentialController extends Controller
{
    public function __construct(
        private ProviderCredentialService $credentialService,
    ) {}

    /**
     * List all configured providers for the current user.
     *
     * Requirement 1.6: Allow users to configure credentials for multiple providers
     * Requirement 1.7: Store provider type and credential fields
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $credentials = PaymentProviderCredential::where('user_id', $user->id)
            ->orderBy('provider')
            ->get();

        $providers = $credentials->map(function ($credential) {
            return [
                'id' => $credential->id,
                'provider' => $credential->provider,
                'is_active' => $credential->is_active,
                'connection_status' => $credential->connection_status,
                'validation_error' => $credential->validation_error,
                'last_validated_at' => $credential->last_validated_at?->toIso8601String(),
                'created_at' => $credential->created_at->toIso8601String(),
                'updated_at' => $credential->updated_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'providers' => $providers,
                'active_provider' => $credentials->firstWhere('is_active', true)?->provider,
            ],
        ]);
    }

    /**
     * Store new provider credentials.
     *
     * Requirement 1.6: Allow users to configure credentials for multiple providers
     * Requirement 8.1: Allow users to update existing provider credentials
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|in:doku,xendit,midtrans,duitku',
            'credentials' => 'required|array',
            'credentials.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid provider credentials data',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $provider = $request->input('provider');
        $credentials = $request->input('credentials');

        try {
            $credential = $this->credentialService->storeCredentials(
                $user,
                $provider,
                $credentials
            );

            Log::info('Provider credentials stored', [
                'user_id' => $user->id,
                'provider' => $provider,
                'credential_id' => $credential->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Provider credentials stored successfully',
                'data' => [
                    'credential' => [
                        'id' => $credential->id,
                        'provider' => $credential->provider,
                        'is_active' => $credential->is_active,
                        'connection_status' => $credential->connection_status,
                        'validation_error' => $credential->validation_error,
                        'last_validated_at' => $credential->last_validated_at?->toIso8601String(),
                        'created_at' => $credential->created_at->toIso8601String(),
                    ],
                ],
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to store provider credentials', [
                'user_id' => $user->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to store credentials. Please try again.',
                ],
            ], 500);
        }
    }

    /**
     * Update existing provider credentials.
     *
     * Requirement 8.1: Allow users to update existing provider credentials
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'credentials' => 'required|array',
            'credentials.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid credentials data',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

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
            $success = $this->credentialService->updateCredentials(
                $credential,
                $request->input('credentials')
            );

            if (! $success) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UPDATE_FAILED',
                        'message' => 'Failed to update credentials',
                    ],
                ], 500);
            }

            $credential->refresh();

            Log::info('Provider credentials updated', [
                'user_id' => $user->id,
                'credential_id' => $credential->id,
                'provider' => $credential->provider,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Provider credentials updated successfully',
                'data' => [
                    'credential' => [
                        'id' => $credential->id,
                        'provider' => $credential->provider,
                        'is_active' => $credential->is_active,
                        'connection_status' => $credential->connection_status,
                        'validation_error' => $credential->validation_error,
                        'last_validated_at' => $credential->last_validated_at?->toIso8601String(),
                        'updated_at' => $credential->updated_at->toIso8601String(),
                    ],
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update provider credentials', [
                'user_id' => $user->id,
                'credential_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to update credentials. Please try again.',
                ],
            ], 500);
        }
    }

    /**
     * Delete provider credentials.
     *
     * Requirement 8.3: Allow users to delete provider credentials
     */
    public function destroy(Request $request, int $id): JsonResponse
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

        $wasActive = $credential->is_active;
        $provider = $credential->provider;

        try {
            $success = $this->credentialService->deleteCredentials($credential);

            if (! $success) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DELETE_FAILED',
                        'message' => 'Failed to delete credentials',
                    ],
                ], 500);
            }

            Log::info('Provider credentials deleted', [
                'user_id' => $user->id,
                'credential_id' => $id,
                'provider' => $provider,
                'was_active' => $wasActive,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Provider credentials deleted successfully',
                'data' => [
                    'was_active' => $wasActive,
                    'requires_new_active_provider' => $wasActive,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete provider credentials', [
                'user_id' => $user->id,
                'credential_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to delete credentials. Please try again.',
                ],
            ], 500);
        }
    }

    /**
     * Set active provider for the current user.
     *
     * Requirement 4.1: Allow users to designate one provider as active at a time
     */
    public function setActive(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|in:doku,xendit,midtrans,duitku',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid provider',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $provider = $request->input('provider');

        try {
            $success = $this->credentialService->setActiveProvider($user, $provider);

            if (! $success) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SET_ACTIVE_FAILED',
                        'message' => 'Provider not found or credentials not configured',
                    ],
                ], 404);
            }

            Log::info('Active provider set', [
                'user_id' => $user->id,
                'provider' => $provider,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Active provider set successfully',
                'data' => [
                    'active_provider' => $provider,
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SET_ACTIVE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to set active provider', [
                'user_id' => $user->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to set active provider. Please try again.',
                ],
            ], 500);
        }
    }
}
