<?php

namespace App\Services\PaymentProviders;

use App\Contracts\PaymentProviderInterface;
use App\Exceptions\UnsupportedProviderException;

/**
 * Factory for creating payment provider instances.
 * 
 * This factory provides a centralized way to instantiate payment providers
 * based on the provider name.
 */
class ProviderFactory
{
    /**
     * Create a payment provider instance.
     *
     * @param string $provider Provider name (doku, xendit, midtrans, duitku)
     * @return PaymentProviderInterface
     * @throws UnsupportedProviderException
     */
    public function make(string $provider): PaymentProviderInterface
    {
        return match (strtolower($provider)) {
            'doku' => app(DokuProvider::class),
            'xendit' => app(XenditProvider::class),
            'midtrans' => app(MidtransProvider::class),
            'duitku' => app(DuitkuProvider::class),
            default => throw new UnsupportedProviderException($provider),
        };
    }

    /**
     * Get list of all supported providers.
     *
     * @return array
     */
    public function getSupportedProviders(): array
    {
        return ['doku', 'xendit', 'midtrans', 'duitku'];
    }

    /**
     * Check if a provider is supported.
     *
     * @param string $provider
     * @return bool
     */
    public function isSupported(string $provider): bool
    {
        return in_array(strtolower($provider), $this->getSupportedProviders());
    }
}
