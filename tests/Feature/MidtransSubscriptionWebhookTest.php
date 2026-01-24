<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Test Midtrans subscription webhook handling.
 * 
 * Tests the complete flow of receiving a Midtrans webhook
 * and creating/updating subscription records.
 */
class MidtransSubscriptionWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $serverKey;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        config(['services.midtrans.server_key' => 'test-server-key']);
        config(['midtrans.server_key' => 'test-server-key']);
        config(['subscription.plans' => [
            'standard' => [
                'name' => 'Standard',
                'price' => 99000,
                'currency' => 'IDR',
            ],
            'pro' => [
                'name' => 'Pro',
                'price' => 199000,
                'currency' => 'IDR',
            ],
        ]]);

        $this->serverKey = 'test-server-key';
        $this->user = User::factory()->create();
    }

    /**
     * Test successful subscription payment webhook creates subscription.
     */
    public function test_successful_payment_creates_subscription(): void
    {
        $orderId = "SUB-{$this->user->id}-" . time() . "-test";
        $grossAmount = "99000.00";
        $statusCode = "200";

        // Calculate signature
        $signatureString = $orderId . $statusCode . $grossAmount . $this->serverKey;
        $signature = hash('sha512', $signatureString);

        // Build webhook payload
        $payload = [
            'transaction_time' => now()->format('Y-m-d H:i:s'),
            'transaction_status' => 'settlement',
            'transaction_id' => 'test-' . uniqid(),
            'status_message' => 'midtrans payment notification',
            'status_code' => $statusCode,
            'signature_key' => $signature,
            'payment_type' => 'credit_card',
            'order_id' => $orderId,
            'merchant_id' => 'G816352475',
            'gross_amount' => $grossAmount,
            'fraud_status' => 'accept',
            'currency' => 'IDR',
            'custom_field1' => 'standard',  // plan_id
            'custom_field2' => 'subscription',  // payment_type
            'custom_field3' => (string) $this->user->id,  // user_id
        ];

        // Send webhook request
        $response = $this->postJson('/api/webhooks/midtrans/subscription', $payload);

        // Debug response
        if (!$response->isSuccessful()) {
            dump($response->json());
        }

        // Assert response
        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);

        // Assert subscription was created
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->id,
            'plan_name' => 'standard',
            'status' => 'active',
            'provider' => 'midtrans',
        ]);

        // Verify subscription details
        $subscription = $this->user->fresh()->subscription;
        $this->assertNotNull($subscription);
        $this->assertEquals('standard', $subscription->plan_name);
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals('midtrans', $subscription->provider);
        
        // Verify metadata
        $metadata = json_decode($subscription->metadata, true);
        $this->assertEquals($orderId, $metadata['order_id']);
        $this->assertEquals('99000.00', $metadata['amount']);
    }

    /**
     * Test successful payment updates existing subscription.
     */
    public function test_successful_payment_updates_existing_subscription(): void
    {
        // Create existing subscription
        $existingSubscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_name' => 'standard',
            'status' => 'expired',
            'provider' => 'midtrans',
        ]);

        $orderId = "SUB-{$this->user->id}-" . time() . "-test";
        $grossAmount = "199000.00";
        $statusCode = "200";

        // Calculate signature
        $signatureString = $orderId . $statusCode . $grossAmount . $this->serverKey;
        $signature = hash('sha512', $signatureString);

        // Build webhook payload for pro plan
        $payload = [
            'transaction_time' => now()->format('Y-m-d H:i:s'),
            'transaction_status' => 'settlement',
            'transaction_id' => 'test-' . uniqid(),
            'status_message' => 'midtrans payment notification',
            'status_code' => $statusCode,
            'signature_key' => $signature,
            'payment_type' => 'credit_card',
            'order_id' => $orderId,
            'merchant_id' => 'G816352475',
            'gross_amount' => $grossAmount,
            'fraud_status' => 'accept',
            'currency' => 'IDR',
            'custom_field1' => 'pro',  // plan_id
            'custom_field2' => 'subscription',  // payment_type
            'custom_field3' => (string) $this->user->id,  // user_id
        ];

        // Send webhook request
        $response = $this->postJson('/api/webhooks/midtrans/subscription', $payload);

        // Assert response
        $response->assertStatus(200);

        // Assert subscription was updated (not created new)
        $this->assertEquals(1, Subscription::where('user_id', $this->user->id)->count());

        // Verify subscription was updated
        $subscription = $this->user->fresh()->subscription;
        $this->assertEquals('pro', $subscription->plan_name);
        $this->assertEquals('active', $subscription->status);
    }

    /**
     * Test webhook with invalid signature is rejected.
     */
    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $orderId = "SUB-{$this->user->id}-" . time() . "-test";
        $grossAmount = "99000.00";
        $statusCode = "200";

        // Build webhook payload with invalid signature
        $payload = [
            'transaction_time' => now()->format('Y-m-d H:i:s'),
            'transaction_status' => 'settlement',
            'transaction_id' => 'test-' . uniqid(),
            'status_message' => 'midtrans payment notification',
            'status_code' => $statusCode,
            'signature_key' => 'invalid-signature',
            'payment_type' => 'credit_card',
            'order_id' => $orderId,
            'merchant_id' => 'G816352475',
            'gross_amount' => $grossAmount,
            'fraud_status' => 'accept',
            'currency' => 'IDR',
            'custom_field1' => 'standard',
            'custom_field2' => 'subscription',
            'custom_field3' => (string) $this->user->id,
        ];

        // Send webhook request
        $response = $this->postJson('/api/webhooks/midtrans/subscription', $payload);

        // Assert response
        $response->assertStatus(401);
        $response->assertJson(['status' => 'error', 'message' => 'Invalid signature']);

        // Assert subscription was NOT created
        $this->assertDatabaseMissing('subscriptions', [
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test webhook with fraud status is not processed.
     */
    public function test_webhook_with_fraud_status_is_not_processed(): void
    {
        $orderId = "SUB-{$this->user->id}-" . time() . "-test";
        $grossAmount = "99000.00";
        $statusCode = "200";

        // Calculate signature
        $signatureString = $orderId . $statusCode . $grossAmount . $this->serverKey;
        $signature = hash('sha512', $signatureString);

        // Build webhook payload with fraud status
        $payload = [
            'transaction_time' => now()->format('Y-m-d H:i:s'),
            'transaction_status' => 'settlement',
            'transaction_id' => 'test-' . uniqid(),
            'status_message' => 'midtrans payment notification',
            'status_code' => $statusCode,
            'signature_key' => $signature,
            'payment_type' => 'credit_card',
            'order_id' => $orderId,
            'merchant_id' => 'G816352475',
            'gross_amount' => $grossAmount,
            'fraud_status' => 'deny',  // Fraud detected
            'currency' => 'IDR',
            'custom_field1' => 'standard',
            'custom_field2' => 'subscription',
            'custom_field3' => (string) $this->user->id,
        ];

        // Send webhook request
        $response = $this->postJson('/api/webhooks/midtrans/subscription', $payload);

        // Assert response (webhook is accepted but not processed)
        $response->assertStatus(200);

        // Assert subscription was NOT created
        $this->assertDatabaseMissing('subscriptions', [
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test webhook without subscription custom field is ignored.
     */
    public function test_webhook_without_subscription_custom_field_is_ignored(): void
    {
        $orderId = "ORDER-{$this->user->id}-" . time() . "-test";
        $grossAmount = "99000.00";
        $statusCode = "200";

        // Calculate signature
        $signatureString = $orderId . $statusCode . $grossAmount . $this->serverKey;
        $signature = hash('sha512', $signatureString);

        // Build webhook payload without subscription custom field
        $payload = [
            'transaction_time' => now()->format('Y-m-d H:i:s'),
            'transaction_status' => 'settlement',
            'transaction_id' => 'test-' . uniqid(),
            'status_message' => 'midtrans payment notification',
            'status_code' => $statusCode,
            'signature_key' => $signature,
            'payment_type' => 'credit_card',
            'order_id' => $orderId,
            'merchant_id' => 'G816352475',
            'gross_amount' => $grossAmount,
            'fraud_status' => 'accept',
            'currency' => 'IDR',
            'custom_field1' => 'standard',
            'custom_field2' => 'order',  // Not a subscription
            'custom_field3' => (string) $this->user->id,
        ];

        // Send webhook request
        $response = $this->postJson('/api/webhooks/midtrans/subscription', $payload);

        // Assert response
        $response->assertStatus(200);

        // Assert subscription was NOT created
        $this->assertDatabaseMissing('subscriptions', [
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test webhook idempotency - duplicate webhooks are ignored.
     */
    public function test_webhook_idempotency(): void
    {
        $orderId = "SUB-{$this->user->id}-" . time() . "-test";
        $grossAmount = "99000.00";
        $statusCode = "200";

        // Calculate signature
        $signatureString = $orderId . $statusCode . $grossAmount . $this->serverKey;
        $signature = hash('sha512', $signatureString);

        // Build webhook payload
        $payload = [
            'transaction_time' => now()->format('Y-m-d H:i:s'),
            'transaction_status' => 'settlement',
            'transaction_id' => 'test-' . uniqid(),
            'status_message' => 'midtrans payment notification',
            'status_code' => $statusCode,
            'signature_key' => $signature,
            'payment_type' => 'credit_card',
            'order_id' => $orderId,
            'merchant_id' => 'G816352475',
            'gross_amount' => $grossAmount,
            'fraud_status' => 'accept',
            'currency' => 'IDR',
            'custom_field1' => 'standard',
            'custom_field2' => 'subscription',
            'custom_field3' => (string) $this->user->id,
        ];

        // Send webhook request first time
        $response1 = $this->postJson('/api/webhooks/midtrans/subscription', $payload);
        $response1->assertStatus(200);

        // Get subscription ID
        $subscriptionId = $this->user->fresh()->subscription->id;

        // Send same webhook again (duplicate)
        $response2 = $this->postJson('/api/webhooks/midtrans/subscription', $payload);
        $response2->assertStatus(200);

        // Assert only one subscription exists
        $this->assertEquals(1, Subscription::where('user_id', $this->user->id)->count());
        
        // Assert subscription ID hasn't changed
        $this->assertEquals($subscriptionId, $this->user->fresh()->subscription->id);
    }
}
