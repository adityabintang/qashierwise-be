<?php

namespace App\DTOs;

/**
 * Data Transfer Object for credential validation results.
 */
class ValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly ?string $errorMessage = null,
        public readonly ?string $errorCode = null,
        public readonly ?array $metadata = null
    ) {}

    /**
     * Create a successful validation result.
     */
    public static function success(?array $metadata = null): self
    {
        return new self(
            isValid: true,
            errorMessage: null,
            errorCode: null,
            metadata: $metadata
        );
    }

    /**
     * Create a failed validation result.
     */
    public static function failure(
        string $errorMessage,
        ?string $errorCode = null,
        ?array $metadata = null
    ): self {
        return new self(
            isValid: false,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
            metadata: $metadata
        );
    }
}
