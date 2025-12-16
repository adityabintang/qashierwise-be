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
        $result = $this->createCheckoutSessionWithError($user, $planId);
        return $result['session'];
    }

    /**
     * Create a checkout session with detailed error information.
     * 
     * @param User $user The user initiating checkout
     * @param string $planId The internal plan identifier (standard, pro)
     * @return array{session: CheckoutSession|null, error: string|null}
     */
    public function createCheckoutSessionWithError(User $user, string $planId): array
    {
        $client = $this->getClient();
        
        if ($client === null) {
            $error = 'Polar client not available - check POLAR_API_TOKEN';
            Log::error('Cannot create checkout session: ' . $error);
            return ['session' => null, 'error' => $error];
        }

        $polarProductId = $this->planConfig->getPolarProductId($planId);
        
        if ($polarProductId === null) {
            $error = "Invalid plan ID: {$planId} - check POLAR_PRODUCT_STANDARD/PRO config";
            Log::error($error);
            return ['session' => null, 'error' => $error];
        }

        $successUrl = config('polar.urls.success');
        $cancelUrl = config('polar.urls.cancel');

        if (empty($successUrl)) {
            $error = 'POLAR_SUCCESS_URL not configured';
            Log::error($error);
            return ['session' => null, 'error' => $error];
        }

        try {
            Log::info('Creating checkout session', [
                'userId' => $user->id,
                'planId' => $planId,
                'polarProductId' => $polarProductId,
                'successUrl' => $successUrl,
                'cancelUrl' => $cancelUrl,
                'sandbox' => config('polar.sandbox'),
            ]);

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
                $error = 'Polar API returned null checkout';
                Log::error($error, ['response' => json_encode($response)]);
                return ['session' => null, 'error' => $error];
            }

            $checkout = $response->checkout;

            $session = new CheckoutSession(
                id: $checkout->id,
                url: $checkout->url,
                planId: $planId,
                userEmail: $user->email,
                successUrl: $checkout->successUrl,
                cancelUrl: $cancelUrl,
            );

            return ['session' => $session, 'error' => null];
        } catch (\Exception $e) {
            $error = $e->getMessage();
            Log::error('Failed to create checkout session', [
                'error' => $error,
                'trace' => $e->getTraceAsString(),
                'userId' => $user->id,
                'planId' => $planId,
                'polarProductId' => $polarProductId,
            ]);
            return ['session' => null, 'error' => $error];
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
     * Supports multiple signature formats:
     * 1. Standard Webhooks format: "v1,base64_signature" with webhook-id and webhook-timestamp headers
     * 2. Legacy format: "t=timestamp,v1=hex_signature" (timestamp embedded in signature header)
     * 
     * @param string $payload The raw webhook payload
     * @param string $signature The signature from the webhook-signature header
     * @param string|null $webhookId The webhook-id header value (for Standard Webhooks format)
     * @param string|null $timestamp The webhook-timestamp header value (for Standard Webhooks format)
     * @return bool
     */
    public function validateWebhookSignature(
        string $payload,
        string $signature,
        ?string $webhookId = null,
        ?string $timestamp = null
    ): bool {
        $webhookSecret = config('polar.webhook_secret');

        if (empty($webhookSecret)) {
            Log::error('Webhook secret is not configured');
            return false;
        }

        if (empty($payload) || empty($signature)) {
            return false;
        }

        Log::debug('Validating webhook signature', [
            'signature' => $signature,
            'webhookId' => $webhookId,
            'timestamp' => $timestamp,
            'payload_length' => strlen($payload),
        ]);

        // Parse signature and determine format
        $signatureValue = null;
        $embeddedTimestamp = null;

        // Format 1: "t=timestamp,v1=signature" (legacy format with embedded timestamp)
        if (str_contains($signature, '=')) {
            $parts = explode(',', $signature);
            foreach ($parts as $part) {
                $keyValue = explode('=', $part, 2);
                if (count($keyValue) === 2) {
                    if ($keyValue[0] === 't') {
                        $embeddedTimestamp = $keyValue[1];
                    } elseif ($keyValue[0] === 'v1') {
                        $signatureValue = $keyValue[1];
                    }
                }
            }
        }
        // Format 2: "v1,base64_signature" (Standard Webhooks format)
        elseif (str_starts_with($signature, 'v1,')) {
            $signatureValue = substr($signature, 3);
        }

        if ($signatureValue === null) {
            Log::warning('Invalid webhook signature format', ['signature' => $signature]);
            return false;
        }

        // Decode the webhook secret if it has a prefix
        $secretKey = $webhookSecret;
        if (str_starts_with($webhookSecret, 'whsec_')) {
            $secretKey = base64_decode(substr($webhookSecret, 6));
        } elseif (str_starts_with($webhookSecret, 'polar_whs_')) {
            $secretKey = substr($webhookSecret, 10);
        }

        // Determine signed payload based on format
        if ($embeddedTimestamp !== null) {
            // Legacy format: timestamp.payload
            $signedPayload = $embeddedTimestamp . '.' . $payload;
            // Legacy uses hex encoding
            $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);
            $isValid = hash_equals($expectedSignature, $signatureValue);
        } elseif ($webhookId !== null && $timestamp !== null) {
            // Standard Webhooks format: webhook_id.timestamp.payload
            $signedPayload = "{$webhookId}.{$timestamp}.{$payload}";
            $expectedSignature = base64_encode(hash_hmac('sha256', $signedPayload, $secretKey, true));
            $isValid = hash_equals($expectedSignature, $signatureValue);

            // Try with raw secret if decoded secret fails
            if (!$isValid) {
                $expectedSignatureAlt = base64_encode(hash_hmac('sha256', $signedPayload, $webhookSecret, true));
                $isValid = hash_equals($expectedSignatureAlt, $signatureValue);
            }
        } else {
            // Fallback: just payload (for simple testing)
            $signedPayload = $payload;
            $expectedSignature = base64_encode(hash_hmac('sha256', $signedPayload, $secretKey, true));
            $isValid = hash_equals($expectedSignature, $signatureValue);

            if (!$isValid) {
                $expectedSignatureAlt = base64_encode(hash_hmac('sha256', $signedPayload, $webhookSecret, true));
                $isValid = hash_equals($expectedSignatureAlt, $signatureValue);
            }
        }

        if (!$isValid) {
            Log::warning('Webhook signature mismatch', [
                'received' => $signatureValue,
                'webhookId' => $webhookId,
                'timestamp' => $timestamp,
                'embeddedTimestamp' => $embeddedTimestamp,
            ]);
        }

        return $isValid;
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
