<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when a user's WhatsApp access token is invalid.
 */
class WhatsAppTokenInvalidException extends Exception
{
    /**
     * The error code for API responses.
     */
    public const ERROR_CODE = 'WHATSAPP_TOKEN_INVALID';

    /**
     * Create a new exception instance.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'Your WhatsApp access token is invalid. Please re-authenticate.',
        int $code = 401,
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
