<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantBalance extends Model
{
    use HasFactory;

    /**
     * Minimum withdrawal amount in IDR (Rp 10,000).
     */
    public const MINIMUM_WITHDRAWAL = 10000;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sub_merchant_id',
        'available_balance',
        'pending_balance',
        'total_earned',
        'total_withdrawn',
        'last_updated',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_balance' => 'decimal:2',
            'pending_balance' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
            'last_updated' => 'datetime',
        ];
    }

    /**
     * Get the sub-merchant that owns this balance.
     */
    public function subMerchant(): BelongsTo
    {
        return $this->belongsTo(SubMerchant::class);
    }

    /**
     * Check if the merchant can withdraw the specified amount.
     */
    public function canWithdraw(float $amount): bool
    {
        return $amount >= self::MINIMUM_WITHDRAWAL
            && $amount <= (float) $this->available_balance;
    }

    /**
     * Check if the merchant has sufficient balance for withdrawal.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $amount <= (float) $this->available_balance;
    }

    /**
     * Check if the amount meets minimum withdrawal requirement.
     */
    public function meetsMinimumWithdrawal(float $amount): bool
    {
        return $amount >= self::MINIMUM_WITHDRAWAL;
    }

    /**
     * Get the total balance (available + pending).
     */
    public function getTotalBalance(): float
    {
        return (float) $this->available_balance + (float) $this->pending_balance;
    }

    /**
     * Add to available balance.
     */
    public function addToAvailable(float $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount must be non-negative');
        }

        $this->available_balance = (float) $this->available_balance + $amount;
        $this->total_earned = (float) $this->total_earned + $amount;
        $this->last_updated = now();
    }

    /**
     * Add to pending balance.
     */
    public function addToPending(float $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount must be non-negative');
        }

        $this->pending_balance = (float) $this->pending_balance + $amount;
        $this->last_updated = now();
    }

    /**
     * Move amount from pending to available.
     */
    public function settlePending(float $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount must be non-negative');
        }

        if ($amount > (float) $this->pending_balance) {
            throw new \InvalidArgumentException('Insufficient pending balance');
        }

        $this->pending_balance = (float) $this->pending_balance - $amount;
        $this->available_balance = (float) $this->available_balance + $amount;
        $this->last_updated = now();
    }

    /**
     * Deduct from available balance for withdrawal.
     */
    public function deductForWithdrawal(float $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount must be non-negative');
        }

        if ($amount > (float) $this->available_balance) {
            throw new \InvalidArgumentException('Insufficient available balance');
        }

        $this->available_balance = (float) $this->available_balance - $amount;
        $this->total_withdrawn = (float) $this->total_withdrawn + $amount;
        $this->last_updated = now();
    }

    /**
     * Return amount to available balance (for rejected withdrawals).
     */
    public function returnToAvailable(float $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount must be non-negative');
        }

        $this->available_balance = (float) $this->available_balance + $amount;
        $this->total_withdrawn = (float) $this->total_withdrawn - $amount;
        $this->last_updated = now();
    }

    /**
     * Validate that balance is non-negative.
     */
    public function isValid(): bool
    {
        return (float) $this->available_balance >= 0
            && (float) $this->pending_balance >= 0;
    }
}
