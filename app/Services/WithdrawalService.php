<?php

namespace App\Services;

use App\Models\MerchantBalance;
use App\Models\SubMerchant;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WithdrawalService
{
    /**
     * Minimum withdrawal amount in IDR (Rp 10,000).
     */
    public const MINIMUM_WITHDRAWAL = 10000;

    protected BalanceService $balanceService;
    protected ?WithdrawalNotificationService $notificationService;
    protected ?FinancialAuditService $auditService = null;

    public function __construct(BalanceService $balanceService, ?WithdrawalNotificationService $notificationService = null)
    {
        $this->balanceService = $balanceService;
        $this->notificationService = $notificationService;
    }

    /**
     * Set the notification service.
     *
     * @param WithdrawalNotificationService $notificationService
     * @return void
     */
    public function setNotificationService(WithdrawalNotificationService $notificationService): void
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Set the audit service for logging.
     */
    public function setAuditService(FinancialAuditService $auditService): self
    {
        $this->auditService = $auditService;
        return $this;
    }

    /**
     * Create a new withdrawal request with validation and balance deduction.
     *
     * @param SubMerchant $merchant The sub-merchant requesting withdrawal
     * @param float $amount Amount to withdraw
     * @param bool $sendNotifications Whether to send notifications (default: true)
     * @return WithdrawalRequest The created withdrawal request
     * @throws InvalidArgumentException If validation fails
     */
    public function createWithdrawalRequest(SubMerchant $merchant, float $amount, bool $sendNotifications = true): WithdrawalRequest
    {
        // Validate the withdrawal request
        $validationErrors = $this->validateWithdrawal($merchant, $amount);
        if (!empty($validationErrors)) {
            $errorMessage = implode(', ', $validationErrors);
            throw new InvalidArgumentException("Withdrawal validation failed: {$errorMessage}");
        }

        $withdrawalRequest = DB::transaction(function () use ($merchant, $amount) {
            // Lock the balance record to prevent race conditions
            $balance = MerchantBalance::lockForUpdate()->find($merchant->balance->id);
            
            // Capture balance before for audit
            $balanceBefore = (float) $balance->available_balance;

            // Double-check balance after locking
            if (!$balance->hasSufficientBalance($amount)) {
                throw new InvalidArgumentException('Insufficient balance after lock');
            }

            // Deduct the amount from available balance
            $balance->deductForWithdrawal($amount);
            $balance->save();
            
            // Capture balance after for audit
            $balanceAfter = (float) $balance->available_balance;

            // Create the withdrawal request with pending status
            $withdrawalRequest = WithdrawalRequest::create([
                'sub_merchant_id' => $merchant->id,
                'amount' => $amount,
                'status' => WithdrawalRequest::STATUS_PENDING,
                'bank_details' => [
                    'bank_name' => $merchant->bank_name,
                    'account_number' => $merchant->account_number,
                    'account_holder_name' => $merchant->account_holder_name,
                ],
            ]);

            Log::info('Withdrawal request created', [
                'withdrawal_id' => $withdrawalRequest->id,
                'sub_merchant_id' => $merchant->id,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'new_available_balance' => $balanceAfter,
            ]);

            // Log to financial audit trail
            if ($this->auditService !== null) {
                $this->auditService->logWithdrawalRequest(
                    $merchant,
                    $withdrawalRequest,
                    $balanceBefore,
                    $balanceAfter
                );
            }

            return $withdrawalRequest;
        });

        // Send notifications to admins (outside transaction)
        if ($sendNotifications && $this->notificationService !== null) {
            $this->notificationService->sendNewRequestNotifications($withdrawalRequest);
        }

        return $withdrawalRequest;
    }

    /**
     * Validate a withdrawal request.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @param float $amount Amount to withdraw
     * @return array<string, string> Validation errors (empty if valid)
     */
    public function validateWithdrawal(SubMerchant $merchant, float $amount): array
    {
        $errors = [];

        // Check if merchant is active
        if (!$merchant->is_active) {
            $errors['merchant'] = 'Sub-merchant account is not active';
        }

        // Check minimum withdrawal amount
        if ($amount < self::MINIMUM_WITHDRAWAL) {
            $errors['amount'] = 'Minimum withdrawal amount is Rp ' . number_format(self::MINIMUM_WITHDRAWAL, 0, ',', '.');
        }

        // Check if amount is positive
        if ($amount <= 0) {
            $errors['amount'] = 'Withdrawal amount must be greater than zero';
        }

        // Check balance
        $balance = $merchant->balance;
        if ($balance === null) {
            $errors['balance'] = 'No balance record found for this merchant';
            return $errors;
        }

        // Check sufficient balance
        if (!$balance->hasSufficientBalance($amount)) {
            $errors['balance'] = 'Insufficient available balance. Available: Rp ' . 
                number_format($balance->available_balance, 0, ',', '.');
        }

        // Check for pending withdrawals that might affect available balance
        $pendingWithdrawals = $merchant->withdrawalRequests()
            ->where('status', WithdrawalRequest::STATUS_PENDING)
            ->sum('amount');

        if ($pendingWithdrawals > 0) {
            // Note: Balance is already deducted when withdrawal is created,
            // so this is just informational
            Log::info('Merchant has pending withdrawals', [
                'sub_merchant_id' => $merchant->id,
                'pending_amount' => $pendingWithdrawals,
            ]);
        }

        return $errors;
    }

    /**
     * Check if a withdrawal is valid without returning detailed errors.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @param float $amount Amount to withdraw
     * @return bool True if withdrawal is valid
     */
    public function isValidWithdrawal(SubMerchant $merchant, float $amount): bool
    {
        return empty($this->validateWithdrawal($merchant, $amount));
    }

    /**
     * Approve a withdrawal request.
     *
     * @param WithdrawalRequest $request The withdrawal request to approve
     * @param User $admin The admin approving the request
     * @param string|null $notes Optional admin notes
     * @param bool $sendNotifications Whether to send notifications (default: true)
     * @return WithdrawalRequest The updated withdrawal request
     * @throws InvalidArgumentException If request cannot be approved
     */
    public function approveWithdrawal(WithdrawalRequest $request, User $admin, ?string $notes = null, bool $sendNotifications = true): WithdrawalRequest
    {
        if (!$request->canBeProcessed()) {
            throw new InvalidArgumentException('Withdrawal request cannot be approved in its current status');
        }

        $withdrawalRequest = DB::transaction(function () use ($request, $admin, $notes) {
            // Lock the request to prevent concurrent modifications
            $request = WithdrawalRequest::lockForUpdate()->find($request->id);

            // Double-check status after locking
            if (!$request->canBeProcessed()) {
                throw new InvalidArgumentException('Withdrawal request status changed during processing');
            }

            // Approve the request
            $request->approve($admin, $notes);
            $request->save();

            Log::info('Withdrawal request approved', [
                'withdrawal_id' => $request->id,
                'sub_merchant_id' => $request->sub_merchant_id,
                'amount' => $request->amount,
                'admin_id' => $admin->id,
            ]);

            // Log to financial audit trail
            if ($this->auditService !== null) {
                $this->auditService->logWithdrawalApproval($request, $admin, $notes);
            }

            return $request->fresh();
        });

        // Send notification to merchant (outside transaction)
        if ($sendNotifications && $this->notificationService !== null) {
            $this->notificationService->sendApprovalNotifications($withdrawalRequest);
        }

        return $withdrawalRequest;
    }

    /**
     * Reject a withdrawal request and return the amount to merchant balance.
     *
     * @param WithdrawalRequest $request The withdrawal request to reject
     * @param User $admin The admin rejecting the request
     * @param string $reason Reason for rejection
     * @param bool $sendNotifications Whether to send notifications (default: true)
     * @return WithdrawalRequest The updated withdrawal request
     * @throws InvalidArgumentException If request cannot be rejected
     */
    public function rejectWithdrawal(WithdrawalRequest $request, User $admin, string $reason, bool $sendNotifications = true): WithdrawalRequest
    {
        if (!$request->canBeProcessed()) {
            throw new InvalidArgumentException('Withdrawal request cannot be rejected in its current status');
        }

        if (empty(trim($reason))) {
            throw new InvalidArgumentException('Rejection reason is required');
        }

        $withdrawalRequest = DB::transaction(function () use ($request, $admin, $reason) {
            // Lock the request and balance to prevent concurrent modifications
            $request = WithdrawalRequest::lockForUpdate()->find($request->id);
            $merchant = $request->subMerchant;
            $balance = MerchantBalance::lockForUpdate()->find($merchant->balance->id);
            
            // Capture balance before for audit
            $balanceBefore = (float) $balance->available_balance;

            // Double-check status after locking
            if (!$request->canBeProcessed()) {
                throw new InvalidArgumentException('Withdrawal request status changed during processing');
            }

            // Return the amount to merchant's available balance
            $balance->returnToAvailable((float) $request->amount);
            $balance->save();
            
            // Capture balance after for audit
            $balanceAfter = (float) $balance->available_balance;

            // Reject the request
            $request->reject($admin, $reason);
            $request->save();

            Log::info('Withdrawal request rejected', [
                'withdrawal_id' => $request->id,
                'sub_merchant_id' => $request->sub_merchant_id,
                'amount' => $request->amount,
                'admin_id' => $admin->id,
                'reason' => $reason,
                'balance_before' => $balanceBefore,
                'new_available_balance' => $balanceAfter,
            ]);

            // Log to financial audit trail
            if ($this->auditService !== null) {
                $this->auditService->logWithdrawalRejection(
                    $request,
                    $admin,
                    $reason,
                    $balanceBefore,
                    $balanceAfter
                );
            }

            return $request->fresh();
        });

        // Send notification to merchant (outside transaction)
        if ($sendNotifications && $this->notificationService !== null) {
            $this->notificationService->sendRejectionNotifications($withdrawalRequest);
        }

        return $withdrawalRequest;
    }

    /**
     * Mark a withdrawal as processed (bank transfer completed).
     *
     * @param WithdrawalRequest $request The withdrawal request to mark as processed
     * @param bool $sendNotifications Whether to send notifications (default: true)
     * @return WithdrawalRequest The updated withdrawal request
     * @throws InvalidArgumentException If request cannot be processed
     */
    public function markAsProcessed(WithdrawalRequest $request, bool $sendNotifications = true): WithdrawalRequest
    {
        if (!$request->isApproved()) {
            throw new InvalidArgumentException('Only approved withdrawals can be marked as processed');
        }

        $withdrawalRequest = DB::transaction(function () use ($request) {
            $request = WithdrawalRequest::lockForUpdate()->find($request->id);

            if (!$request->isApproved()) {
                throw new InvalidArgumentException('Withdrawal status changed during processing');
            }

            $request->markAsProcessed();
            $request->save();

            Log::info('Withdrawal request processed', [
                'withdrawal_id' => $request->id,
                'sub_merchant_id' => $request->sub_merchant_id,
                'amount' => $request->amount,
            ]);

            // Log to financial audit trail
            if ($this->auditService !== null) {
                $this->auditService->logWithdrawalProcessed($request);
            }

            return $request->fresh();
        });

        // Send notification to merchant (outside transaction)
        if ($sendNotifications && $this->notificationService !== null) {
            $this->notificationService->sendProcessedNotifications($withdrawalRequest);
        }

        return $withdrawalRequest;
    }

    /**
     * Cancel a pending withdrawal request (by merchant).
     *
     * @param WithdrawalRequest $request The withdrawal request to cancel
     * @return WithdrawalRequest The updated withdrawal request
     * @throws InvalidArgumentException If request cannot be cancelled
     */
    public function cancelWithdrawal(WithdrawalRequest $request): WithdrawalRequest
    {
        if (!$request->canBeCancelled()) {
            throw new InvalidArgumentException('Withdrawal request cannot be cancelled in its current status');
        }

        return DB::transaction(function () use ($request) {
            $request = WithdrawalRequest::lockForUpdate()->find($request->id);
            $merchant = $request->subMerchant;
            $balance = MerchantBalance::lockForUpdate()->find($merchant->balance->id);
            
            // Capture balance before for audit
            $balanceBefore = (float) $balance->available_balance;

            if (!$request->canBeCancelled()) {
                throw new InvalidArgumentException('Withdrawal request status changed during processing');
            }

            // Return the amount to merchant's available balance
            $balance->returnToAvailable((float) $request->amount);
            $balance->save();
            
            // Capture balance after for audit
            $balanceAfter = (float) $balance->available_balance;

            // Update status to rejected with cancellation note
            $request->status = WithdrawalRequest::STATUS_REJECTED;
            $request->admin_notes = 'Cancelled by merchant';
            $request->processed_at = now();
            $request->save();

            Log::info('Withdrawal request cancelled by merchant', [
                'withdrawal_id' => $request->id,
                'sub_merchant_id' => $request->sub_merchant_id,
                'amount' => $request->amount,
                'balance_before' => $balanceBefore,
                'new_available_balance' => $balanceAfter,
            ]);

            // Log to financial audit trail
            if ($this->auditService !== null) {
                $this->auditService->logWithdrawalCancellation(
                    $request,
                    $balanceBefore,
                    $balanceAfter
                );
            }

            return $request->fresh();
        });
    }

    /**
     * Get all pending withdrawal requests for admin review.
     *
     * @param int $limit Number of requests to return
     * @return Collection Pending withdrawal requests
     */
    public function getPendingWithdrawals(int $limit = 50): Collection
    {
        return WithdrawalRequest::with(['subMerchant.user', 'subMerchant.balance'])
            ->pending()
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get withdrawal history for a sub-merchant.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @param int $limit Number of requests to return
     * @return Collection Withdrawal requests
     */
    public function getWithdrawalHistory(SubMerchant $merchant, int $limit = 50): Collection
    {
        return $merchant->withdrawalRequests()
            ->with('processedBy')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get withdrawal statistics for a sub-merchant.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @return array Withdrawal statistics
     */
    public function getWithdrawalStats(SubMerchant $merchant): array
    {
        $requests = $merchant->withdrawalRequests;

        return [
            'total_requests' => $requests->count(),
            'pending_count' => $requests->where('status', WithdrawalRequest::STATUS_PENDING)->count(),
            'approved_count' => $requests->where('status', WithdrawalRequest::STATUS_APPROVED)->count(),
            'rejected_count' => $requests->where('status', WithdrawalRequest::STATUS_REJECTED)->count(),
            'processed_count' => $requests->where('status', WithdrawalRequest::STATUS_PROCESSED)->count(),
            'total_withdrawn' => (float) $requests->whereIn('status', [
                WithdrawalRequest::STATUS_APPROVED,
                WithdrawalRequest::STATUS_PROCESSED,
            ])->sum('amount'),
            'pending_amount' => (float) $requests->where('status', WithdrawalRequest::STATUS_PENDING)->sum('amount'),
        ];
    }

    /**
     * Get a withdrawal request by ID with validation.
     *
     * @param int $id Withdrawal request ID
     * @param SubMerchant|null $merchant Optional merchant for ownership validation
     * @return WithdrawalRequest|null The withdrawal request or null if not found
     */
    public function getWithdrawalById(int $id, ?SubMerchant $merchant = null): ?WithdrawalRequest
    {
        $query = WithdrawalRequest::with(['subMerchant.user', 'processedBy']);

        if ($merchant !== null) {
            $query->where('sub_merchant_id', $merchant->id);
        }

        return $query->find($id);
    }
}
