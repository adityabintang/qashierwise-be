<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Controller for admin withdrawal management API endpoints.
 * 
 * Handles listing, approving, rejecting, and processing withdrawal requests.
 * Requirements: 6.1, 6.2, 6.3, 6.4
 */
class AdminWithdrawalController extends Controller
{
    public function __construct(
        private WithdrawalService $withdrawalService,
    ) {}

    /**
     * List all withdrawal requests with optional filtering.
     * 
     * Requirement 6.1: Display all pending withdrawal requests
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $limit = min((int) $request->input('limit', 50), 100);
        $page = (int) $request->input('page', 1);

        $query = WithdrawalRequest::with(['subMerchant.user', 'subMerchant.balance', 'processedBy'])
            ->orderBy('created_at', 'desc');

        // Filter by status if provided
        if ($status !== null && in_array($status, [
            WithdrawalRequest::STATUS_PENDING,
            WithdrawalRequest::STATUS_APPROVED,
            WithdrawalRequest::STATUS_REJECTED,
            WithdrawalRequest::STATUS_PROCESSED,
        ])) {
            $query->where('status', $status);
        }

        $withdrawals = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'withdrawals' => $withdrawals->items() ? collect($withdrawals->items())->map(fn ($w) => $this->formatWithdrawalResponse($w)) : [],
                'pagination' => [
                    'current_page' => $withdrawals->currentPage(),
                    'last_page' => $withdrawals->lastPage(),
                    'per_page' => $withdrawals->perPage(),
                    'total' => $withdrawals->total(),
                    'from' => $withdrawals->firstItem(),
                    'to' => $withdrawals->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * Get pending withdrawal requests for admin review.
     * 
     * Requirement 6.1: Display all pending withdrawal requests
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function pending(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 50), 100);

        $withdrawals = $this->withdrawalService->getPendingWithdrawals($limit);

        return response()->json([
            'success' => true,
            'data' => [
                'withdrawals' => $withdrawals->map(fn ($w) => $this->formatWithdrawalResponse($w)),
                'count' => $withdrawals->count(),
            ],
        ]);
    }

    /**
     * Get withdrawal request details.
     * 
     * Requirement 6.2: Show sub-merchant details and bank account information
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $withdrawal = $this->withdrawalService->getWithdrawalById($id);

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
                'withdrawal' => $this->formatWithdrawalResponse($withdrawal, true),
            ],
        ]);
    }

    /**
     * Approve a withdrawal request.
     * 
     * Requirement 6.3: Process bank transfer and update request status
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $withdrawal = $this->withdrawalService->getWithdrawalById($id);

        if ($withdrawal === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WITHDRAWAL_NOT_FOUND',
                    'message' => 'Withdrawal request not found',
                ],
            ], 404);
        }

        if (!$withdrawal->canBeProcessed()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_APPROVE',
                    'message' => 'Only pending withdrawal requests can be approved',
                ],
            ], 422);
        }

        try {
            $admin = $request->user();
            $notes = $request->input('notes');

            $updatedWithdrawal = $this->withdrawalService->approveWithdrawal($withdrawal, $admin, $notes);

            Log::info('Withdrawal request approved by admin', [
                'admin_id' => $admin->id,
                'withdrawal_id' => $id,
                'amount' => $withdrawal->amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request approved successfully',
                'data' => [
                    'withdrawal' => $this->formatWithdrawalResponse($updatedWithdrawal),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'APPROVAL_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Reject a withdrawal request.
     * 
     * Requirement 6.4: Return amount to sub-merchant balance and notify them
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:1000',
        ], [
            'reason.required' => 'Rejection reason is required',
            'reason.min' => 'Rejection reason must be at least 10 characters',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $withdrawal = $this->withdrawalService->getWithdrawalById($id);

        if ($withdrawal === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WITHDRAWAL_NOT_FOUND',
                    'message' => 'Withdrawal request not found',
                ],
            ], 404);
        }

        if (!$withdrawal->canBeProcessed()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_REJECT',
                    'message' => 'Only pending withdrawal requests can be rejected',
                ],
            ], 422);
        }

        try {
            $admin = $request->user();
            $reason = $request->input('reason');

            $updatedWithdrawal = $this->withdrawalService->rejectWithdrawal($withdrawal, $admin, $reason);

            Log::info('Withdrawal request rejected by admin', [
                'admin_id' => $admin->id,
                'withdrawal_id' => $id,
                'amount' => $withdrawal->amount,
                'reason' => $reason,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request rejected successfully',
                'data' => [
                    'withdrawal' => $this->formatWithdrawalResponse($updatedWithdrawal),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REJECTION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Mark an approved withdrawal as processed (bank transfer completed).
     * 
     * Requirement 6.3: Process bank transfer and update request status
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function markProcessed(Request $request, int $id): JsonResponse
    {
        $withdrawal = $this->withdrawalService->getWithdrawalById($id);

        if ($withdrawal === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WITHDRAWAL_NOT_FOUND',
                    'message' => 'Withdrawal request not found',
                ],
            ], 404);
        }

        if (!$withdrawal->isApproved()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_PROCESS',
                    'message' => 'Only approved withdrawal requests can be marked as processed',
                ],
            ], 422);
        }

        try {
            $updatedWithdrawal = $this->withdrawalService->markAsProcessed($withdrawal);

            Log::info('Withdrawal request marked as processed', [
                'admin_id' => $request->user()->id,
                'withdrawal_id' => $id,
                'amount' => $withdrawal->amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal marked as processed successfully',
                'data' => [
                    'withdrawal' => $this->formatWithdrawalResponse($updatedWithdrawal),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PROCESS_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Get withdrawal statistics for admin dashboard.
     * 
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        $pendingCount = WithdrawalRequest::pending()->count();
        $pendingAmount = WithdrawalRequest::pending()->sum('amount');
        
        $approvedCount = WithdrawalRequest::approved()->count();
        $approvedAmount = WithdrawalRequest::approved()->sum('amount');
        
        $processedCount = WithdrawalRequest::processed()->count();
        $processedAmount = WithdrawalRequest::processed()->sum('amount');
        
        $rejectedCount = WithdrawalRequest::rejected()->count();
        $rejectedAmount = WithdrawalRequest::rejected()->sum('amount');

        $totalCount = WithdrawalRequest::count();
        $totalAmount = WithdrawalRequest::sum('amount');

        // Get recent activity (last 7 days)
        $recentPending = WithdrawalRequest::pending()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'pending' => [
                        'count' => $pendingCount,
                        'amount' => (float) $pendingAmount,
                    ],
                    'approved' => [
                        'count' => $approvedCount,
                        'amount' => (float) $approvedAmount,
                    ],
                    'processed' => [
                        'count' => $processedCount,
                        'amount' => (float) $processedAmount,
                    ],
                    'rejected' => [
                        'count' => $rejectedCount,
                        'amount' => (float) $rejectedAmount,
                    ],
                    'total' => [
                        'count' => $totalCount,
                        'amount' => (float) $totalAmount,
                    ],
                    'recent_pending' => $recentPending,
                ],
                'currency' => 'IDR',
            ],
        ]);
    }

    /**
     * Get audit trail for a withdrawal request.
     * 
     * Requirement 6.5: Maintain audit trail of all approval actions
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function auditTrail(int $id): JsonResponse
    {
        $withdrawal = WithdrawalRequest::with(['subMerchant.user', 'processedBy'])->find($id);

        if ($withdrawal === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WITHDRAWAL_NOT_FOUND',
                    'message' => 'Withdrawal request not found',
                ],
            ], 404);
        }

        // Build audit trail from withdrawal data
        $auditTrail = [];

        // Creation event
        $auditTrail[] = [
            'action' => 'created',
            'timestamp' => $withdrawal->created_at->toIso8601String(),
            'user' => $withdrawal->subMerchant?->user ? [
                'id' => $withdrawal->subMerchant->user->id,
                'name' => $withdrawal->subMerchant->user->name,
                'type' => 'merchant',
            ] : null,
            'details' => [
                'amount' => (float) $withdrawal->amount,
                'bank_name' => $withdrawal->getBankName(),
            ],
        ];

        // Processing event (if processed)
        if ($withdrawal->processed_at !== null) {
            $action = match ($withdrawal->status) {
                WithdrawalRequest::STATUS_APPROVED => 'approved',
                WithdrawalRequest::STATUS_REJECTED => 'rejected',
                WithdrawalRequest::STATUS_PROCESSED => 'processed',
                default => 'updated',
            };

            $auditTrail[] = [
                'action' => $action,
                'timestamp' => $withdrawal->processed_at->toIso8601String(),
                'user' => $withdrawal->processedBy ? [
                    'id' => $withdrawal->processedBy->id,
                    'name' => $withdrawal->processedBy->name,
                    'type' => 'admin',
                ] : null,
                'details' => [
                    'notes' => $withdrawal->admin_notes,
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'withdrawal_id' => $withdrawal->id,
                'current_status' => $withdrawal->status,
                'audit_trail' => $auditTrail,
            ],
        ]);
    }

    /**
     * Format withdrawal request data for API response.
     * 
     * @param WithdrawalRequest $withdrawal
     * @param bool $includeFullBankDetails Whether to include full bank account details
     * @return array
     */
    private function formatWithdrawalResponse(WithdrawalRequest $withdrawal, bool $includeFullBankDetails = false): array
    {
        $response = [
            'id' => $withdrawal->id,
            'amount' => (float) $withdrawal->amount,
            'status' => $withdrawal->status,
            'bank_details' => [
                'bank_name' => $withdrawal->getBankName(),
                'account_number' => $includeFullBankDetails 
                    ? $withdrawal->getAccountNumber() 
                    : $this->maskAccountNumber($withdrawal->getAccountNumber()),
                'account_holder_name' => $withdrawal->getAccountHolderName(),
            ],
            'admin_notes' => $withdrawal->admin_notes,
            'can_be_processed' => $withdrawal->canBeProcessed(),
            'is_approved' => $withdrawal->isApproved(),
            'processed_at' => $withdrawal->processed_at?->toIso8601String(),
            'processed_by' => $withdrawal->processedBy ? [
                'id' => $withdrawal->processedBy->id,
                'name' => $withdrawal->processedBy->name,
            ] : null,
            'created_at' => $withdrawal->created_at->toIso8601String(),
            'updated_at' => $withdrawal->updated_at->toIso8601String(),
        ];

        // Include sub-merchant details
        if ($withdrawal->subMerchant) {
            $response['sub_merchant'] = [
                'id' => $withdrawal->subMerchant->id,
                'is_active' => $withdrawal->subMerchant->is_active,
                'user' => $withdrawal->subMerchant->user ? [
                    'id' => $withdrawal->subMerchant->user->id,
                    'name' => $withdrawal->subMerchant->user->name,
                    'email' => $withdrawal->subMerchant->user->email,
                ] : null,
                'balance' => $withdrawal->subMerchant->balance ? [
                    'available' => (float) $withdrawal->subMerchant->balance->available_balance,
                    'pending' => (float) $withdrawal->subMerchant->balance->pending_balance,
                    'total_earned' => (float) $withdrawal->subMerchant->balance->total_earned,
                    'total_withdrawn' => (float) $withdrawal->subMerchant->balance->total_withdrawn,
                ] : null,
            ];
        }

        return $response;
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
