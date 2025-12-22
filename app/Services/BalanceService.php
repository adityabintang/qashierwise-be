<?php

namespace App\Services;

use App\Models\MerchantBalance;
use App\Models\PlatformFee;
use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class BalanceService
{
    /**
     * Platform fee percentage (2.5%).
     */
    public const PLATFORM_FEE_PERCENTAGE = 2.5;

    /**
     * Financial audit service for logging.
     */
    protected ?FinancialAuditService $auditService = null;

    /**
     * Set the audit service for logging.
     */
    public function setAuditService(FinancialAuditService $auditService): self
    {
        $this->auditService = $auditService;
        return $this;
    }

    /**
     * Update a sub-merchant's balance.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @param float $amount Amount to add (positive) or deduct (negative)
     * @param string $type Type of update ('payment', 'withdrawal', 'adjustment', 'refund')
     * @param string|null $reference Optional reference (e.g., order_id, withdrawal_id)
     * @return MerchantBalance Updated balance
     * @throws InvalidArgumentException If operation would result in negative balance
     */
    public function updateBalance(SubMerchant $merchant, float $amount, string $type, ?string $reference = null): MerchantBalance
    {
        $balance = $merchant->balance;
        
        if ($balance === null) {
            throw new InvalidArgumentException('Sub-merchant has no balance record');
        }

        return DB::transaction(function () use ($balance, $amount, $type, $reference, $merchant) {
            // Lock the balance record for update
            $balance = MerchantBalance::lockForUpdate()->find($balance->id);
            
            // Capture balance before update for audit
            $balanceBefore = (float) $balance->available_balance;

            switch ($type) {
                case 'payment':
                    // Add to available balance from successful payment
                    if ($amount < 0) {
                        throw new InvalidArgumentException('Payment amount must be positive');
                    }
                    $balance->addToAvailable($amount);
                    break;

                case 'withdrawal':
                    // Deduct from available balance for withdrawal
                    if ($amount < 0) {
                        throw new InvalidArgumentException('Withdrawal amount must be positive');
                    }
                    if (!$balance->hasSufficientBalance($amount)) {
                        throw new InvalidArgumentException('Insufficient balance for withdrawal');
                    }
                    $balance->deductForWithdrawal($amount);
                    break;

                case 'refund':
                    // Return amount to available balance (e.g., rejected withdrawal)
                    if ($amount < 0) {
                        throw new InvalidArgumentException('Refund amount must be positive');
                    }
                    $balance->returnToAvailable($amount);
                    break;

                case 'adjustment':
                    // Manual adjustment (can be positive or negative)
                    if ($amount >= 0) {
                        $balance->available_balance = (float) $balance->available_balance + $amount;
                    } else {
                        $newBalance = (float) $balance->available_balance + $amount;
                        if ($newBalance < 0) {
                            throw new InvalidArgumentException('Adjustment would result in negative balance');
                        }
                        $balance->available_balance = $newBalance;
                    }
                    $balance->last_updated = now();
                    break;

                default:
                    throw new InvalidArgumentException("Invalid balance update type: {$type}");
            }

            $balance->save();
            
            // Capture balance after update for audit
            $balanceAfter = (float) $balance->available_balance;

            Log::info('Balance updated', [
                'sub_merchant_id' => $merchant->id,
                'type' => $type,
                'amount' => $amount,
                'reference' => $reference,
                'balance_before' => $balanceBefore,
                'new_available_balance' => $balanceAfter,
            ]);

            // Log to financial audit trail
            if ($this->auditService !== null) {
                $this->auditService->logBalanceUpdate(
                    $merchant,
                    $amount,
                    $type,
                    $balanceBefore,
                    $balanceAfter,
                    $reference
                );
            }

            return $balance->fresh();
        });
    }

    /**
     * Calculate platform fee for a given amount.
     *
     * @param float $amount Transaction amount
     * @return float Platform fee amount
     */
    public function calculatePlatformFee(float $amount): float
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount must be non-negative');
        }

        return round($amount * (self::PLATFORM_FEE_PERCENTAGE / 100), 2);
    }

    /**
     * Calculate net amount after platform fee deduction.
     *
     * @param float $amount Gross transaction amount
     * @return float Net amount (gross - platform fee)
     */
    public function calculateNetAmount(float $amount): float
    {
        return round($amount - $this->calculatePlatformFee($amount), 2);
    }

    /**
     * Process a successful payment and update merchant balance.
     *
     * @param QrisTransaction $transaction The settled transaction
     * @return MerchantBalance Updated balance
     * @throws InvalidArgumentException If transaction is not settled
     */
    public function processPaymentSuccess(QrisTransaction $transaction): MerchantBalance
    {
        if (!$transaction->isSettled()) {
            throw new InvalidArgumentException('Transaction must be settled to process payment');
        }

        $merchant = $transaction->subMerchant;
        if ($merchant === null) {
            throw new InvalidArgumentException('Transaction has no associated sub-merchant');
        }

        return DB::transaction(function () use ($transaction, $merchant) {
            // Get the net amount (already calculated when transaction was created)
            $netAmount = (float) $transaction->net_amount;
            
            // Capture balance before for audit
            $balanceBefore = (float) ($merchant->balance?->available_balance ?? 0);

            // Update merchant balance
            $balance = $this->updateBalance($merchant, $netAmount, 'payment', $transaction->order_id);

            // Create platform fee record
            $platformFee = PlatformFee::createForTransaction($transaction);

            Log::info('Payment processed successfully', [
                'order_id' => $transaction->order_id,
                'sub_merchant_id' => $merchant->id,
                'gross_amount' => $transaction->amount,
                'platform_fee' => $transaction->platform_fee,
                'net_amount' => $netAmount,
            ]);

            // Log fee calculation and collection to audit trail
            if ($this->auditService !== null) {
                $this->auditService->logFeeCalculation(
                    $merchant,
                    $transaction,
                    (float) $transaction->platform_fee,
                    self::PLATFORM_FEE_PERCENTAGE
                );
                
                $this->auditService->logFeeCollection(
                    $merchant,
                    $transaction,
                    $platformFee
                );
                
                $this->auditService->logQrisSettlement(
                    $merchant,
                    $transaction,
                    $balanceBefore,
                    (float) $balance->available_balance
                );
            }

            return $balance;
        });
    }

    /**
     * Get balance history/breakdown for a sub-merchant.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @return array Balance breakdown
     */
    public function getBalanceHistory(SubMerchant $merchant): array
    {
        $balance = $merchant->balance;
        
        if ($balance === null) {
            return [
                'available_balance' => 0,
                'pending_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'total_balance' => 0,
            ];
        }

        return [
            'available_balance' => (float) $balance->available_balance,
            'pending_balance' => (float) $balance->pending_balance,
            'total_earned' => (float) $balance->total_earned,
            'total_withdrawn' => (float) $balance->total_withdrawn,
            'total_balance' => $balance->getTotalBalance(),
            'last_updated' => $balance->last_updated,
        ];
    }

    /**
     * Get transaction history with fee breakdown for a sub-merchant.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @param int $limit Number of transactions to return
     * @return Collection Transaction history with fee details
     */
    public function getTransactionHistoryWithFees(SubMerchant $merchant, int $limit = 50): Collection
    {
        return $merchant->transactions()
            ->with('platformFee')
            ->where('status', QrisTransaction::STATUS_SETTLEMENT)
            ->orderBy('settled_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($transaction) {
                return [
                    'order_id' => $transaction->order_id,
                    'gross_amount' => (float) $transaction->amount,
                    'platform_fee' => (float) $transaction->platform_fee,
                    'net_amount' => (float) $transaction->net_amount,
                    'settled_at' => $transaction->settled_at,
                    'fee_percentage' => $transaction->platformFee?->fee_percentage ?? self::PLATFORM_FEE_PERCENTAGE,
                ];
            });
    }

    /**
     * Get total earnings for a sub-merchant within a date range.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @param \DateTimeInterface|null $startDate Start date (optional)
     * @param \DateTimeInterface|null $endDate End date (optional)
     * @return array Earnings summary
     */
    public function getEarningsSummary(SubMerchant $merchant, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $query = $merchant->transactions()
            ->where('status', QrisTransaction::STATUS_SETTLEMENT);

        if ($startDate !== null) {
            $query->where('settled_at', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->where('settled_at', '<=', $endDate);
        }

        $transactions = $query->get();

        return [
            'transaction_count' => $transactions->count(),
            'gross_earnings' => (float) $transactions->sum('amount'),
            'total_fees' => (float) $transactions->sum('platform_fee'),
            'net_earnings' => (float) $transactions->sum('net_amount'),
        ];
    }

    /**
     * Validate that a balance operation won't result in negative balance.
     *
     * @param MerchantBalance $balance The balance to check
     * @param float $amount Amount to deduct
     * @return bool True if operation is valid
     */
    public function validateBalanceOperation(MerchantBalance $balance, float $amount): bool
    {
        if ($amount <= 0) {
            return true; // Adding or zero amount is always valid
        }

        return $balance->hasSufficientBalance($amount);
    }

    /**
     * Get the current available balance for a sub-merchant.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @return float Available balance
     */
    public function getAvailableBalance(SubMerchant $merchant): float
    {
        return (float) ($merchant->balance?->available_balance ?? 0);
    }

    /**
     * Check if a sub-merchant can withdraw a specific amount.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @param float $amount Amount to withdraw
     * @return bool True if withdrawal is possible
     */
    public function canWithdraw(SubMerchant $merchant, float $amount): bool
    {
        $balance = $merchant->balance;
        
        if ($balance === null) {
            return false;
        }

        return $balance->canWithdraw($amount);
    }

    /**
     * Get withdrawal validation errors.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @param float $amount Amount to withdraw
     * @return array<string, string> Validation errors (empty if valid)
     */
    public function getWithdrawalValidationErrors(SubMerchant $merchant, float $amount): array
    {
        $errors = [];
        $balance = $merchant->balance;

        if ($balance === null) {
            $errors['balance'] = 'No balance record found';
            return $errors;
        }

        if (!$balance->meetsMinimumWithdrawal($amount)) {
            $errors['amount'] = 'Minimum withdrawal amount is Rp ' . number_format(MerchantBalance::MINIMUM_WITHDRAWAL, 0, ',', '.');
        }

        if (!$balance->hasSufficientBalance($amount)) {
            $errors['balance'] = 'Insufficient available balance';
        }

        return $errors;
    }
}
