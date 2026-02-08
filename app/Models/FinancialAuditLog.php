<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialAuditLog extends Model
{
    use HasFactory;

    /**
     * Action type constants.
     */
    public const ACTION_BALANCE_UPDATE = 'balance_update';

    public const ACTION_BALANCE_PAYMENT = 'balance_payment';

    public const ACTION_BALANCE_REFUND = 'balance_refund';

    public const ACTION_BALANCE_ADJUSTMENT = 'balance_adjustment';

    public const ACTION_WITHDRAWAL_REQUEST = 'withdrawal_request';

    public const ACTION_WITHDRAWAL_APPROVAL = 'withdrawal_approval';

    public const ACTION_WITHDRAWAL_REJECTION = 'withdrawal_rejection';

    public const ACTION_WITHDRAWAL_PROCESSED = 'withdrawal_processed';

    public const ACTION_WITHDRAWAL_CANCELLED = 'withdrawal_cancelled';

    public const ACTION_QRIS_GENERATED = 'qris_generated';

    public const ACTION_QRIS_SETTLED = 'qris_settled';

    public const ACTION_QRIS_EXPIRED = 'qris_expired';

    public const ACTION_QRIS_CANCELLED = 'qris_cancelled';

    public const ACTION_FEE_COLLECTED = 'fee_collected';

    public const ACTION_FEE_CALCULATED = 'fee_calculated';

    /**
     * Action category constants.
     */
    public const CATEGORY_BALANCE = 'balance';

    public const CATEGORY_WITHDRAWAL = 'withdrawal';

    public const CATEGORY_TRANSACTION = 'transaction';

    public const CATEGORY_FEE = 'fee';

    /**
     * Status constants.
     */
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PENDING = 'pending';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sub_merchant_id',
        'user_id',
        'admin_id',
        'action_type',
        'action_category',
        'reference_type',
        'reference_id',
        'reference_code',
        'amount',
        'fee_amount',
        'balance_before',
        'balance_after',
        'status',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the sub-merchant associated with this audit log.
     */
    public function subMerchant(): BelongsTo
    {
        return $this->belongsTo(SubMerchant::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who performed the action (for admin actions).
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Get the referenced model (polymorphic).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    /**
     * Scope to filter by sub-merchant.
     */
    public function scopeForSubMerchant($query, int $subMerchantId)
    {
        return $query->where('sub_merchant_id', $subMerchantId);
    }

    /**
     * Scope to filter by action type.
     */
    public function scopeOfActionType($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    /**
     * Scope to filter by action category.
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('action_category', $category);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get balance-related logs.
     */
    public function scopeBalanceLogs($query)
    {
        return $query->where('action_category', self::CATEGORY_BALANCE);
    }

    /**
     * Scope to get withdrawal-related logs.
     */
    public function scopeWithdrawalLogs($query)
    {
        return $query->where('action_category', self::CATEGORY_WITHDRAWAL);
    }

    /**
     * Scope to get transaction-related logs.
     */
    public function scopeTransactionLogs($query)
    {
        return $query->where('action_category', self::CATEGORY_TRANSACTION);
    }

    /**
     * Scope to get fee-related logs.
     */
    public function scopeFeeLogs($query)
    {
        return $query->where('action_category', self::CATEGORY_FEE);
    }

    /**
     * Get the balance change amount.
     */
    public function getBalanceChange(): ?float
    {
        if ($this->balance_before === null || $this->balance_after === null) {
            return null;
        }

        return (float) $this->balance_after - (float) $this->balance_before;
    }

    /**
     * Check if this is a successful action.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if this is a failed action.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Get a human-readable description of the action.
     */
    public function getActionDescription(): string
    {
        return match ($this->action_type) {
            self::ACTION_BALANCE_UPDATE => 'Balance updated',
            self::ACTION_BALANCE_PAYMENT => 'Payment received',
            self::ACTION_BALANCE_REFUND => 'Balance refunded',
            self::ACTION_BALANCE_ADJUSTMENT => 'Balance adjusted',
            self::ACTION_WITHDRAWAL_REQUEST => 'Withdrawal requested',
            self::ACTION_WITHDRAWAL_APPROVAL => 'Withdrawal approved',
            self::ACTION_WITHDRAWAL_REJECTION => 'Withdrawal rejected',
            self::ACTION_WITHDRAWAL_PROCESSED => 'Withdrawal processed',
            self::ACTION_WITHDRAWAL_CANCELLED => 'Withdrawal cancelled',
            self::ACTION_QRIS_GENERATED => 'QRIS generated',
            self::ACTION_QRIS_SETTLED => 'QRIS payment settled',
            self::ACTION_QRIS_EXPIRED => 'QRIS expired',
            self::ACTION_QRIS_CANCELLED => 'QRIS cancelled',
            self::ACTION_FEE_COLLECTED => 'Platform fee collected',
            self::ACTION_FEE_CALCULATED => 'Platform fee calculated',
            default => $this->action_type,
        };
    }

    /**
     * Get formatted amount with currency.
     */
    public function getFormattedAmount(): string
    {
        if ($this->amount === null) {
            return '-';
        }

        return 'Rp '.number_format((float) $this->amount, 0, ',', '.');
    }

    /**
     * Get formatted fee amount with currency.
     */
    public function getFormattedFeeAmount(): string
    {
        if ($this->fee_amount === null) {
            return '-';
        }

        return 'Rp '.number_format((float) $this->fee_amount, 0, ',', '.');
    }
}
