<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when an unsupported payment provider is requested.
 */
class UnsupportedProviderException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $provider)
    {
        parent::__construct("Unsupported payment provider: {$provider}");
    }

    /**
     * Get the list of supported providers.
     */
    public static function getSupportedProviders(): array
    {
        return ['doku', 'xendit', 'midtrans', 'duitku'];
    }
}
