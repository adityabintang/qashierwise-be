<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class QrisTransaction extends Model
{
    use HasFactory;

    /**
     * Transaction status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SETTLEMENT = 'settlement';
    public const STATUS_EXPIRE = 'expire';
    public const STATUS_CANCEL = 'cancel';

    /**
     * Payment provider constants.
     */
    public const PROVIDER_DOKU = 'doku';
    public const PROVIDER_XENDIT = 'xendit';
    public const PROVIDER_MIDTRANS = 'midtrans';
    public const PROVIDER_DUITKU = 'duitku';

    /**
     * Platform fee percentage (2.5%).
     */
    public const PLATFORM_FEE_PERCENTAGE = 2.5;

    /**
     * Default QRIS expiration time in minutes.
     */
    public const DEFAULT_EXPIRY_MINUTES = 30;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sub_merchant_id',
        'linked_order_id',
        'order_id',
        'amount',
        'platform_fee',
        'net_amount',
        'status',
        'provider',
        'provider_transaction_id',
        'midtrans_transaction_id',
        'qr_code_url',
        'expires_at',
        'settled_at',
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
            'platform_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * Get the sub-merchant that owns this transaction.
     */
    public function subMerchant(): BelongsTo
    {
        return $this->belongsTo(SubMerchant::class);
    }

    /**
     * Get the order associated with this QRIS transaction.
     */
    public function linkedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'linked_order_id');
    }

    /**
     * Get the payment associated with this QRIS transaction.
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Get the platform fee record for this transaction.
     */
    public function platformFee(): HasOne
    {
        return $this->hasOne(PlatformFee::class);
    }

    /**
     * Generate a unique order ID.
     */
    public static function generateOrderId(): string
    {
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(Str::random(8));
        return "QRIS-{$timestamp}-{$random}";
    }

    /**
     * Calculate platform fee for a given amount.
     */
    public static function calculatePlatformFee(float $amount): float
    {
        return round($amount * (self::PLATFORM_FEE_PERCENTAGE / 100), 2);
    }

    /**
     * Calculate net amount after platform fee deduction.
     */
    public static function calculateNetAmount(float $amount): float
    {
        return round($amount - self::calculatePlatformFee($amount), 2);
    }

    /**
     * Check if the transaction is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }
        
        return now()->isAfter($this->expires_at);
    }

    /**
     * Check if the transaction can be used for payment.
     */
    public function canBeUsed(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->isExpired();
    }

    /**
     * Check if the transaction is settled.
     */
    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLEMENT;
    }

    /**
     * Check if the transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the transaction is cancelled or expired.
     */
    public function isCancelledOrExpired(): bool
    {
        return in_array($this->status, [self::STATUS_CANCEL, self::STATUS_EXPIRE]);
    }

    /**
     * Generate a shareable link for this QRIS transaction.
     */
    public function getShareableLink(): string
    {
        return url("/pay/qris/{$this->order_id}");
    }

    /**
     * Mark the transaction as settled.
     */
    public function markAsSettled(?string $midtransTransactionId = null): void
    {
        $this->status = self::STATUS_SETTLEMENT;
        $this->settled_at = now();
        
        if ($midtransTransactionId !== null) {
            $this->midtrans_transaction_id = $midtransTransactionId;
        }
    }

    /**
     * Mark the transaction as expired.
     */
    public function markAsExpired(): void
    {
        $this->status = self::STATUS_EXPIRE;
    }

    /**
     * Mark the transaction as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->status = self::STATUS_CANCEL;
    }

    /**
     * Get the remaining time until expiration in seconds.
     */
    public function getRemainingTimeInSeconds(): int
    {
        if ($this->expires_at === null || $this->isExpired()) {
            return 0;
        }
        
        return (int) now()->diffInSeconds($this->expires_at, false);
    }

    /**
     * Scope to get pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get settled transactions.
     */
    public function scopeSettled($query)
    {
        return $query->where('status', self::STATUS_SETTLEMENT);
    }

    /**
     * Scope to get expired transactions that need status update.
     */
    public function scopeExpiredPending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('expires_at', '<', now());
    }

    /**
     * Scope to filter transactions by provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Get the provider name for display.
     */
    public function getProviderDisplayName(): string
    {
        return match($this->provider) {
            self::PROVIDER_DOKU => 'Doku',
            self::PROVIDER_XENDIT => 'Xendit',
            self::PROVIDER_MIDTRANS => 'Midtrans',
            self::PROVIDER_DUITKU => 'Duitku',
            default => 'Unknown',
        };
    }
}
