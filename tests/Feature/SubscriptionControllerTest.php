<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\PolarService;
use App\DTOs\CheckoutSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up Polar config for tests
        config([
            'polar.api_token' => 'test_token',
            'polar.webhook_secret' => 'test_secret',
            'polar.products.standard' => 'prod_standard',
            'polar.products.pro' => 'prod_pro',
            'polar.urls.success' => 'https://example.com/success',
            'polar.urls.cancel' => 'https://example.com/cancel',
            'polar.trial_days' => 14,
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
        $user = User::factory()->create();
        
        Subscription::create([
            'user_id' => $user->id,
            'polar_subscription_id' => 'sub_123',
            'polar_customer_id' => 'cus_123',
            'plan_name' => 'standard',
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
                        'plan_name' => 'standard',
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
     * Test portal endpoint returns 404 when no subscription exists.
     */
    public function test_portal_returns_404_without_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription/portal');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_NOT_FOUND',
                ],
            ]);
    }
}
