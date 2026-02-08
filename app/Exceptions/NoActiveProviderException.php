<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when no active payment provider is configured.
 */
class NoActiveProviderException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'No active payment provider configured', int $code = 400)
    {
        parent::__construct($message, $code);
    }

    /**
     * Get a user-friendly error message.
     */
    public function getUserMessage(): string
    {
        return 'Please configure and activate a payment provider before generating QRIS codes.';
    }

    /**
     * Get the error code for API responses.
     */
    public function getErrorCode(): string
    {
        return 'NO_ACTIVE_PROVIDER';
    }
}
