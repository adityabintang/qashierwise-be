<?php

namespace App\DTOs;

/**
 * Data Transfer Object for transaction status information.
 */
class TransactionStatus
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SETTLEMENT = 'settlement';

    public const STATUS_EXPIRE = 'expire';

    public const STATUS_CANCEL = 'cancel';

    public const STATUS_FAILED = 'failed';

    public function __construct(
        public readonly string $status,
        public readonly string $transactionId,
        public readonly ?float $amount = null,
        public readonly ?\DateTimeInterface $settledAt = null,
        public readonly ?array $metadata = null
    ) {}

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
     * Check if the transaction is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRE;
    }

    /**
     * Check if the transaction is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCEL;
    }

    /**
     * Check if the transaction failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Convert to array format.
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'settled_at' => $this->settledAt?->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
        ];
    }
}
