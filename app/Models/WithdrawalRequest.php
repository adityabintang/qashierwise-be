<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sub_merchant_id',
        'amount',
        'bank_code',
        'bank_account_number',
        'bank_account_name',
        'status',
        'xendit_payout_id',
        'reference_id',
        'completed_at',
        'failure_reason',
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
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the sub-merchant that owns this withdrawal request.
     */
    public function subMerchant(): BelongsTo
    {
        return $this->belongsTo(SubMerchant::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function markAsProcessing(string $xenditPayoutId): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->xendit_payout_id = $xenditPayoutId;
    }

    public function markAsCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
    }

    public function markAsFailed(string $reason): void
    {
        $this->status = self::STATUS_FAILED;
        $this->failure_reason = $reason;
    }

    /**
     * Generate a unique reference ID for this withdrawal.
     */
    public static function generateReferenceId(): string
    {
        return 'WD-'.now()->format('YmdHis').'-'.strtoupper(substr(uniqid(), -6));
    }
}
