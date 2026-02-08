<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when payment provider operations fail.
 *
 * This exception classifies provider errors into network errors,
 * credential errors, and other provider-specific errors.
 */
class ProviderException extends Exception
{
    public const TYPE_NETWORK = 'network';

    public const TYPE_CREDENTIAL = 'credential';

    public const TYPE_PROVIDER = 'provider';

    public const TYPE_UNKNOWN = 'unknown';

    /**
     * Create a new exception instance.
     */
    public function __construct(
        string $message,
        private string $errorType = self::TYPE_UNKNOWN,
        private ?string $providerName = null,
        private ?string $providerErrorCode = null,
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the error type (network, credential, provider, unknown).
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * Get the provider name.
     */
    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    /**
     * Get the provider-specific error code.
     */
    public function getProviderErrorCode(): ?string
    {
        return $this->providerErrorCode;
    }

    /**
     * Check if this is a network error.
     */
    public function isNetworkError(): bool
    {
        return $this->errorType === self::TYPE_NETWORK;
    }

    /**
     * Check if this is a credential error.
     */
    public function isCredentialError(): bool
    {
        return $this->errorType === self::TYPE_CREDENTIAL;
    }

    /**
     * Get a user-friendly error message.
     */
    public function getUserMessage(): string
    {
        return match ($this->errorType) {
            self::TYPE_NETWORK => 'Unable to connect to the payment provider. Please check your internet connection and try again.',
            self::TYPE_CREDENTIAL => 'Invalid payment provider credentials. Please verify your API keys and try again.',
            self::TYPE_PROVIDER => $this->message,
            default => 'An error occurred while processing your request. Please try again later.',
        };
    }

    /**
     * Get the error code for API responses.
     */
    public function getErrorCode(): string
    {
        return match ($this->errorType) {
            self::TYPE_NETWORK => 'PROVIDER_NETWORK_ERROR',
            self::TYPE_CREDENTIAL => 'PROVIDER_CREDENTIAL_ERROR',
            self::TYPE_PROVIDER => 'PROVIDER_ERROR',
            default => 'PROVIDER_UNKNOWN_ERROR',
        };
    }

    /**
     * Create a network error exception.
     */
    public static function networkError(string $providerName, ?\Throwable $previous = null): self
    {
        return new self(
            "Network error connecting to {$providerName}",
            self::TYPE_NETWORK,
            $providerName,
            null,
            503,
            $previous
        );
    }

    /**
     * Create a credential error exception.
     */
    public static function credentialError(string $providerName, string $message, ?string $errorCode = null): self
    {
        return new self(
            $message,
            self::TYPE_CREDENTIAL,
            $providerName,
            $errorCode,
            401
        );
    }

    /**
     * Create a provider error exception.
     */
    public static function providerError(string $providerName, string $message, ?string $errorCode = null): self
    {
        return new self(
            $message,
            self::TYPE_PROVIDER,
            $providerName,
            $errorCode,
            400
        );
    }
}
