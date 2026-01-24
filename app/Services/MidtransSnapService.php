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
     * 
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->serverKey) && !empty($this->clientKey);
    }

    /**
     * Create a Snap token for subscription checkout.
     * 
     * @param User $user
     * @param string $planId
     * @return array|null ['snap_token' => string, 'redirect_url' => string] or null on failure
     */
    public function createSubscriptionSnapToken(User $user, string $planId): ?array
    {
        // Validate plan exists
        if (!isset($this->plans[$planId])) {
            Log::error('Invalid plan_id provided', [
                'planId' => $planId,
                'userId' => $user->id,
            ]);
            return null;
        }

        $plan = $this->plans[$planId];

        // Generate unique order ID
        $orderId = $this->generateOrderId($user->id);

        // Build transaction payload
        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $plan['price'],
            ],
            'item_details' => [
                [
                    'id' => $planId,
                    'price' => (int) $plan['price'],
                    'quantity' => 1,
                    'name' => "{$plan['name']} Subscription",
                ],
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
            'custom_field1' => $planId,
            'custom_field2' => 'subscription',
            'custom_field3' => (string) $user->id,
        ];

        Log::info('Creating Midtrans Snap token', [
            'userId' => $user->id,
            'planId' => $planId,
            'orderId' => $orderId,
            'amount' => $plan['price'],
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
                    'snapToken' => substr($data['token'] ?? '', 0, 20) . '...',
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
     * Format: SUB-{user_id}-{timestamp}-{random}
     * 
     * @param int $userId
     * @return string
     */
    private function generateOrderId(int $userId): string
    {
        $timestamp = time();
        $random = substr(md5(uniqid((string) rand(), true)), 0, 8);
        
        return "SUB-{$userId}-{$timestamp}-{$random}";
    }
}
