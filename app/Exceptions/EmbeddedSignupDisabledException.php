<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when the Embedded Signup feature is disabled
 * due to missing configuration.
 */
class EmbeddedSignupDisabledException extends Exception
{
    /**
     * The error code for API responses.
     */
    public const ERROR_CODE = 'EMBEDDED_SIGNUP_DISABLED';

    /**
     * Create a new exception instance.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'Embedded Signup feature is disabled. Please contact the administrator.',
        int $code = 503,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the error code for API responses.
     */
    public function getErrorCode(): string
    {
        return self::ERROR_CODE;
    }
}
