<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'midtrans.server_key' => 'test_server_key',
            'midtrans.client_key' => 'test_client_key',
            'midtrans.is_production' => false,
            'subscription.trial_days' => 14,
            'subscription.plans' => [
                'pro' => [
                    'id' => 'pro',
                    'name' => 'Pro',
                    'price_monthly' => 350000,
                    'tier' => 'pro',
                    'features' => ['All features'],
                    'durations' => [
                        '1_month' => [
                            'id' => 'pro_1_month',
                            'name' => '1 Bulan',
                            'months' => 1,
                            'price' => 350000,
                            'price_per_month' => 350000,
                            'discount' => 0,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test status endpoint returns correct subscription info for trial user.
     */
    public function test_status_returns_trial_for_new_user(): void
    {
        $user = User::factory()->create([
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'subscription' => [
                        'status' => 'trial',
                        'plan_name' => 'free_trial',
                    ],
                ],
            ]);
    }

    /**
     * Test status endpoint returns correct info for active subscription.
     */
    public function test_status_returns_active_subscription(): void
    {
        $user = User::factory()->create(['is_master_admin' => true]);

        Subscription::create([
            'user_id' => $user->id,
            'midtrans_subscription_id' => 'sub_123',
            'midtrans_customer_id' => 'cust_123',
            'plan_name' => 'pro',
            'status' => 'active',
            'current_period_start' => Carbon::now()->subDays(10),
            'current_period_end' => Carbon::now()->addDays(20),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'subscription' => [
                        'status' => 'active',
                        'plan_name' => 'pro',
                    ],
                ],
            ]);
    }

    /**
     * Test status endpoint requires authentication.
     */
    public function test_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/subscription/status');

        $response->assertStatus(401);
    }

    /**
     * Test checkout endpoint validates plan_id.
     */
    public function test_checkout_validates_plan_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/checkout', [
                'plan_id' => 'invalid_plan',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PLAN',
                ],
            ]);
    }

    /**
     * Test checkout endpoint requires plan_id.
     */
    public function test_checkout_requires_plan_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/checkout', []);

        $response->assertStatus(422);
    }

    /**
     * Test cancel endpoint returns 404 when no subscription exists.
     */
    public function test_cancel_returns_404_without_subscription(): void
    {
        $user = User::factory()->create(['is_master_admin' => true]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/cancel');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_NOT_FOUND',
                ],
            ]);
    }
}
