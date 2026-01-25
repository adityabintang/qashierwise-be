<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test billing history returns empty array for user without payments.
     */
    public function test_billing_history_returns_empty_for_user_without_payments(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/subscription/billing-history');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'payments' => [],
                ],
            ]);
    }

    /**
     * Test billing history returns payments for user with subscription.
     */
    public function test_billing_history_returns_payments_for_user_with_subscription(): void
    {
        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_name' => 'pro',
            'status' => 'active',
        ]);

        $payment = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'order_id' => 'SUB-TEST-12345',
            'transaction_id' => 'trx-12345',
            'plan_name' => 'Pro - 3 Bulan',
            'duration' => '3_months',
            'gross_amount' => 872500,
            'currency' => 'IDR',
            'payment_type' => 'credit_card',
            'status' => 'settlement',
            'transaction_time' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/subscription/billing-history');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.order_id', 'SUB-TEST-12345')
            ->assertJsonPath('data.payments.0.status', 'settlement')
            ->assertJsonPath('data.payments.0.gross_amount', 872500);
    }

    /**
     * Test billing history returns multiple payments ordered by transaction time desc.
     */
    public function test_billing_history_returns_multiple_payments_in_order(): void
    {
        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_name' => 'pro',
            'status' => 'active',
        ]);

        // Older payment
        SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'order_id' => 'SUB-TEST-OLD',
            'plan_name' => 'Pro - 1 Bulan',
            'gross_amount' => 350000,
            'currency' => 'IDR',
            'status' => 'settlement',
            'transaction_time' => now()->subMonths(2),
        ]);

        // Newer payment
        SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'order_id' => 'SUB-TEST-NEW',
            'plan_name' => 'Pro - 3 Bulan',
            'gross_amount' => 872500,
            'currency' => 'IDR',
            'status' => 'settlement',
            'transaction_time' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/subscription/billing-history');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.payments')
            // Newer payment should come first
            ->assertJsonPath('data.payments.0.order_id', 'SUB-TEST-NEW')
            ->assertJsonPath('data.payments.1.order_id', 'SUB-TEST-OLD');
    }

    /**
     * Test billing history requires authentication.
     */
    public function test_billing_history_requires_authentication(): void
    {
        $response = $this->getJson('/api/subscription/billing-history');

        $response->assertStatus(401);
    }

    /**
     * Test SubscriptionPayment model helper methods.
     */
    public function test_subscription_payment_status_helpers(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);

        $settledPayment = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'order_id' => 'SUB-SETTLED',
            'plan_name' => 'Test',
            'gross_amount' => 100000,
            'currency' => 'IDR',
            'status' => 'settlement',
        ]);

        $pendingPayment = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'order_id' => 'SUB-PENDING',
            'plan_name' => 'Test',
            'gross_amount' => 100000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $failedPayment = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'order_id' => 'SUB-FAILED',
            'plan_name' => 'Test',
            'gross_amount' => 100000,
            'currency' => 'IDR',
            'status' => 'deny',
        ]);

        $this->assertTrue($settledPayment->isSuccessful());
        $this->assertFalse($settledPayment->isPending());
        $this->assertFalse($settledPayment->isFailed());

        $this->assertFalse($pendingPayment->isSuccessful());
        $this->assertTrue($pendingPayment->isPending());
        $this->assertFalse($pendingPayment->isFailed());

        $this->assertFalse($failedPayment->isSuccessful());
        $this->assertFalse($failedPayment->isPending());
        $this->assertTrue($failedPayment->isFailed());
    }
}
