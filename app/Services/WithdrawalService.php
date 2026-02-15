<?php

namespace App\Services;

use App\Models\SubMerchant;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class WithdrawalService
{
    protected ?FinancialAuditService $auditService = null;

    public function __construct(
        private XenPlatformService $xenPlatformService,
        private BalanceService $balanceService,
    ) {}

    /**
     * Set the audit service for logging.
     */
    public function setAuditService(FinancialAuditService $auditService): self
    {
        $this->auditService = $auditService;

        return $this;
    }

    /**
     * Request a withdrawal for a sub-merchant.
     *
     * @throws InvalidArgumentException If validation fails
     * @throws RuntimeException If payout creation fails
     */
    public function requestWithdrawal(SubMerchant $merchant, float $amount): WithdrawalRequest
    {
        // Validate merchant has bank account
        if (! $merchant->hasBankAccount()) {
            throw new InvalidArgumentException('Please configure your bank account before requesting a withdrawal.');
        }

        // Validate merchant has XenPlatform account
        if (! $merchant->hasXenditAccount()) {
            throw new InvalidArgumentException('XenPlatform account is not active. Please contact support.');
        }

        // Validate withdrawal amount
        $validationErrors = $this->balanceService->getWithdrawalValidationErrors($merchant, $amount);
        if (! empty($validationErrors)) {
            throw new InvalidArgumentException(implode(' ', $validationErrors));
        }

        $referenceId = WithdrawalRequest::generateReferenceId();

        return DB::transaction(function () use ($merchant, $amount, $referenceId) {
            // Create withdrawal request record
            $withdrawal = WithdrawalRequest::create([
                'sub_merchant_id' => $merchant->id,
                'amount' => $amount,
                'bank_code' => $merchant->bank_code,
                'bank_account_number' => $merchant->bank_account_number,
                'bank_account_name' => $merchant->bank_account_name,
                'status' => WithdrawalRequest::STATUS_PENDING,
                'reference_id' => $referenceId,
            ]);

            // Deduct from local balance immediately
            $this->balanceService->updateBalance($merchant, $amount, 'withdrawal', $referenceId);

            Log::info('Withdrawal requested', [
                'sub_merchant_id' => $merchant->id,
                'amount' => $amount,
                'reference_id' => $referenceId,
            ]);

            // Create payout via XenPlatform
            try {
                $payoutResult = $this->xenPlatformService->createPayout(
                    $merchant->xendit_account_id,
                    $amount,
                    [
                        'bank_code' => $merchant->bank_code,
                        'bank_account_number' => $merchant->bank_account_number,
                        'bank_account_name' => $merchant->bank_account_name,
                    ],
                    $referenceId
                );

                $withdrawal->markAsProcessing($payoutResult['id']);
                $withdrawal->save();

                Log::info('Payout created via XenPlatform', [
                    'withdrawal_id' => $withdrawal->id,
                    'xendit_payout_id' => $payoutResult['id'],
                ]);

            } catch (\Exception $e) {
                // If payout fails, return balance and mark as failed
                $this->balanceService->updateBalance($merchant, $amount, 'refund', $referenceId);

                $withdrawal->markAsFailed($e->getMessage());
                $withdrawal->save();

                Log::error('Payout creation failed, balance returned', [
                    'withdrawal_id' => $withdrawal->id,
                    'error' => $e->getMessage(),
                ]);

                throw new RuntimeException("Withdrawal failed: {$e->getMessage()}");
            }

            return $withdrawal;
        });
    }

    /**
     * Handle a payout webhook notification from Xendit.
     */
    public function handlePayoutWebhook(array $payload): void
    {
        // Payouts API v2 wraps payload in { event, data: {...} }
        // Unwrap if 'data' key exists (v2 format), otherwise use flat payload (v1)
        $payoutData = $payload['data'] ?? $payload;

        $referenceId = $payoutData['reference_id'] ?? null;
        $payoutId = $payoutData['id'] ?? null;
        $status = $payoutData['status'] ?? null;

        Log::info('Processing payout webhook', [
            'event' => $payload['event'] ?? 'unknown',
            'reference_id' => $referenceId,
            'payout_id' => $payoutId,
            'status' => $status,
        ]);

        // Find withdrawal by payout ID or reference ID
        $withdrawal = null;
        if ($payoutId) {
            $withdrawal = WithdrawalRequest::where('xendit_payout_id', $payoutId)->first();
        }
        if (! $withdrawal && $referenceId) {
            $withdrawal = WithdrawalRequest::where('reference_id', $referenceId)->first();
        }

        if (! $withdrawal) {
            Log::warning('Withdrawal not found for payout webhook', [
                'reference_id' => $referenceId,
                'payout_id' => $payoutId,
            ]);

            return;
        }

        // Skip if already in final state
        if ($withdrawal->isCompleted() || $withdrawal->isFailed()) {
            Log::info('Withdrawal already in final state', [
                'withdrawal_id' => $withdrawal->id,
                'status' => $withdrawal->status,
            ]);

            return;
        }

        $xenditStatus = strtoupper($status ?? '');

        if (in_array($xenditStatus, ['SUCCEEDED', 'COMPLETED'])) {
            $withdrawal->markAsCompleted();
            $withdrawal->save();

            Log::info('Withdrawal completed', [
                'withdrawal_id' => $withdrawal->id,
            ]);

        } elseif (in_array($xenditStatus, ['FAILED', 'VOIDED', 'CANCELLED', 'REVERSED'])) {
            $failureReason = $payoutData['failure_code'] ?? $payoutData['error_code'] ?? 'Payout failed';

            $withdrawal->markAsFailed($failureReason);
            $withdrawal->save();

            // Return balance to merchant
            $merchant = $withdrawal->subMerchant;
            if ($merchant) {
                $this->balanceService->updateBalance(
                    $merchant,
                    (float) $withdrawal->amount,
                    'refund',
                    $withdrawal->reference_id
                );

                Log::info('Balance returned after failed payout', [
                    'withdrawal_id' => $withdrawal->id,
                    'amount' => $withdrawal->amount,
                ]);
            }
        }
    }

    /**
     * Get withdrawal history for a sub-merchant.
     */
    public function getWithdrawalHistory(SubMerchant $merchant, int $limit = 50): Collection
    {
        return $merchant->withdrawalRequests()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find a withdrawal request by ID for a specific merchant.
     */
    public function findForMerchant(SubMerchant $merchant, int $withdrawalId): ?WithdrawalRequest
    {
        return $merchant->withdrawalRequests()->find($withdrawalId);
    }
}
