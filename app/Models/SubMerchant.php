<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubMerchant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'business_name',
        'is_active',
        'verified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this sub-merchant.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the balance for this sub-merchant.
     */
    public function balance(): HasOne
    {
        return $this->hasOne(MerchantBalance::class);
    }

    /**
     * Get the QRIS transactions for this sub-merchant.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(QrisTransaction::class);
    }

    /**
     * Check if the sub-merchant is verified.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Check if the sub-merchant can accept payments.
     * Now only requires active status (no bank account needed).
     */
    public function canAcceptPayments(): bool
    {
        return $this->is_active;
    }
}
