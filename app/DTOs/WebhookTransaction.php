<?php

namespace App\DTOs;

/**
 * Data Transfer Object for webhook transaction data.
 * 
 * Represents standardized transaction data parsed from provider webhooks.
 */
class WebhookTransaction
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $status,
        public readonly float $amount,
        public readonly ?string $paidAt,
        public readonly string $provider,
        public readonly ?string $referenceId = null,
        public readonly ?array $metadata = null
    ) {}

    /**
     * Convert to array format.
     */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'status' => $this->status,
            'amount' => $this->amount,
            'paid_at' => $this->paidAt,
            'provider' => $this->provider,
            'reference_id' => $this->referenceId,
            'metadata' => $this->metadata,
        ];
    }
}
