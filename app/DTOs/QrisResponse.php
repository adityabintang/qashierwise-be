<?php

namespace App\DTOs;

/**
 * Data Transfer Object for QRIS generation responses.
 */
class QrisResponse
{
    public function __construct(
        public readonly string $qrCodeUrl,
        public readonly string $providerTransactionId,
        public readonly string $orderId,
        public readonly float $amount,
        public readonly ?\DateTimeInterface $expiresAt = null,
        public readonly ?array $metadata = null
    ) {}

    /**
     * Convert to array format.
     */
    public function toArray(): array
    {
        return [
            'qr_code_url' => $this->qrCodeUrl,
            'provider_transaction_id' => $this->providerTransactionId,
            'order_id' => $this->orderId,
            'amount' => $this->amount,
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
        ];
    }
}
