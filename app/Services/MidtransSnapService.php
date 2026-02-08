<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling Midtrans Snap integration for subscriptions.
 *
 * This service creates Snap payment tokens for subscription checkout,
 * allowing users to pay via Midtrans payment page.
 */
class MidtransSnapService
{
    private string $serverKey;

    private string $clientKey;

    private string $snapUrl;

    private array $plans;

    public function __construct()
    {
        $this->serverKey = config('midtrans.server_key', '');
        $this->clientKey = config('midtrans.client_key', '');
        $this->plans = config('subscription.plans', []);

        // Set Snap URL based on environment
        $isProduction = config('midtrans.is_production', false);
        $this->snapUrl = $isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    /**
     * Check if Midtrans Snap is properly configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->serverKey) && ! empty($this->clientKey);
    }

    /**
     * Create a Snap token for subscription checkout.
     *
     * @param  float|null  $customAmount  Optional custom amount (for promo codes)
     * @param  string|null  $promoCode  Optional promo code
     * @return array|null ['snap_token' => string, 'redirect_url' => string] or null on failure
     */
    public function createSubscriptionSnapToken(
        User $user,
        string $planId,
        string $duration,
        ?float $customAmount = null,
        ?string $promoCode = null
    ): ?array {
        // Validate plan exists
        if (! isset($this->plans[$planId])) {
            Log::error('Invalid plan_id provided', [
                'planId' => $planId,
                'userId' => $user->id,
            ]);

            return null;
        }

        $plan = $this->plans[$planId];

        // Validate duration exists
        if (! isset($plan['durations'][$duration])) {
            Log::error('Invalid duration provided', [
                'planId' => $planId,
                'duration' => $duration,
                'userId' => $user->id,
            ]);

            return null;
        }

        $durationDetails = $plan['durations'][$duration];

        // Use custom amount if provided (for promo codes), otherwise use plan price
        $amount = $customAmount ?? $durationDetails['price'];

        // Generate unique order ID
        $orderId = $this->generateOrderId($user->id, $duration);

        // Build item name
        $itemName = "{$plan['name']} Subscription - {$durationDetails['name']}";
        if ($promoCode) {
            $itemName .= " (Promo: {$promoCode})";
        }

        // Build transaction payload
        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $amount,
            ],
            'item_details' => [
                [
                    'id' => $durationDetails['id'],
                    'price' => (int) $amount,
                    'quantity' => 1,
                    'name' => $itemName,
                ],
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
            'custom_field1' => $planId,
            'custom_field2' => $duration,
            'custom_field3' => (string) $user->id,
            'callbacks' => [
                'finish' => route('subscription.success'),
                'error' => route('subscription.error'),
                'pending' => route('subscription.manage'),
            ],
            // Enable save card for recurring subscription
            'credit_card' => [
                'secure' => true,
                'save_card' => true, // This enables card tokenization
            ],
            'enabled_payments' => [
                'credit_card',
                'bca_va',
                'bni_va',
                'bri_va',
                'permata_va',
                'other_va',
                'gopay',
                'shopeepay',
            ],
        ];

        Log::info('Creating Midtrans Snap token', [
            'userId' => $user->id,
            'planId' => $planId,
            'duration' => $duration,
            'orderId' => $orderId,
            'amount' => $durationDetails['price'],
            'months' => $durationDetails['months'],
        ]);

        try {
            // Make HTTP POST request to Midtrans Snap API with Basic Auth
            $response = Http::withBasicAuth($this->serverKey, '')
                ->timeout(30)
                ->post($this->snapUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Snap token created successfully', [
                    'userId' => $user->id,
                    'orderId' => $orderId,
                    'snapToken' => substr($data['token'] ?? '', 0, 20).'...',
                ]);

                return [
                    'snap_token' => $data['token'] ?? null,
                    'redirect_url' => $data['redirect_url'] ?? null,
                ];
            }

            // Handle error response
            $errorData = $response->json();
            Log::error('Failed to create Snap token', [
                'userId' => $user->id,
                'orderId' => $orderId,
                'statusCode' => $response->status(),
                'error' => $errorData,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception while creating Snap token', [
                'userId' => $user->id,
                'orderId' => $orderId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Generate a unique order ID for subscription.
     *
     * Format: SUB-{user_id}-{duration}-{timestamp}-{random}
     */
    private function generateOrderId(int $userId, string $duration): string
    {
        $timestamp = time();
        $random = substr(md5(uniqid((string) rand(), true)), 0, 8);

        return "SUB-{$userId}-{$duration}-{$timestamp}-{$random}";
    }
}
