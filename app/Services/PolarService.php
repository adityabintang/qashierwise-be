<?php

namespace App\Services;

use App\DTOs\CheckoutSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Polar\Models\Components\CheckoutCreate;
use Polar\Models\Components\CustomerSessionCustomerIDCreate;
use Polar\Polar;

/**
 * Service for communicating with Polar.sh API.
 * 
 * Handles checkout session creation, customer portal access,
 * webhook signature validation, and subscription retrieval.
 */
class PolarService
{
    private ?Polar $client = null;
    private PlanConfig $planConfig;

    public function __construct(PlanConfig $planConfig)
    {
        $this->planConfig = $planConfig;
    }

    /**
     * Get the Polar SDK client instance.
     * 
     * @return Polar|null
     */
    protected function getClient(): ?Polar
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $apiToken = config('polar.api_token');
        
        if (empty($apiToken)) {
            Log::error('Polar.sh API token is not configured');
            return null;
        }

        try {
            $builder = Polar::builder()
                ->setSecurity($apiToken);
            
            // Use sandbox server if configured
            if (config('polar.sandbox', false)) {
                $builder->setServer('sandbox');
            }
            
            $this->client = $builder->build();
            
            return $this->client;
        } catch (\Exception $e) {
            Log::error('Failed to initialize Polar.sh client', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a checkout session for a user and plan.
     * 
     * @param User $user The user initiating checkout
     * @param string $planId The internal plan identifier (standard, pro)
     * @return CheckoutSession|null
     */
    public function createCheckoutSession(User $user, string $planId): ?CheckoutSession
    {
        $client = $this->getClient();
        
        if ($client === null) {
            Log::error('Cannot create checkout session: Polar client not available');
            return null;
        }

        $polarProductId = $this->planConfig->getPolarProductId($planId);
        
        if ($polarProductId === null) {
            Log::error('Invalid plan ID for checkout', ['planId' => $planId]);
            return null;
        }

        $successUrl = config('polar.urls.success');
        $cancelUrl = config('polar.urls.cancel');

        try {
            $checkoutCreate = new CheckoutCreate(
                products: [$polarProductId],
                customerEmail: $user->email,
                customerName: $user->name,
                successUrl: $successUrl,
                metadata: [
                    'user_id' => (string) $user->id,
                    'plan_id' => $planId,
                ],
            );

            $response = $client->checkouts->create($checkoutCreate);
            
            if ($response->checkout === null) {
                Log::error('Checkout session creation returned null');
                return null;
            }

            $checkout = $response->checkout;

            return new CheckoutSession(
                id: $checkout->id,
                url: $checkout->url,
                planId: $planId,
                userEmail: $user->email,
                successUrl: $checkout->successUrl,
                cancelUrl: $cancelUrl,
            );
        } catch (\Exception $e) {
            Log::error('Failed to create checkout session', [
                'error' => $e->getMessage(),
                'userId' => $user->id,
                'planId' => $planId,
            ]);
            return null;
        }
    }

    /**
     * Get the customer portal URL for a customer.
     * 
     * @param string $customerId The Polar customer ID
     * @return string|null
     */
    public function getCustomerPortalUrl(string $customerId): ?string
    {
        $client = $this->getClient();
        
        if ($client === null) {
            Log::error('Cannot get customer portal URL: Polar client not available');
            return null;
        }

        try {
            $sessionCreate = new CustomerSessionCustomerIDCreate(
                customerId: $customerId,
            );

            $response = $client->customerSessions->create($sessionCreate);
            
            if ($response->customerSession === null) {
                Log::error('Customer session creation returned null');
                return null;
            }

            return $response->customerSession->customerPortalUrl;
        } catch (\Exception $e) {
            Log::error('Failed to get customer portal URL', [
                'error' => $e->getMessage(),
                'customerId' => $customerId,
            ]);
            return null;
        }
    }

    /**
     * Validate a webhook signature.
     * 
     * Polar.sh uses HMAC-SHA256 for webhook signature validation.
     * The signature is sent in the 'webhook-signature' header.
     * 
     * @param string $payload The raw webhook payload
     * @param string $signature The signature from the webhook header
     * @return bool
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $webhookSecret = config('polar.webhook_secret');
        
        if (empty($webhookSecret)) {
            Log::error('Webhook secret is not configured');
            return false;
        }

        if (empty($payload) || empty($signature)) {
            return false;
        }

        // Polar uses standard webhook signature format: t=timestamp,v1=signature
        // Parse the signature header
        $parts = explode(',', $signature);
        $timestamp = null;
        $signatureValue = null;

        foreach ($parts as $part) {
            $keyValue = explode('=', $part, 2);
            if (count($keyValue) === 2) {
                if ($keyValue[0] === 't') {
                    $timestamp = $keyValue[1];
                } elseif ($keyValue[0] === 'v1') {
                    $signatureValue = $keyValue[1];
                }
            }
        }

        if ($timestamp === null || $signatureValue === null) {
            Log::warning('Invalid webhook signature format', ['signature' => $signature]);
            return false;
        }

        // Compute expected signature: HMAC-SHA256(timestamp.payload, secret)
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

        return hash_equals($expectedSignature, $signatureValue);
    }

    /**
     * Get a subscription by ID from Polar.sh.
     * 
     * @param string $subscriptionId The Polar subscription ID
     * @return array|null Subscription data as array, or null on failure
     */
    public function getSubscription(string $subscriptionId): ?array
    {
        $client = $this->getClient();
        
        if ($client === null) {
            Log::error('Cannot get subscription: Polar client not available');
            return null;
        }

        try {
            $response = $client->subscriptions->get($subscriptionId);
            
            if ($response->subscription === null) {
                return null;
            }

            $subscription = $response->subscription;

            return [
                'id' => $subscription->id,
                'status' => $subscription->status->value ?? $subscription->status,
                'customer_id' => $subscription->customerId,
                'product_id' => $subscription->productId,
                'current_period_start' => $subscription->currentPeriodStart?->format('Y-m-d H:i:s'),
                'current_period_end' => $subscription->currentPeriodEnd?->format('Y-m-d H:i:s'),
                'cancel_at_period_end' => $subscription->cancelAtPeriodEnd ?? false,
                'canceled_at' => $subscription->canceledAt?->format('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get subscription', [
                'error' => $e->getMessage(),
                'subscriptionId' => $subscriptionId,
            ]);
            return null;
        }
    }

    /**
     * Check if the Polar service is properly configured.
     * 
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty(config('polar.api_token'));
    }

    /**
     * Set a custom client (useful for testing).
     * 
     * @param Polar|null $client
     * @return void
     */
    public function setClient(?Polar $client): void
    {
        $this->client = $client;
    }
}
