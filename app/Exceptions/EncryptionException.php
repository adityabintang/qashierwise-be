<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when encryption or decryption operations fail.
 * 
 * This exception is used to wrap encryption-related errors and provide
 * sanitized error messages to users without exposing technical details.
 */
class EncryptionException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'Encryption operation failed', int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get a user-friendly error message.
     */
    public function getUserMessage(): string
    {
        return 'Unable to process your credentials securely. Please try again or contact support if the issue persists.';
    }

    /**
     * Get the error code for API responses.
     */
    public function getErrorCode(): string
    {
        return 'ENCRYPTION_ERROR';
    }
}
