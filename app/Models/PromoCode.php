<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'max_uses',
        'used_count',
        'valid_from',
        'valid_until',
        'applicable_plans',
        'min_purchase',
        'is_active',
        'description',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'applicable_plans' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get usages for this promo code.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    /**
     * Check if promo code is valid.
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Check if expired
        if ($this->valid_until && now()->isAfter($this->valid_until)) {
            return false;
        }

        // Check if not yet valid
        if ($this->valid_from && now()->isBefore($this->valid_from)) {
            return false;
        }

        // Check max uses
        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Check if promo code is applicable to a plan.
     */
    public function isApplicableToPlan(string $planId): bool
    {
        if (! $this->applicable_plans) {
            return true; // Applicable to all plans
        }

        return in_array($planId, $this->applicable_plans);
    }

    /**
     * Calculate discount amount.
     */
    public function calculateDiscount(float $amount): float
    {
        if ($this->type === 'percentage') {
            return round($amount * ($this->value / 100), 2);
        }

        // Fixed amount
        return min($this->value, $amount); // Don't exceed original amount
    }

    /**
     * Calculate final amount after discount.
     */
    public function calculateFinalAmount(float $amount): float
    {
        $discount = $this->calculateDiscount($amount);

        return max(0, $amount - $discount);
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
