<?php

namespace App\DTOs;

/**
 * Data Transfer Object for QRIS generation requests.
 */
class QrisRequest
{
    public function __construct(
        public readonly string $orderId,
        public readonly float $amount,
        public readonly ?string $description = null,
        public readonly ?int $expiryMinutes = null,
        public readonly ?array $metadata = null
    ) {}

    /**
     * Convert to array format.
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'amount' => $this->amount,
            'description' => $this->description,
            'expiry_minutes' => $this->expiryMinutes,
            'metadata' => $this->metadata,
        ];
    }
}
