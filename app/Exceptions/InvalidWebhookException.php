<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when webhook signature verification fails or webhook data is invalid.
 */
class InvalidWebhookException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(
        string $message = 'Invalid webhook signature or data',
        private ?string $providerName = null,
        int $code = 400,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the provider name.
     */
    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    /**
     * Get a user-friendly error message.
     */
    public function getUserMessage(): string
    {
        return 'The webhook request could not be verified. Please check your webhook configuration.';
    }

    /**
     * Get the error code for API responses.
     */
    public function getErrorCode(): string
    {
        return 'INVALID_WEBHOOK';
    }

    /**
     * Create an invalid signature exception.
     */
    public static function invalidSignature(string $providerName): self
    {
        return new self(
            "Invalid webhook signature from {$providerName}",
            $providerName,
            401
        );
    }

    /**
     * Create an invalid payload exception.
     */
    public static function invalidPayload(string $providerName, string $reason): self
    {
        return new self(
            "Invalid webhook payload from {$providerName}: {$reason}",
            $providerName,
            400
        );
    }
}
