<?php

namespace App\DTOs;

/**
 * Standardized error response DTO.
 *
 * Provides a consistent structure for error responses across the application.
 * Ensures sensitive information is never exposed to clients.
 */
class ErrorResponse
{
    /**
     * Create a new error response instance.
     *
     * @param  string  $message  User-friendly error message
     * @param  string  $code  Error code for programmatic handling
     * @param  int  $statusCode  HTTP status code
     * @param  array|null  $details  Additional error details (optional)
     * @param  string|null  $provider  Provider name if applicable
     */
    public function __construct(
        public readonly string $message,
        public readonly string $code,
        public readonly int $statusCode = 500,
        public readonly ?array $details = null,
        public readonly ?string $provider = null
    ) {}

    /**
     * Convert to array for JSON response.
     */
    public function toArray(): array
    {
        $response = [
            'error' => $this->message,
            'code' => $this->code,
        ];

        if ($this->provider !== null) {
            $response['provider'] = $this->provider;
        }

        if ($this->details !== null && ! empty($this->details)) {
            $response['details'] = $this->details;
        }

        return $response;
    }

    /**
     * Create an error response from a ProviderException.
     */
    public static function fromProviderException(\App\Exceptions\ProviderException $exception): self
    {
        return new self(
            message: $exception->getUserMessage(),
            code: $exception->getErrorCode(),
            statusCode: $exception->getCode(),
            details: $exception->getProviderErrorCode() ? [
                'provider_error_code' => $exception->getProviderErrorCode(),
            ] : null,
            provider: $exception->getProviderName()
        );
    }

    /**
     * Create a network error response.
     */
    public static function networkError(?string $provider = null): self
    {
        return new self(
            message: 'Unable to connect to the payment provider. Please check your internet connection and try again.',
            code: 'PROVIDER_NETWORK_ERROR',
            statusCode: 503,
            provider: $provider
        );
    }

    /**
     * Create a credential error response.
     */
    public static function credentialError(?string $provider = null): self
    {
        return new self(
            message: 'Invalid payment provider credentials. Please verify your API keys and try again.',
            code: 'PROVIDER_CREDENTIAL_ERROR',
            statusCode: 401,
            provider: $provider
        );
    }

    /**
     * Create a validation error response.
     */
    public static function validationError(string $message, ?array $details = null): self
    {
        return new self(
            message: $message,
            code: 'VALIDATION_ERROR',
            statusCode: 422,
            details: $details
        );
    }

    /**
     * Create a not found error response.
     */
    public static function notFound(string $resource): self
    {
        return new self(
            message: "{$resource} not found",
            code: 'NOT_FOUND',
            statusCode: 404
        );
    }

    /**
     * Create an unauthorized error response.
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self(
            message: $message,
            code: 'UNAUTHORIZED',
            statusCode: 401
        );
    }

    /**
     * Create a forbidden error response.
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self(
            message: $message,
            code: 'FORBIDDEN',
            statusCode: 403
        );
    }

    /**
     * Create a generic server error response.
     */
    public static function serverError(string $message = 'An unexpected error occurred'): self
    {
        return new self(
            message: $message,
            code: 'SERVER_ERROR',
            statusCode: 500
        );
    }
}
