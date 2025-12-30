<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\QrisTransaction;
use App\Services\QrisService;
use App\Services\SubMerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Controller for QRIS generation and sharing API endpoints.
 * 
 * Handles QRIS code generation, retrieval, and sharing functionality.
 * Requirements: 2.1, 2.2, 2.3, 8.1, 8.2, 8.5
 */
class QrisController extends Controller
{
    public function __construct(
        private QrisService $qrisService,
        private SubMerchantService $subMerchantService,
    ) {}

    /**
     * Generate a new QRIS code for a transaction.
     * 
     * Requirement 2.1: Generate unique order_id for each transaction
     * Requirement 2.2: Create QR code through multi-provider API
     * Requirement 2.3: Provide both image and shareable link formats
     * Requirement 4.5: Prevent QRIS generation when no provider is active
     * Requirement 7.5: Provide provider-specific error messages
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1000|max:100000000',
            'description' => 'nullable|string|max:255',
            'customer_name' => 'nullable|string|max:100',
            'customer_email' => 'nullable|email|max:255',
        ], [
            'amount.min' => 'Minimum transaction amount is Rp 1,000',
            'amount.max' => 'Maximum transaction amount is Rp 100,000,000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $user = $request->user();
        $subMerchant = $this->subMerchantService->findByUserId($user->id);

        if ($subMerchant === null) {
            return ApiResponse::forbidden('messages.error.forbidden');
        }

        try {
            $transaction = $this->qrisService->generateQris(
                $subMerchant,
                (float) $request->input('amount'),
                [
                    'description' => $request->input('description'),
                    'customer_name' => $request->input('customer_name'),
                    'customer_email' => $request->input('customer_email'),
                ]
            );

            Log::info('QRIS generated via API', [
                'user_id' => $user->id,
                'sub_merchant_id' => $subMerchant->id,
                'order_id' => $transaction->order_id,
                'amount' => $transaction->amount,
                'provider' => $transaction->provider,
            ]);

            return ApiResponse::success([
                'transaction' => $this->formatTransactionResponse($transaction),
            ], 'payments.qris_generated', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error('messages.error.invalid_data', 422);
        } catch (RuntimeException $e) {
            Log::error('QRIS generation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            // Check if error is about no active provider (Requirement 4.5)
            if (str_contains($e->getMessage(), 'No active payment provider')) {
                return ApiResponse::error('payments.no_active_provider', 428);
            }

            // Check if error is about invalid provider configuration
            if (str_contains($e->getMessage(), 'not properly configured')) {
                return ApiResponse::error('payments.provider_invalid', 428);
            }

            // Generic provider error (Requirement 7.5)
            return ApiResponse::serverError('messages.error.server');
        }
    }

    /**
     * Get QRIS transaction details by order ID.
     * 
     * @param Request $request
     * @param string $orderId
     * @return JsonResponse
     */
    public function show(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        $subMerchant = $this->subMerchantService->findByUserId($user->id);

        if ($subMerchant === null) {
            return ApiResponse::forbidden('messages.error.forbidden');
        }

        $transaction = $this->qrisService->findByOrderId($orderId);

        if ($transaction === null || $transaction->sub_merchant_id !== $subMerchant->id) {
            return ApiResponse::notFound('messages.error.not_found');
        }

        return ApiResponse::success([
            'transaction' => $this->formatTransactionResponse($transaction),
        ]);
    }

    /**
     * Get QR code image URL for a transaction.
     * 
     * Requirement 8.1: Provide downloadable QR code image
     * 
     * @param Request $request
     * @param string $orderId
     * @return JsonResponse
     */
    public function getQrCode(Request $request, string $orderId): JsonResponse
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

        $transaction = $this->qrisService->findByOrderId($orderId);

