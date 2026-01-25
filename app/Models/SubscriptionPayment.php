<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for subscription payment history.
 *
 * Records each payment transaction for subscription billing history.
 */
class SubscriptionPayment extends Model
{
    /**
     * Payment status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_SETTLEMENT = 'settlement';

    public const STATUS_CAPTURE = 'capture';

    public const STATUS_DENY = 'deny';

    public const STATUS_CANCEL = 'cancel';

    public const STATUS_EXPIRE = 'expire';

    public const STATUS_REFUND = 'refund';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
        'user_id',
        'order_id',
        'transaction_id',
        'plan_name',
        'duration',
        'gross_amount',
        'currency',
        'payment_type',
        'status',
        'transaction_time',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'transaction_time' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the subscription that this payment belongs to.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the user that made this payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if payment is successful.
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, [self::STATUS_SETTLEMENT, self::STATUS_CAPTURE]);
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payment failed.
     */
    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_DENY, self::STATUS_CANCEL, self::STATUS_EXPIRE]);
    }
}
