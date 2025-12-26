<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when Row Level Security policies are violated.
 * 
 * This exception is used to handle unauthorized access attempts to
 * data protected by RLS policies, providing generic error messages
 * to prevent information disclosure.
 */
class RLSViolationException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'Access denied', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get a user-friendly error message.
     */
    public function getUserMessage(): string
    {
        return 'You do not have permission to access this resource.';
    }

    /**
     * Get the error code for API responses.
     */
    public function getErrorCode(): string
    {
        return 'ACCESS_DENIED';
    }
}
