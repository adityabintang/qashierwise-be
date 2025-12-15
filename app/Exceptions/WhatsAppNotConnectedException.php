<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when a user attempts to use WhatsApp features
 * without having a connected WhatsApp account.
 */
class WhatsAppNotConnectedException extends Exception
{
    /**
     * The error code for API responses.
     */
    public const ERROR_CODE = 'WHATSAPP_NOT_CONNECTED';

    /**
     * Create a new exception instance.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'Please connect your WhatsApp Business account first.',
        int $code = 400,
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
