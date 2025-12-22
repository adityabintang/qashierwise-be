<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    use HasFactory;

    /**
     * Withdrawal status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PROCESSED = 'processed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sub_merchant_id',
        'amount',
        'status',
        'bank_details',
        'admin_notes',
        'processed_at',
        'processed_by',
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
            'bank_details' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the sub-merchant that owns this withdrawal request.
     */
    public function subMerchant(): BelongsTo
    {
        return $this->belongsTo(SubMerchant::class);
    }

    /**
     * Get the admin user who processed this request.
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Check if the withdrawal request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the withdrawal request is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the withdrawal request is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if the withdrawal request is processed.
     */
    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    /**
     * Check if the withdrawal request can be processed by admin.
     */
    public function canBeProcessed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the withdrawal request can be cancelled by the merchant.
     */
    public function canBeCancelled(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Mark the withdrawal as approved.
     */
    public function approve(User $admin, ?string $notes = null): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->processed_by = $admin->id;
        $this->processed_at = now();
        
        if ($notes !== null) {
            $this->admin_notes = $notes;
        }
    }

    /**
     * Mark the withdrawal as rejected.
     */
    public function reject(User $admin, string $reason): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->processed_by = $admin->id;
        $this->processed_at = now();
        $this->admin_notes = $reason;
    }

    /**
     * Mark the withdrawal as processed (bank transfer completed).
     */
    public function markAsProcessed(): void
    {
        $this->status = self::STATUS_PROCESSED;
        
        if ($this->processed_at === null) {
            $this->processed_at = now();
        }
    }

    /**
     * Get the bank name from bank details.
     */
    public function getBankName(): ?string
    {
        return $this->bank_details['bank_name'] ?? null;
    }

    /**
     * Get the account number from bank details.
     */
    public function getAccountNumber(): ?string
    {
        return $this->bank_details['account_number'] ?? null;
    }

    /**
     * Get the account holder name from bank details.
     */
    public function getAccountHolderName(): ?string
    {
        return $this->bank_details['account_holder_name'] ?? null;
    }

    /**
     * Scope to get pending withdrawal requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get approved withdrawal requests.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to get rejected withdrawal requests.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope to get processed withdrawal requests.
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }
}
