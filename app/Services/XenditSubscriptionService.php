<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service for handling Xendit Recurring/Subscriptions integration.
 *
 * This service manages subscription payments using Xendit's Subscriptions API,
 * which allows for automated recurring billing with flexible scheduling.
 */
class XenditSubscriptionService
{
    private string $apiKey;

    private string $webhookToken;

    private string $baseUrl;

    private array $plans;

    public function __construct()
    {
        $config = config('subscription.xendit', []);
        $this->apiKey = $config['api_key'] ?? config('xendit.api_key', '');
        $this->webhookToken = $config['webhook_token'] ?? config('xendit.webhook_token', '');
        $this->baseUrl = $config['base_url'] ?? config('xendit.base_url', 'https://api.xendit.co');
        $this->plans = config('subscription.plans', []);
    }

    /**
     * Check if Xendit is properly configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Create a customer in Xendit.
     *
     * @return string The Xendit customer ID
     *
     * @throws RuntimeException If API call fails
     */
    public function createCustomer(User $user): string
    {
        $this->ensureApiKey();

        $referenceId = 'cust_'.$user->id.'_'.time();

        Log::info('Xendit: Creating customer', [
            'user_id' => $user->id,
            'email' => $user->email,
            'reference_id' => $referenceId,
        ]);

        try {
            $payload = [
                'reference_id' => $referenceId,
                'type' => 'INDIVIDUAL',
                'email' => $user->email,
                'individual_detail' => [
                    'given_names' => $user->name ?? 'Customer',
                ],
            ];

            // Add mobile number if available
            if (! empty($user->phone)) {
                $payload['mobile_number'] = $this->formatPhoneNumber($user->phone);
            }

            Log::info('Xendit: Creating customer with payload', [
                'payload' => json_encode($payload),
            ]);

            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->baseUrl}/customers", $payload);

            if (! $response->successful()) {
                $errorMessage = $response->json('message') ?? $response->json('error_code') ?? 'Failed to create customer';

                Log::error('Xendit: Failed to create customer', [
                    'user_id' => $user->id,
                    'status_code' => $response->status(),
                    'error' => $errorMessage,
                ]);

                throw new RuntimeException("Xendit API error: {$errorMessage}");
            }

            $data = $response->json();

            Log::info('Xendit: Customer created', [
                'user_id' => $user->id,
                'customer_id' => $data['id'],
            ]);

            return $data['id'];
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Xendit: Unexpected error creating customer', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Failed to create Xendit customer: {$e->getMessage()}");
        }
    }

    /**
     * Get an existing customer by reference ID.
     *
     * @return string|null The customer ID or null if not found
     */
    public function getCustomerByReferenceId(string $referenceId): ?string
    {
        $this->ensureApiKey();

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->get("{$this->baseUrl}/customers", [
                    'reference_id' => $referenceId,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $customers = $data['data'] ?? [];

            if (empty($customers)) {
                return null;
            }

            return $customers[0]['id'] ?? null;
        } catch (\Exception $e) {
            Log::warning('Xendit: Error fetching customer', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create a recurring subscription plan.
     *
     * @return array{
     *     subscription_id: string,
     *     action_url: string|null,
     *     status: string
     * }
     *
     * @throws RuntimeException If API call fails
     */
    public function createRecurringPlan(
        User $user,
        string $planId,
        string $duration
    ): array {
        $this->ensureApiKey();

        // Validate plan exists
        if (! isset($this->plans[$planId])) {
            throw new RuntimeException("Invalid plan ID: {$planId}");
        }

        $plan = $this->plans[$planId];

        // Validate duration exists
        if (! isset($plan['durations'][$duration])) {
            throw new RuntimeException("Invalid duration: {$duration}");
        }

        $durationDetails = $plan['durations'][$duration];

        // Get or create customer
        $customerId = $this->getOrCreateCustomer($user);

        $referenceId = 'sub_'.$user->id.'_'.time();
        $scheduleReferenceId = 'sch_'.$user->id.'_'.time();

        Log::info('Xendit: Creating recurring plan', [
            'user_id' => $user->id,
            'plan_id' => $planId,
            'duration' => $duration,
            'reference_id' => $referenceId,
            'customer_id' => $customerId,
            'amount' => $durationDetails['price'],
        ]);

        try {
            // Get success/cancel URLs
            $urls = config('subscription.urls', []);
            $successUrl = $urls['success'] ?? config('app.url').'/subscription/success';
            $cancelUrl = $urls['cancel'] ?? config('app.url').'/subscription/cancelled';

            // Build payload according to Xendit API specification
            // See: https://docs.xendit.co/apidocs/create-recurring-plan
            $payload = [
                'reference_id' => $referenceId,
                'customer_id' => $customerId,
                'recurring_action' => 'PAYMENT',
                'currency' => $plan['currency'] ?? 'IDR',
                'amount' => (int) $durationDetails['price'],
                'schedule' => [
                    'reference_id' => $scheduleReferenceId,
                    'interval' => 'MONTH',
                    'interval_count' => $durationDetails['months'],
                    'total_recurrence' => $durationDetails['months'], // Limited number of cycles
                ],
                'success_return_url' => $successUrl,
                'failure_return_url' => $cancelUrl,
            ];

            Log::info('Xendit: Full payload being sent to /recurring/plans', [
                'url' => "{$this->baseUrl}/recurring/plans",
                'payload' => json_encode($payload),
                'payload_array' => $payload,
            ]);

            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->baseUrl}/recurring/plans", $payload);

            Log::info('Xendit: Response status and body', [
                'status' => $response->status(),
                'body' => $response->body(),
                'json' => $response->json(),
            ]);

            if (! $response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['message'] ?? $errorData['error_code'] ?? 'Failed to create recurring plan';

                Log::error('Xendit: Failed to create recurring plan', [
                    'user_id' => $user->id,
                    'status_code' => $response->status(),
                    'error' => $errorMessage,
                    'full_response' => $errorData,
                    'payload' => $payload,
                ]);

                throw new RuntimeException("Xendit API error: {$errorMessage}");
            }

            $data = $response->json();

            Log::info('Xendit: Recurring plan created', [
                'user_id' => $user->id,
                'subscription_id' => $data['id'],
                'status' => $data['status'],
            ]);

            // Get action URL if available
            $actionUrl = null;
            $actions = $data['actions'] ?? [];
            foreach ($actions as $action) {
                if ($action['action'] === 'AUTH' && ! empty($action['url'])) {
                    $actionUrl = $action['url'];
                    break;
                }
            }

            return [
                'subscription_id' => $data['id'],
                'action_url' => $actionUrl,
                'status' => $data['status'] ?? 'REQUIRES_ACTION',
                'reference_id' => $referenceId,
            ];
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Xendit: Unexpected error creating recurring plan', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Failed to create recurring plan: {$e->getMessage()}");
        }
    }

    /**
     * Get recurring plan details.
     *
     * @return array|null Plan details or null if not found
     */
    public function getRecurringPlan(string $planId): ?array
    {
        $this->ensureApiKey();

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->get("{$this->baseUrl}/recurring/plans/{$planId}");

            if (! $response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::warning('Xendit: Error fetching recurring plan', [
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Pause a recurring subscription.
     *
     * @return array Updated plan details
     *
     * @throws RuntimeException If API call fails
     */
    public function pauseRecurringPlan(string $planId): array
    {
        $this->ensureApiKey();

        Log::info('Xendit: Pausing recurring plan', ['plan_id' => $planId]);

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->baseUrl}/recurring/plans/{$planId}/pause");

            if (! $response->successful()) {
                $errorMessage = $response->json('message') ?? 'Failed to pause recurring plan';

                Log::error('Xendit: Failed to pause recurring plan', [
                    'plan_id' => $planId,
                    'error' => $errorMessage,
                ]);

                throw new RuntimeException("Xendit API error: {$errorMessage}");
            }

            Log::info('Xendit: Recurring plan paused', ['plan_id' => $planId]);

            return $response->json();
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Xendit: Unexpected error pausing recurring plan', [
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Failed to pause recurring plan: {$e->getMessage()}");
        }
    }

    /**
     * Resume a paused recurring subscription.
     *
     * @return array Updated plan details
     *
     * @throws RuntimeException If API call fails
     */
    public function resumeRecurringPlan(string $planId): array
    {
        $this->ensureApiKey();

        Log::info('Xendit: Resuming recurring plan', ['plan_id' => $planId]);

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->baseUrl}/recurring/plans/{$planId}/resume");

            if (! $response->successful()) {
                $errorMessage = $response->json('message') ?? 'Failed to resume recurring plan';

                Log::error('Xendit: Failed to resume recurring plan', [
                    'plan_id' => $planId,
                    'error' => $errorMessage,
                ]);

                throw new RuntimeException("Xendit API error: {$errorMessage}");
            }

            Log::info('Xendit: Recurring plan resumed', ['plan_id' => $planId]);

            return $response->json();
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Xendit: Unexpected error resuming recurring plan', [
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Failed to resume recurring plan: {$e->getMessage()}");
        }
    }

    /**
     * Stop/cancel a recurring subscription.
     *
     * @return array Updated plan details
     *
     * @throws RuntimeException If API call fails
     */
    public function stopRecurringPlan(string $planId): array
    {
        $this->ensureApiKey();

        Log::info('Xendit: Stopping recurring plan', ['plan_id' => $planId]);

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->baseUrl}/recurring/plans/{$planId}/stop");

            if (! $response->successful()) {
                // 404 means the plan no longer exists in Xendit — treat as already cancelled.
                // This happens when the plan expired/was cleaned up on Xendit's side, or when
                // API credentials changed between environments.
                if ($response->status() === 404) {
                    Log::warning('Xendit: Recurring plan not found, treating as already cancelled', [
                        'plan_id' => $planId,
                    ]);

                    return ['status' => 'STOPPED', 'already_gone' => true];
                }

                $errorMessage = $response->json('message') ?? 'Failed to stop recurring plan';

                Log::error('Xendit: Failed to stop recurring plan', [
                    'plan_id' => $planId,
                    'error' => $errorMessage,
                ]);

                throw new RuntimeException("Xendit API error: {$errorMessage}");
            }

            Log::info('Xendit: Recurring plan stopped', ['plan_id' => $planId]);

            return $response->json();
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Xendit: Unexpected error stopping recurring plan', [
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Failed to stop recurring plan: {$e->getMessage()}");
        }
    }

    /**
     * Get or create a customer for a user.
     */
    private function getOrCreateCustomer(User $user): string
    {
        // Check if user already has a Xendit customer ID stored
        $subscription = $user->subscription;
        if ($subscription && ! empty($subscription->xendit_customer_id)) {
            return $subscription->xendit_customer_id;
        }

        // Try to find existing customer by reference ID
        $referenceId = 'cust_'.$user->id;
        $existingCustomerId = $this->getCustomerByReferenceId($referenceId);

        if ($existingCustomerId) {
            // Save to subscription if exists
            if ($subscription) {
                $subscription->update(['xendit_customer_id' => $existingCustomerId]);
            }

            return $existingCustomerId;
        }

        // Create new customer
        $customerId = $this->createCustomer($user);

        // Save to subscription if exists
        if ($subscription) {
            $subscription->update(['xendit_customer_id' => $customerId]);
        }

        return $customerId;
    }

    /**
     * Format phone number for Xendit.
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove any non-digit characters except +
        $phone = preg_replace('/[^+0-9]/', '', $phone);

        // If doesn't start with +, add country code
        if (! str_starts_with($phone, '+')) {
            // Assume Indonesian number if no country code
            if (str_starts_with($phone, '0')) {
                $phone = '+62'.substr($phone, 1);
            } elseif (strlen($phone) >= 9) {
                $phone = '+'.$phone;
            }
        }

        return $phone;
    }

    /**
     * Ensure API key is configured.
     *
     * @throws RuntimeException If API key is not configured
     */
    private function ensureApiKey(): void
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('Xendit API key is not configured');
        }
    }
}
