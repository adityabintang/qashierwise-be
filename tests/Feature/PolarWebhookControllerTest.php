<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolarWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'test_webhook_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'polar.api_token' => 'test_token',
            'polar.webhook_secret' => $this->webhookSecret,
            'polar.products.standard' => 'prod_standard',
            'polar.products.pro' => 'prod_pro',
            'polar.trial_days' => 14,
        ]);
    }

    /**
     * Generate a valid webhook signature for testing.
     */
    private function generateSignature(string $payload): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        return "t={$timestamp},v1={$signature}";
    }

    /**
     * Test webhook rejects invalid signature.
     */
    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode([
            'type' => 'subscription.created',
            'data' => [],
        ]);

        $response = $this->postJson('/api/webhooks/polar', json_decode($payload, true), [
            'webhook-signature' => 'invalid_signature',
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test webhook accepts valid signature.
     */
    public function test_webhook_accepts_valid_signature(): void
    {
        $user = User::factory()->create();

        $payload = json_encode([
            'type' => 'subscription.created',
            'data' => [
                'id' => 'sub_123',
                'customer_id' => 'cus_123',
                'product_id' => 'prod_standard',
                'status' => 'active',
                'current_period_start' => Carbon::now()->toIso8601String(),
                'current_period_end' => Carbon::now()->addMonth()->toIso8601String(),
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan_id' => 'standard',
                ],
            ],
        ]);

        $signature = $this->generateSignature($payload);

        $response = $this->call(
            'POST',
            '/api/webhooks/polar',
            [],
            [],
            [],
            [
                'HTTP_WEBHOOK_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);
    }

    /**
     * Test webhook creates subscription on subscription.created event.
     */
    public function test_webhook_creates_subscription(): void
    {
        $user = User::factory()->create();

        $payload = json_encode([
            'type' => 'subscription.created',
            'data' => [
                'id' => 'sub_new_123',
                'customer_id' => 'cus_new_123',
                'product_id' => 'prod_standard',
                'status' => 'active',
                'current_period_start' => Carbon::now()->toIso8601String(),
                'current_period_end' => Carbon::now()->addMonth()->toIso8601String(),
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan_id' => 'standard',
                ],
            ],
        ]);

        $signature = $this->generateSignature($payload);

        $response = $this->call(
            'POST',
            '/api/webhooks/polar',
            [],
            [],
            [],
            [
                'HTTP_WEBHOOK_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'polar_subscription_id' => 'sub_new_123',
            'polar_customer_id' => 'cus_new_123',
            'plan_name' => 'standard',
            'status' => 'active',
        ]);
    }

    /**
     * Test webhook handles subscription.cancelled event.
     */
    public function test_webhook_handles_cancellation(): void
    {
        $user = User::factory()->create();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'polar_subscription_id' => 'sub_cancel_123',
            'polar_customer_id' => 'cus_cancel_123',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => Carbon::now()->subDays(10),
            'current_period_end' => Carbon::now()->addDays(20),
        ]);

        $cancelledAt = Carbon::now()->toIso8601String();

        $payload = json_encode([
            'type' => 'subscription.cancelled',
            'data' => [
                'id' => 'sub_cancel_123',
                'canceled_at' => $cancelledAt,
            ],
        ]);

        $signature = $this->generateSignature($payload);

        $response = $this->call(
            'POST',
            '/api/webhooks/polar',
            [],
            [],
            [],
            [
                'HTTP_WEBHOOK_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);

        $subscription->refresh();
        $this->assertNotNull($subscription->cancelled_at);
    }

    /**
     * Test webhook returns 400 for missing event type.
     */
    public function test_webhook_returns_400_for_missing_event_type(): void
    {
        $payload = json_encode([
            'data' => [],
        ]);

        $signature = $this->generateSignature($payload);

        $response = $this->call(
            'POST',
            '/api/webhooks/polar',
            [],
            [],
            [],
            [
                'HTTP_WEBHOOK_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(400);
    }
}
