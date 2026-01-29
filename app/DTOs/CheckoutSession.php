<?php

namespace App\DTOs;

/**
 * Data Transfer Object for Polar.sh checkout session.
 *
 * Contains all information about a checkout session created
 * for subscription purchases.
 */
class CheckoutSession
{
    /**
     * Create a new CheckoutSession instance.
     *
     * @param  string  $id  Polar.sh checkout session ID
     * @param  string  $url  Checkout URL to redirect the user to
     * @param  string  $planId  Internal plan identifier
     * @param  string  $userEmail  Email of the user initiating checkout
     * @param  string  $successUrl  URL to redirect to on successful payment
     * @param  string  $cancelUrl  URL to redirect to on cancelled payment
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $planId,
        public readonly string $userEmail,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
    ) {}
}