        if ($transaction === null || $transaction->sub_merchant_id !== $subMerchant->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TRANSACTION_NOT_FOUND',
                    'message' => 'QRIS transaction not found',
                ],
            ], 404);
        }

        $qrCodeUrl = $this->qrisService->getQrCodeUrl($transaction);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $transaction->order_id,
                'qr_code_url' => $qrCodeUrl,
                'is_expired' => $transaction->isExpired(),
                'can_be_used' => $transaction->canBeUsed(),
            ],
        ]);
    }

    /**
     * Get shareable link for a QRIS transaction.
     * 
     * Requirement 8.2: Generate shareable links for QRIS codes
     * 
     * @param Request $request
     * @param string $orderId
     * @return JsonResponse
     */
    public function getShareableLink(Request $request, string $orderId): JsonResponse
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

        $transaction = $this->qrisService->findByOrderId($orderId);

        if ($transaction === null || $transaction->sub_merchant_id !== $subMerchant->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TRANSACTION_NOT_FOUND',
                    'message' => 'QRIS transaction not found',
                ],
            ], 404);
        }

        $shareableLink = $this->qrisService->generateShareableLink($transaction);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $transaction->order_id,
                'shareable_link' => $shareableLink,
                'amount' => (float) $transaction->amount,
                'is_expired' => $transaction->isExpired(),
                'expires_at' => $transaction->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Check QRIS transaction status.
     * 
     * Requirement 8.5: Display payment interface when QRIS is accessed
     * 
     * @param Request $request
     * @param string $orderId
     * @return JsonResponse
     */
    public function checkStatus(Request $request, string $orderId): JsonResponse
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

        $transaction = $this->qrisService->findByOrderId($orderId);

        if ($transaction === null || $transaction->sub_merchant_id !== $subMerchant->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TRANSACTION_NOT_FOUND',
                    'message' => 'QRIS transaction not found',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $transaction->order_id,
                'status' => $transaction->status,
                'amount' => (float) $transaction->amount,
                'is_expired' => $transaction->isExpired(),
                'is_settled' => $transaction->isSettled(),
                'can_be_used' => $transaction->canBeUsed(),
                'remaining_time_seconds' => $transaction->getRemainingTimeInSeconds(),
                'expires_at' => $transaction->expires_at?->toIso8601String(),
                'settled_at' => $transaction->settled_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get transaction history for the current sub-merchant.
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

        $query = $subMerchant->transactions()->orderBy('created_at', 'desc');

        if ($status !== null && in_array($status, [
            QrisTransaction::STATUS_PENDING,
            QrisTransaction::STATUS_SETTLEMENT,
            QrisTransaction::STATUS_EXPIRE,
            QrisTransaction::STATUS_CANCEL,
        ])) {
            $query->where('status', $status);
        }

        $transactions = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions->map(fn ($t) => $this->formatTransactionResponse($t)),
                'count' => $transactions->count(),
            ],
        ]);
    }

    /**
     * Get pending transactions for the current sub-merchant.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function pending(Request $request): JsonResponse
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

        $transactions = $this->qrisService->getPendingTransactions($subMerchant);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions->map(fn ($t) => $this->formatTransactionResponse($t)),
                'count' => $transactions->count(),
            ],
        ]);
    }

    /**
     * Cancel a pending QRIS transaction.
     * 
     * @param Request $request
     * @param string $orderId
     * @return JsonResponse
     */
    public function cancel(Request $request, string $orderId): JsonResponse
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

        $transaction = $this->qrisService->findByOrderId($orderId);

        if ($transaction === null || $transaction->sub_merchant_id !== $subMerchant->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TRANSACTION_NOT_FOUND',
                    'message' => 'QRIS transaction not found',
                ],
            ], 404);
        }

        try {
            $this->qrisService->cancelTransaction($transaction);

            Log::info('QRIS transaction cancelled via API', [
                'user_id' => $user->id,
                'order_id' => $orderId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'QRIS transaction cancelled successfully',
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
     * Format transaction data for API response.
     * 
     * Requirement 7.5: Include provider information in response
     * 
     * @param QrisTransaction $transaction
     * @return array
     */
    private function formatTransactionResponse(QrisTransaction $transaction): array
    {
        return [
            'order_id' => $transaction->order_id,
            'amount' => (float) $transaction->amount,
            'platform_fee' => (float) $transaction->platform_fee,
            'net_amount' => (float) $transaction->net_amount,
            'status' => $transaction->status,
            'provider' => $transaction->provider,
            'provider_transaction_id' => $transaction->provider_transaction_id,
            'qr_code_url' => $transaction->qr_code_url,
            'shareable_link' => $transaction->getShareableLink(),
            'is_expired' => $transaction->isExpired(),
            'can_be_used' => $transaction->canBeUsed(),
            'remaining_time_seconds' => $transaction->getRemainingTimeInSeconds(),
            'expires_at' => $transaction->expires_at?->toIso8601String(),
            'settled_at' => $transaction->settled_at?->toIso8601String(),
            'created_at' => $transaction->created_at->toIso8601String(),
        ];
    }
}
