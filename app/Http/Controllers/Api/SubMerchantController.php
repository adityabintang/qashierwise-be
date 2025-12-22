<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubMerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Controller for sub-merchant registration and management API endpoints.
 * 
 * Handles sub-merchant registration, bank account updates, and profile management.
 * Requirements: 1.1, 1.2, 10.1
 */
class SubMerchantController extends Controller
{
    public function __construct(
        private SubMerchantService $subMerchantService,
    ) {}

    /**
     * Get the current user's sub-merchant status and profile.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $subMerchant = $this->subMerchantService->findByUserId($user->id);

        if ($subMerchant === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'is_sub_merchant' => false,
                    'can_register' => $this->subMerchantService->canBecomeSubMerchant($user),
                ],
            ]);
        }

        $subMerchant->load('balance');

        return response()->json([
            'success' => true,
            'data' => [
                'is_sub_merchant' => true,
                'sub_merchant' => [
                    'id' => $subMerchant->id,
                    'bank_name' => $subMerchant->bank_name,
                    'account_number' => $this->maskAccountNumber($subMerchant->account_number),
                    'account_holder_name' => $subMerchant->account_holder_name,
                    'is_active' => $subMerchant->is_active,
                    'is_verified' => $subMerchant->isVerified(),
                    'verified_at' => $subMerchant->verified_at?->toIso8601String(),
                    'can_accept_payments' => $subMerchant->canAcceptPayments(),
                    'created_at' => $subMerchant->created_at->toIso8601String(),
                ],
                'balance' => $subMerchant->balance ? [
                    'available' => (float) $subMerchant->balance->available_balance,
                    'pending' => (float) $subMerchant->balance->pending_balance,
                    'total_earned' => (float) $subMerchant->balance->total_earned,
                    'total_withdrawn' => (float) $subMerchant->balance->total_withdrawn,
                ] : null,
            ],
        ]);
    }

    /**
     * Register the current user as a sub-merchant.
     * 
     * Requirement 1.1: Collect and validate bank account information
     * Requirement 10.1: Prompt existing users for bank account setup
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|regex:/^[0-9]+$/|min:5|max:50',
            'account_holder_name' => 'required|string|max:100',
        ], [
            'account_number.regex' => 'Account number must contain only digits',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid bank account details',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Check if user can become a sub-merchant
        if (!$this->subMerchantService->canBecomeSubMerchant($user)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ALREADY_REGISTERED',
                    'message' => 'User is already registered as a sub-merchant',
                ],
            ], 409);
        }

        try {
            $subMerchant = $this->subMerchantService->registerSubMerchant($user, [
                'bank_name' => $request->input('bank_name'),
                'account_number' => $request->input('account_number'),
                'account_holder_name' => $request->input('account_holder_name'),
            ]);

            Log::info('Sub-merchant registered via API', [
                'user_id' => $user->id,
                'sub_merchant_id' => $subMerchant->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully registered as sub-merchant',
                'data' => [
                    'sub_merchant' => [
                        'id' => $subMerchant->id,
                        'bank_name' => $subMerchant->bank_name,
                        'account_number' => $this->maskAccountNumber($subMerchant->account_number),
                        'account_holder_name' => $subMerchant->account_holder_name,
                        'is_active' => $subMerchant->is_active,
                        'is_verified' => $subMerchant->isVerified(),
                        'created_at' => $subMerchant->created_at->toIso8601String(),
                    ],
                ],
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REGISTRATION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Update bank account information for the current sub-merchant.
     * 
     * Requirement 1.2: Allow existing users to update bank account details
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateBankAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|regex:/^[0-9]+$/|min:5|max:50',
            'account_holder_name' => 'required|string|max:100',
        ], [
            'account_number.regex' => 'Account number must contain only digits',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid bank account details',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $subMerchant = $this->subMerchantService->findByUserId($user->id);

        if ($subMerchant === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_SUB_MERCHANT',
                    'message' => 'User is not registered as a sub-merchant',
                ],
            ], 404);
        }

        try {
            $this->subMerchantService->updateBankAccount($subMerchant, [
                'bank_name' => $request->input('bank_name'),
                'account_number' => $request->input('account_number'),
                'account_holder_name' => $request->input('account_holder_name'),
            ]);

            $subMerchant->refresh();

            Log::info('Sub-merchant bank account updated via API', [
                'user_id' => $user->id,
                'sub_merchant_id' => $subMerchant->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank account updated successfully',
                'data' => [
                    'sub_merchant' => [
                        'id' => $subMerchant->id,
                        'bank_name' => $subMerchant->bank_name,
                        'account_number' => $this->maskAccountNumber($subMerchant->account_number),
                        'account_holder_name' => $subMerchant->account_holder_name,
                        'updated_at' => $subMerchant->updated_at->toIso8601String(),
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
        }
    }

    /**
     * Get the full sub-merchant profile with balance details.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $subMerchant = $this->subMerchantService->findByUserId($user->id);

        if ($subMerchant === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_SUB_MERCHANT',
                    'message' => 'User is not registered as a sub-merchant',
                ],
            ], 404);
        }

        $profileData = $this->subMerchantService->getWithBalance($subMerchant);

        return response()->json([
            'success' => true,
            'data' => [
                'sub_merchant' => [
                    'id' => $subMerchant->id,
                    'bank_name' => $subMerchant->bank_name,
                    'account_number' => $this->maskAccountNumber($subMerchant->account_number),
                    'account_holder_name' => $subMerchant->account_holder_name,
                    'is_active' => $subMerchant->is_active,
                    'is_verified' => $subMerchant->isVerified(),
                    'verified_at' => $subMerchant->verified_at?->toIso8601String(),
                    'can_accept_payments' => $profileData['can_accept_payments'],
                    'created_at' => $subMerchant->created_at->toIso8601String(),
                    'updated_at' => $subMerchant->updated_at->toIso8601String(),
                ],
                'balance' => $profileData['balance'] ? [
                    'available' => (float) $profileData['balance']->available_balance,
                    'pending' => (float) $profileData['balance']->pending_balance,
                    'total_earned' => (float) $profileData['balance']->total_earned,
                    'total_withdrawn' => (float) $profileData['balance']->total_withdrawn,
                    'total_balance' => $profileData['balance']->getTotalBalance(),
                    'last_updated' => $profileData['balance']->last_updated?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    /**
     * Deactivate the current sub-merchant account.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function deactivate(Request $request): JsonResponse
    {
        $user = $request->user();
        $subMerchant = $this->subMerchantService->findByUserId($user->id);

        if ($subMerchant === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_SUB_MERCHANT',
                    'message' => 'User is not registered as a sub-merchant',
                ],
            ], 404);
        }

        if (!$subMerchant->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ALREADY_INACTIVE',
                    'message' => 'Sub-merchant account is already inactive',
                ],
            ], 409);
        }

        $this->subMerchantService->deactivateSubMerchant($subMerchant);

        Log::info('Sub-merchant deactivated via API', [
            'user_id' => $user->id,
            'sub_merchant_id' => $subMerchant->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sub-merchant account deactivated successfully',
        ]);
    }

    /**
     * Reactivate the current sub-merchant account.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function activate(Request $request): JsonResponse
    {
        $user = $request->user();
        $subMerchant = $this->subMerchantService->findByUserId($user->id);

        if ($subMerchant === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_SUB_MERCHANT',
                    'message' => 'User is not registered as a sub-merchant',
                ],
            ], 404);
        }

        if ($subMerchant->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ALREADY_ACTIVE',
                    'message' => 'Sub-merchant account is already active',
                ],
            ], 409);
        }

        $this->subMerchantService->activateSubMerchant($subMerchant);

        Log::info('Sub-merchant activated via API', [
            'user_id' => $user->id,
            'sub_merchant_id' => $subMerchant->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sub-merchant account activated successfully',
        ]);
    }

    /**
     * Mask account number for display (show only last 4 digits).
     * 
     * @param string|null $accountNumber
     * @return string|null
     */
    private function maskAccountNumber(?string $accountNumber): ?string
    {
        if ($accountNumber === null || strlen($accountNumber) < 4) {
            return $accountNumber;
        }

        $visibleDigits = substr($accountNumber, -4);
        $maskedLength = strlen($accountNumber) - 4;
        
        return str_repeat('*', $maskedLength) . $visibleDigits;
    }
}
