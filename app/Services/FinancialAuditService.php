<?php

namespace App\Services;

use App\Models\FinancialAuditLog;
use App\Models\PlatformFee;
use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FinancialAuditService
{
    /**
     * Current request instance for capturing context.
     */
    protected ?Request $request = null;

    /**
     * Set the current request for context capture.
     */
    public function setRequest(?Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Log a balance update action.
     */
    public function logBalanceUpdate(
        SubMerchant $merchant,
        float $amount,
        string $type,
        float $balanceBefore,
        float $balanceAfter,
        ?string $reference = null,
        ?array $metadata = null
    ): FinancialAuditLog {
        $actionType = match ($type) {
            'payment' => FinancialAuditLog::ACTION_BALANCE_PAYMENT,
            'refund' => FinancialAuditLog::ACTION_BALANCE_REFUND,
            'adjustment' => FinancialAuditLog::ACTION_BALANCE_ADJUSTMENT,
            default => FinancialAuditLog::ACTION_BALANCE_UPDATE,
        };

        return $this->createLog([
            'sub_merchant_id' => $merchant->id,
            'user_id' => $merchant->user_id,
            'action_type' => $actionType,
            'action_category' => FinancialAuditLog::CATEGORY_BALANCE,
            'reference_code' => $reference,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => FinancialAuditLog::STATUS_SUCCESS,
            'description' => "Balance {$type}: ".$this->formatAmount($amount),
            'metadata' => array_merge($metadata ?? [], [
                'update_type' => $type,
            ]),
        ]);
    }

    /**
     * Log a fee calculation action.
     */
    public function logFeeCalculation(
        SubMerchant $merchant,
        QrisTransaction $transaction,
        float $feeAmount,
        float $feePercentage
    ): FinancialAuditLog {
        return $this->createLog([
            'sub_merchant_id' => $merchant->id,
            'user_id' => $merchant->user_id,
            'action_type' => FinancialAuditLog::ACTION_FEE_CALCULATED,
            'action_category' => FinancialAuditLog::CATEGORY_FEE,
            'reference_type' => QrisTransaction::class,
            'reference_id' => $transaction->id,
            'reference_code' => $transaction->order_id,
            'amount' => $transaction->amount,
            'fee_amount' => $feeAmount,
            'status' => FinancialAuditLog::STATUS_SUCCESS,
            'description' => "Platform fee calculated: {$feePercentage}% of ".$this->formatAmount($transaction->amount),
            'metadata' => [
                'gross_amount' => (float) $transaction->amount,
                'fee_percentage' => $feePercentage,
                'fee_amount' => $feeAmount,
                'net_amount' => (float) $transaction->net_amount,
            ],
        ]);
    }

    /**
     * Log a fee collection action.
     */
    public function logFeeCollection(
        SubMerchant $merchant,
        QrisTransaction $transaction,
        PlatformFee $platformFee
    ): FinancialAuditLog {
        return $this->createLog([
            'sub_merchant_id' => $merchant->id,
            'user_id' => $merchant->user_id,
            'action_type' => FinancialAuditLog::ACTION_FEE_COLLECTED,
            'action_category' => FinancialAuditLog::CATEGORY_FEE,
            'reference_type' => PlatformFee::class,
            'reference_id' => $platformFee->id,
            'reference_code' => $transaction->order_id,
            'amount' => $transaction->amount,
            'fee_amount' => $platformFee->fee_amount,
            'status' => FinancialAuditLog::STATUS_SUCCESS,
            'description' => 'Platform fee collected: '.$this->formatAmount($platformFee->fee_amount),
            'metadata' => [
                'transaction_id' => $transaction->id,
                'fee_percentage' => (float) $platformFee->fee_percentage,
            ],
        ]);
    }

    /**
     * Log a QRIS generation action.
     */
    public function logQrisGeneration(
        SubMerchant $merchant,
        QrisTransaction $transaction
    ): FinancialAuditLog {
        return $this->createLog([
            'sub_merchant_id' => $merchant->id,
            'user_id' => $merchant->user_id,
            'action_type' => FinancialAuditLog::ACTION_QRIS_GENERATED,
            'action_category' => FinancialAuditLog::CATEGORY_TRANSACTION,
            'reference_type' => QrisTransaction::class,
            'reference_id' => $transaction->id,
            'reference_code' => $transaction->order_id,
            'amount' => $transaction->amount,
            'fee_amount' => $transaction->platform_fee,
            'status' => FinancialAuditLog::STATUS_PENDING,
            'description' => 'QRIS generated: '.$this->formatAmount($transaction->amount),
            'metadata' => [
                'order_id' => $transaction->order_id,
                'expires_at' => $transaction->expires_at?->toIso8601String(),
                'platform_fee' => (float) $transaction->platform_fee,
                'net_amount' => (float) $transaction->net_amount,
            ],
        ]);
    }

    /**
     * Log a QRIS settlement action.
     */
    public function logQrisSettlement(
        SubMerchant $merchant,
        QrisTransaction $transaction,
        float $balanceBefore,
        float $balanceAfter
    ): FinancialAuditLog {
        return $this->createLog([
            'sub_merchant_id' => $merchant->id,
            'user_id' => $merchant->user_id,
            'action_type' => FinancialAuditLog::ACTION_QRIS_SETTLED,
            'action_category' => FinancialAuditLog::CATEGORY_TRANSACTION,
            'reference_type' => QrisTransaction::class,
            'reference_id' => $transaction->id,
            'reference_code' => $transaction->order_id,
            'amount' => $transaction->amount,
            'fee_amount' => $transaction->platform_fee,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => FinancialAuditLog::STATUS_SUCCESS,
            'description' => 'QRIS payment settled: '.$this->formatAmount($transaction->amount),
            'metadata' => [
                'order_id' => $transaction->order_id,
                'midtrans_transaction_id' => $transaction->midtrans_transaction_id,
                'gross_amount' => (float) $transaction->amount,
                'platform_fee' => (float) $transaction->platform_fee,
                'net_amount' => (float) $transaction->net_amount,
                'settled_at' => $transaction->settled_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Log a QRIS expiration action.
     */
    public function logQrisExpiration(
        SubMerchant $merchant,
        QrisTransaction $transaction
    ): FinancialAuditLog {
        return $this->createLog([
            'sub_merchant_id' => $merchant->id,
            'user_id' => $merchant->user_id,
            'action_type' => FinancialAuditLog::ACTION_QRIS_EXPIRED,
            'action_category' => FinancialAuditLog::CATEGORY_TRANSACTION,
            'reference_type' => QrisTransaction::class,
            'reference_id' => $transaction->id,
            'reference_code' => $transaction->order_id,
            'amount' => $transaction->amount,
            'status' => FinancialAuditLog::STATUS_FAILED,
            'description' => 'QRIS expired: '.$transaction->order_id,
            'metadata' => [
                'order_id' => $transaction->order_id,
                'expired_at' => $transaction->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Log a QRIS cancellation action.
     */
    public function logQrisCancellation(
        SubMerchant $merchant,
        QrisTransaction $transaction
    ): FinancialAuditLog {
        return $this->createLog([
            'sub_merchant_id' => $merchant->id,
            'user_id' => $merchant->user_id,
            'action_type' => FinancialAuditLog::ACTION_QRIS_CANCELLED,
            'action_category' => FinancialAuditLog::CATEGORY_TRANSACTION,
            'reference_type' => QrisTransaction::class,
            'reference_id' => $transaction->id,
            'reference_code' => $transaction->order_id,
            'amount' => $transaction->amount,
            'status' => FinancialAuditLog::STATUS_FAILED,
            'description' => 'QRIS cancelled: '.$transaction->order_id,
            'metadata' => [
                'order_id' => $transaction->order_id,
            ],
        ]);
    }

    /**
     * Get audit logs for a sub-merchant.
     */
    public function getLogsForMerchant(
        SubMerchant $merchant,
        ?string $category = null,
        ?int $limit = 50
    ): Collection {
        $query = FinancialAuditLog::forSubMerchant($merchant->id)
            ->orderBy('created_at', 'desc');

        if ($category !== null) {
            $query->ofCategory($category);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get audit logs for a specific QRIS transaction.
     */
    public function getLogsForTransaction(QrisTransaction $transaction): Collection
    {
        return FinancialAuditLog::where('reference_type', QrisTransaction::class)
            ->where('reference_id', $transaction->id)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get fee collection summary for a date range.
     */
    public function getFeeCollectionSummary(
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $query = FinancialAuditLog::feeLogs()
            ->ofActionType(FinancialAuditLog::ACTION_FEE_COLLECTED);

        if ($startDate !== null && $endDate !== null) {
            $query->betweenDates($startDate, $endDate);
        }

        $logs = $query->get();

        return [
            'total_fees_collected' => (float) $logs->sum('fee_amount'),
            'total_transactions' => $logs->count(),
            'total_transaction_amount' => (float) $logs->sum('amount'),
        ];
    }

    /**
     * Get audit trail summary for a sub-merchant.
     */
    public function getMerchantAuditSummary(SubMerchant $merchant): array
    {
        $logs = FinancialAuditLog::forSubMerchant($merchant->id)->get();

        return [
            'total_logs' => $logs->count(),
            'balance_updates' => $logs->where('action_category', FinancialAuditLog::CATEGORY_BALANCE)->count(),
            'transaction_actions' => $logs->where('action_category', FinancialAuditLog::CATEGORY_TRANSACTION)->count(),
            'fee_actions' => $logs->where('action_category', FinancialAuditLog::CATEGORY_FEE)->count(),
            'successful_actions' => $logs->where('status', FinancialAuditLog::STATUS_SUCCESS)->count(),
            'failed_actions' => $logs->where('status', FinancialAuditLog::STATUS_FAILED)->count(),
            'pending_actions' => $logs->where('status', FinancialAuditLog::STATUS_PENDING)->count(),
        ];
    }

    /**
     * Create an audit log entry.
     */
    protected function createLog(array $data): FinancialAuditLog
    {
        // Add request context if available
        if ($this->request !== null) {
            $data['ip_address'] = $this->request->ip();
            $data['user_agent'] = $this->request->userAgent();
        }

        try {
            $log = FinancialAuditLog::create($data);

            Log::debug('Financial audit log created', [
                'log_id' => $log->id,
                'action_type' => $log->action_type,
                'sub_merchant_id' => $log->sub_merchant_id,
            ]);

            return $log;
        } catch (\Exception $e) {
            Log::error('Failed to create financial audit log', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Format amount for display.
     */
    protected function formatAmount(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
