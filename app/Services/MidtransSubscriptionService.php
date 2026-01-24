<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for communicating with Midtrans Subscription API.
 *
 * Handles subscription creation, retrieval, cancellation, and updates
 * through the Midtrans Subscription API v1.
 */
class MidtransSubscriptionService
{
    private string $baseUrl;
    private string $serverKey;
    private bool $isProduction;

    public function __construct()
    {
        $this->serverKey = config('subscription.midtrans.server_key', '');
        $this->isProduction = config('subscription.midtrans.is_production', false);
        $this->baseUrl = $this->isProduction
            ? 'https://api.midtrans.com/v1'
            : 'https://api.sandbox.midtrans.com/v1';

        if (empty($this->serverKey)) {
            Log::warning('Midtrans server key is not configured');
        }
    }

    /**
     * Create a new subscription.
     *
     * @param User $user The user creating the subscription
     * @param string $planId The plan identifier (standard, pro)
     * @param string $paymentToken The payment token from Midtrans
     * @return array|null Subscription data or null on failure
     */
    public function createSubscription(User $user, string $planId, string $paymentToken): ?array
    {
        if (empty($this->serverKey)) {
            Log::error('Cannot create subscription: Midtrans server key not configured');
            return null;
        }

        $plan = config("subscription.plans.{$planId}");
        if ($plan === null) {
            Log::error('Invalid plan ID', ['planId' => $planId]);
            return null;
        }

        $payload = [
            'name' => "{$plan['name']} Monthly Subscription",
            'amount' => (string) $plan['price'],
            'currency' => $plan['currency'],
            'payment_type' => 'credit_card',
            'token' => $paymentToken,
            'schedule' => [
                'interval' => $plan['interval_count'],
                'interval_unit' => $plan['interval'],
                'max_interval' => 12,
                'start_time' => now()->addMinute()->format('Y-m-d H:i:s O'),
            ],
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_id' => $planId,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
        ];

        try {
            $startTime = microtime(true);
            
            Log::info('Creating Midtrans subscription', [
                'event' => 'subscription.create.started',
                'userId' => $user->id,
                'planId' => $planId,
                'planName' => $plan['name'],
                'amount' => $plan['price'],
                'currency' => $plan['currency'],
            ]);

            $response = Http::withBasicAuth($this->serverKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/subscriptions", $payload);

            $duration = microtime(true) - $startTime;

            if (!$response->successful()) {
                Log::error('Failed to create Midtrans subscription', [
                    'event' => 'subscription.create.failed',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'userId' => $user->id,
                    'planId' => $planId,
                    'duration_ms' => round($duration * 1000, 2),
                ]);
                return null;
            }

            $data = $response->json();
            Log::info('Midtrans subscription created successfully', [
                'event' => 'subscription.create.success',
                'subscriptionId' => $data['id'] ?? null,
                'userId' => $user->id,
                'planId' => $planId,
                'status' => $data['status'] ?? null,
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $data;
        } catch (\Exception $e) {
            Log::error('Exception creating Midtrans subscription', [
                'error' => $e->getMessage(),
                'userId' => $user->id,
                'planId' => $planId,
            ]);
            return null;
        }
    }

    /**
     * Get subscription details.
     *
     * @param string $subscriptionId The Midtrans subscription ID
     * @return array|null Subscription data or null on failure
     */
    public function getSubscription(string $subscriptionId): ?array
    {
        if (empty($this->serverKey)) {
            Log::error('Cannot get subscription: Midtrans server key not configured');
            return null;
        }

        try {
            Log::debug('Fetching Midtrans subscription', [
                'subscriptionId' => $subscriptionId,
            ]);

            $response = Http::withBasicAuth($this->serverKey, '')
                ->get("{$this->baseUrl}/subscriptions/{$subscriptionId}");

            if (!$response->successful()) {
                Log::error('Failed to get Midtrans subscription', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'subscriptionId' => $subscriptionId,
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Exception getting Midtrans subscription', [
                'error' => $e->getMessage(),
                'subscriptionId' => $subscriptionId,
            ]);
            return null;
        }
    }

    /**
     * Cancel/disable subscription.
     *
     * @param string $subscriptionId The Midtrans subscription ID
     * @return bool True on success, false on failure
     */
    public function cancelSubscription(string $subscriptionId): bool
    {
        if (empty($this->serverKey)) {
            Log::error('Cannot cancel subscription: Midtrans server key not configured');
            return false;
        }

        try {
            $startTime = microtime(true);
            
            Log::info('Cancelling Midtrans subscription', [
                'event' => 'subscription.cancel.started',
                'subscriptionId' => $subscriptionId,
            ]);

            $response = Http::withBasicAuth($this->serverKey, '')
                ->post("{$this->baseUrl}/subscriptions/{$subscriptionId}/disable");

            $duration = microtime(true) - $startTime;

            if (!$response->successful()) {
                Log::error('Failed to cancel Midtrans subscription', [
                    'event' => 'subscription.cancel.failed',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'subscriptionId' => $subscriptionId,
                    'duration_ms' => round($duration * 1000, 2),
                ]);
                return false;
            }

            Log::info('Midtrans subscription cancelled successfully', [
                'event' => 'subscription.cancel.success',
                'subscriptionId' => $subscriptionId,
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Exception cancelling Midtrans subscription', [
                'error' => $e->getMessage(),
                'subscriptionId' => $subscriptionId,
            ]);
            return false;
        }
    }

    /**
     * Update subscription.
     *
     * @param string $subscriptionId The Midtrans subscription ID
     * @param array $data Update data
     * @return array|null Updated subscription data or null on failure
     */
    public function updateSubscription(string $subscriptionId, array $data): ?array
    {
        if (empty($this->serverKey)) {
            Log::error('Cannot update subscription: Midtrans server key not configured');
            return null;
        }

        try {
            Log::info('Updating Midtrans subscription', [
                'subscriptionId' => $subscriptionId,
                'data' => $data,
            ]);

            $response = Http::withBasicAuth($this->serverKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->patch("{$this->baseUrl}/subscriptions/{$subscriptionId}", $data);

            if (!$response->successful()) {
                Log::error('Failed to update Midtrans subscription', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'subscriptionId' => $subscriptionId,
                ]);
                return null;
            }

            $responseData = $response->json();
            Log::info('Midtrans subscription updated', [
                'subscriptionId' => $subscriptionId,
            ]);

            return $responseData;
        } catch (\Exception $e) {
            Log::error('Exception updating Midtrans subscription', [
                'error' => $e->getMessage(),
                'subscriptionId' => $subscriptionId,
            ]);
            return null;
        }
    }

    /**
     * Enable subscription.
     *
     * @param string $subscriptionId The Midtrans subscription ID
     * @return bool True on success, false on failure
     */
    public function enableSubscription(string $subscriptionId): bool
    {
        if (empty($this->serverKey)) {
            Log::error('Cannot enable subscription: Midtrans server key not configured');
            return false;
        }

        try {
            Log::info('Enabling Midtrans subscription', [
                'subscriptionId' => $subscriptionId,
            ]);

            $response = Http::withBasicAuth($this->serverKey, '')
                ->post("{$this->baseUrl}/subscriptions/{$subscriptionId}/enable");

            if (!$response->successful()) {
                Log::error('Failed to enable Midtrans subscription', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'subscriptionId' => $subscriptionId,
                ]);
                return false;
            }

            Log::info('Midtrans subscription enabled', [
                'subscriptionId' => $subscriptionId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Exception enabling Midtrans subscription', [
                'error' => $e->getMessage(),
                'subscriptionId' => $subscriptionId,
            ]);
            return false;
        }
    }

    /**
     * Check if the Midtrans service is properly configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->serverKey);
    }
}
