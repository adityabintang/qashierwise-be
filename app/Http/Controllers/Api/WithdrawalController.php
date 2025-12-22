<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\WithdrawalAuthentication;
use App\Models\MerchantBalance;
use App\Models\WithdrawalRequest;
use App\Services\SubMerchantService;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Controller for withdrawal request API endpoints.
 * 
 * Handles withdrawal request submission, history, and cancellation.
 * Requirements: 5.1, 5.2, 7.3, 7.5
 */
class WithdrawalController extends Controller
{
    public function __construct(
        private WithdrawalService $withdrawalService,
        private SubMerchantService $subMerchantService,
    ) {}

    /**
     * Submit a new withdrawal request.
     * 
     * Requirement 5.1: Enforce minimum withdrawal amount of Rp 10,000
     * Requirement 5.2: Validate sufficient available balance
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:' . MerchantBalance::MINIMUM_WITHDRAWAL,
        ], [
            'amount.min' => 'Minimum withdrawal amount is Rp ' . number_format(MerchantBalance::MINIMUM_WITHDRAWAL, 0, ',', '.'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid withdrawal request',
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
            ], 403);
        }

        if (!$subMerchant->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MERCHANT_INACTIVE',
                    'message' => 'Sub-merchant account is not active',
                ],
            ], 403);
        }

        $amount = (float) $request->input('amount');

        // Validate withdrawal before processing
        $validationErrors = $this->withdrawalService->validateWithdrawal($subMerchant, $amount);
        if (!empty($validationErrors)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WITHDRAWAL_VALIDATION_FAILED',
                    'message' => 'Withdrawal validation failed',
                ],
                'errors' => $validationErrors,
            ], 422);
        }

        try {
            $withdrawalRequest = $this->withdrawalService->createWithdrawalRequest($subMerchant, $amount);

            Log::info('Withdrawal request created via API', [
                'user_id' => $user->id,
                'sub_merchant_id' => $subMerchant->id,
                'withdrawal_id' => $withdrawalRequest->id,
                'amount' => $amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully',
                'data' => [
                    'withdrawal' => $this->formatWithdrawalResponse($withdrawalRequest),
                ],
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WITHDRAWAL_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Get withdrawal request details.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
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
            ], 403);
        }

        $withdrawal = $this->withdrawalService->getWithdrawalById($id, $subMerchant);

        if ($withdrawal === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WITHDRAWAL_NOT_FOUND',
                    'message' => 'Withdrawal request not found',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'withdrawal' => $this->formatWithdrawalResponse($withdrawal),
            ],
        ]);
    }

    /**
     * Get withdrawal history for the current sub-merchant.
     * 
     * Requirement 7.5: Display withdrawal history with status tracking
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
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
            ], 403);
        }

        $limit = min((int) $request->input('limit', 50), 100);
        $status = $request->input('status');

        $query = $subMerchant->withdrawalRequests()
            ->with('processedBy')
            ->orderBy('created_at', 'desc');

        if ($status !== null && in_array($status, [
            WithdrawalRequest::STATUS_PENDING,
            WithdrawalRequest::STATUS_APPROVED,
            WithdrawalRequest::STATUS_REJECTED,
            WithdrawalRequest::STATUS_PROCESSED,
        ])) {
            $query->where('status', $status);
        }

        $withdrawals = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'withdrawals' => $withdrawals->map(fn ($w) => $this->formatWithdrawalResponse($w)),
                'count' => $withdrawals->count(),
            ],
        ]);
    }

    /**
     * Get withdrawal statistics for the current sub-merchant.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
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
            ], 403);
        }

        $stats = $this->withdrawalService->getWithdrawalStats($subMerchant);

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_requests' => $stats['total_requests'],
                    'pending_count' => $stats['pending_count'],
                    'approved_count' => $stats['approved_count'],
                    'rejected_count' => $stats['rejected_count'],
                    'processed_count' => $stats['processed_count'],
                    'total_withdrawn' => $stats['total_withdrawn'],
                    'pending_amount' => $stats['pending_amount'],
                ],
                'currency' => 'IDR',
            ],
        ]);
    }

    /**
     * Cancel a pending withdrawal request.
     * 
     * Requirement 7.3: Provide withdrawal request form with validation
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function cancel(Request $request, int $id): JsonResponse
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
            ], 403);
        }

        $withdrawal = $this->withdrawalService->getWithdrawalById($id, $subMerchant);

        if ($withdrawal === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WITHDRAWAL_NOT_FOUND',
                    'message' => 'Withdrawal request not found',
                ],
            ], 404);
        }

        if (!$withdrawal->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_CANCEL',
                    'message' => 'Only pending withdrawal requests can be cancelled',
                ],
            ], 422);
        }

        try {
            $this->withdrawalService->cancelWithdrawal($withdrawal);

            Log::info('Withdrawal request cancelled via API', [
                'user_id' => $user->id,
                'withdrawal_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request cancelled successfully',
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANCEL_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Validate withdrawal amount before submission.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validate(Request $request): JsonResponse
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
            ], 403);
        }

        $amount = (float) $request->input('amount', 0);

        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_AMOUNT',
                    'message' => 'Amount must be greater than zero',
                ],
            ], 422);
        }

        $validationErrors = $this->withdrawalService->validateWithdrawal($subMerchant, $amount);
        $isValid = empty($validationErrors);

        $subMerchant->load('balance');

        return response()->json([
            'success' => true,
            'data' => [
                'is_valid' => $isValid,
                'errors' => $validationErrors,
                'amount' => $amount,
                'available_balance' => (float) ($subMerchant->balance?->available_balance ?? 0),
                'minimum_withdrawal' => MerchantBalance::MINIMUM_WITHDRAWAL,
            ],
        ]);
    }

    /**
     * Confirm password for withdrawal operations.
     * 
     * Requirement 9.4: Require additional authentication for withdrawals
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function confirmPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Password is required',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $password = $request->input('password');

        if (!Hash::check($password, $user->password)) {
            Log::warning('Withdrawal password confirmation failed', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PASSWORD',
                    'message' => 'The provided password is incorrect',
                ],
            ], 403);
        }

        // Store password confirmation in cache
        $cacheKey = "withdrawal_password_confirmed:{$user->id}";
        cache()->put($cacheKey, time(), WithdrawalAuthentication::PASSWORD_CONFIRMATION_TIMEOUT);

        Log::info('Withdrawal password confirmed via API', [
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password confirmed successfully',
            'data' => [
                'confirmed' => true,
                'expires_in_seconds' => WithdrawalAuthentication::PASSWORD_CONFIRMATION_TIMEOUT,
            ],
        ]);
    }

    /**
     * Check password confirmation status.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkPasswordConfirmation(Request $request): JsonResponse
    {
        $user = $request->user();
        $remainingTime = WithdrawalAuthentication::getRemainingConfirmationTime($user->id);

        return response()->json([
            'success' => true,
            'data' => [
                'is_confirmed' => $remainingTime > 0,
                'remaining_seconds' => $remainingTime,
            ],
        ]);
    }

    /**
     * Format withdrawal request data for API response.
     * 
     * @param WithdrawalRequest $withdrawal
     * @return array
     */
    private function formatWithdrawalResponse(WithdrawalRequest $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'amount' => (float) $withdrawal->amount,
            'status' => $withdrawal->status,
            'bank_details' => [
                'bank_name' => $withdrawal->getBankName(),
                'account_number' => $this->maskAccountNumber($withdrawal->getAccountNumber()),
                'account_holder_name' => $withdrawal->getAccountHolderName(),
            ],
            'admin_notes' => $withdrawal->admin_notes,
            'can_be_cancelled' => $withdrawal->canBeCancelled(),
            'processed_at' => $withdrawal->processed_at?->toIso8601String(),
            'processed_by' => $withdrawal->processedBy ? [
                'id' => $withdrawal->processedBy->id,
                'name' => $withdrawal->processedBy->name,
            ] : null,
            'created_at' => $withdrawal->created_at->toIso8601String(),
            'updated_at' => $withdrawal->updated_at->toIso8601String(),
        ];
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
