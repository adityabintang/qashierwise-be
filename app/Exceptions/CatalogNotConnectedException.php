<?php

namespace App\Exceptions;

use Exception;

class CatalogNotConnectedException extends Exception
{
    /**
     * The error code for API responses.
     */
    public const ERROR_CODE = 'CATALOG_NOT_CONNECTED';

    /**
     * Create a new exception instance.
     */
    public function __construct(
        string $message = 'Please connect your Meta catalog first.',
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
