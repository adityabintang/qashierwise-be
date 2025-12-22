<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformFee extends Model
{
    use HasFactory;

    /**
     * Default platform fee percentage (2.5%).
     */
    public const DEFAULT_FEE_PERCENTAGE = 2.5;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'qris_transaction_id',
        'fee_percentage',
        'fee_amount',
        'collected_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fee_percentage' => 'decimal:4',
            'fee_amount' => 'decimal:2',
            'collected_at' => 'datetime',
        ];
    }

    /**
     * Get the QRIS transaction that this fee belongs to.
     */
    public function qrisTransaction(): BelongsTo
    {
        return $this->belongsTo(QrisTransaction::class);
    }

    /**
     * Calculate fee amount for a given transaction amount.
     */
    public static function calculateFeeAmount(float $transactionAmount, ?float $feePercentage = null): float
    {
        $percentage = $feePercentage ?? self::DEFAULT_FEE_PERCENTAGE;
        return round($transactionAmount * ($percentage / 100), 2);
    }

    /**
     * Create a platform fee record for a transaction.
     */
    public static function createForTransaction(QrisTransaction $transaction, ?float $feePercentage = null): self
    {
        $percentage = $feePercentage ?? self::DEFAULT_FEE_PERCENTAGE;
        $feeAmount = self::calculateFeeAmount((float) $transaction->amount, $percentage);

        return self::create([
            'qris_transaction_id' => $transaction->id,
            'fee_percentage' => $percentage,
            'fee_amount' => $feeAmount,
            'collected_at' => now(),
        ]);
    }

    /**
     * Get the effective fee rate as a decimal (e.g., 0.025 for 2.5%).
     */
    public function getEffectiveRate(): float
    {
        return (float) $this->fee_percentage / 100;
    }

    /**
     * Validate that the fee amount matches the expected calculation.
     */
    public function isValidFeeAmount(float $transactionAmount): bool
    {
        $expectedFee = self::calculateFeeAmount($transactionAmount, (float) $this->fee_percentage);
        return abs((float) $this->fee_amount - $expectedFee) < 0.01; // Allow for rounding differences
    }

    /**
     * Scope to get fees collected within a date range.
     */
    public function scopeCollectedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('collected_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get fees collected today.
     */
    public function scopeCollectedToday($query)
    {
        return $query->whereDate('collected_at', today());
    }

    /**
     * Get total fees collected for a given period.
     */
    public static function getTotalFeesCollected($startDate = null, $endDate = null): float
    {
        $query = self::query();
        
        if ($startDate !== null && $endDate !== null) {
            $query->collectedBetween($startDate, $endDate);
        }
        
        return (float) $query->sum('fee_amount');
    }
}
