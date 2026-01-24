<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\MidtransSubscriptionService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MidtransSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up Midtrans config for tests
        config([
            'subscription.midtrans.server_key' => 'test_server_key',
            'subscription.midtrans.client_key' => 'test_client_key',
            'subscription.midtrans.is_production' => false,
            'subscription.plans' => [
                'standard' => [
                    'id' => 'standard',
                    'name' => 'Standard',
                    'price' => 99000,
                    'interval' => 'month',
                    'interval_count' => 1,
                    'currency' => 'IDR',
                ],
                'pro' => [
                    'id' => 'pro',
                    'name' => 'Pro',
                    'price' => 199000,
                    'interval' => 'month',
                    'interval_count' => 1,
                    'currency' => 'IDR',
                ],
            ],
        ]);
    }

    /**
     * Test index method returns pricing page with plans.
     */
    public function test_index_returns_pricing_page_with_plans(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('subscription.pricing'));

        $response->assertStatus(200)
            ->assertViewIs('subscription.pricing')
            ->assertViewHas('plans');
    }

    /**
     * Test createCheckout validates plan_id.
     */
    public function test_create_checkout_validates_plan_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('subscription.checkout'), [
                'plan_id' => 'invalid_plan',
            ]);

        $response->assertSessionHasErrors('plan_id');
    }

    /**
     * Test createCheckout requires plan_id.
     */
    public function test_create_checkout_requires_plan_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('subscription.checkout'), []);

        $response->assertSessionHasErrors('plan_id');
    }

    /**
     * Test createCheckout checks if service is configured.
     */
    public function test_create_checkout_checks_service_configuration(): void
    {
        config(['subscription.midtrans.server_key' => '']);
        
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('subscription.checkout'), [
                'plan_id' => 'standard',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('error');
    }

    /**
     * Test createCheckout redirects if user has active subscription.
     */
    public function test_create_checkout_redirects_if_user_has_active_subscription(): void
    {
        $user = User::factory()->create();
        
        Subscription::create([
            'user_id' => $user->id,
            'provider' => 'midtrans',
            'midtrans_subscription_id' => 'sub_123',
            'midtrans_customer_id' => 'cus_123',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
        ]);

        $response = $this->actingAs($user)
            ->post(route('subscription.checkout'), [
                'plan_id' => 'pro',
            ]);

        $response->assertRedirect(route('subscription.manage'))
            ->assertSessionHas('info');
    }

    /**
     * Test success callback logs and redirects.
     */
    public function test_success_callback_redirects_to_manage(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('subscription.success'));

        $response->assertRedirect(route('subscription.manage'))
            ->assertSessionHas('success');
    }

    /**
     * Test cancel callback redirects to pricing.
     */
    public function test_cancel_callback_redirects_to_pricing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('subscription.cancel'));

        $response->assertRedirect(route('subscription.pricing'))
            ->assertSessionHas('info');
    }

    /**
     * Test error callback redirects to pricing with error.
     */
    public function test_error_callback_redirects_to_pricing_with_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('subscription.error'));

        $response->assertRedirect(route('subscription.pricing'))
            ->assertSessionHas('error');
    }

    /**
     * Test manage method returns subscription management page.
     */
    public function test_manage_returns_subscription_management_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('subscription.manage'));

        $response->assertStatus(200)
            ->assertViewIs('subscription.manage')
            ->assertViewHas(['subscription', 'subscriptionStatus', 'plans']);
    }

    /**
     * Test cancelSubscription returns error if no subscription exists.
     */
    public function test_cancel_subscription_returns_error_if_no_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('subscription.cancel.post'));

        $response->assertRedirect()
            ->assertSessionHas('error', 'No active subscription found.');
    }

    /**
     * Test cancelSubscription returns error if subscription is not Midtrans.
     */
    public function test_cancel_subscription_returns_error_if_not_midtrans(): void
    {
        $user = User::factory()->create();
        
        Subscription::create([
            'user_id' => $user->id,
            'provider' => 'polar',
            'polar_subscription_id' => 'sub_123',
            'polar_customer_id' => 'cus_123',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
        ]);

        $response = $this->actingAs($user)
            ->post(route('subscription.cancel.post'));

        $response->assertRedirect()
            ->assertSessionHas('error');
    }

    /**
     * Test cancelSubscription returns info if already cancelled.
     */
    public function test_cancel_subscription_returns_info_if_already_cancelled(): void
    {
        $user = User::factory()->create();
        
        Subscription::create([
            'user_id' => $user->id,
            'provider' => 'midtrans',
            'midtrans_subscription_id' => 'sub_123',
            'midtrans_customer_id' => 'cus_123',
            'plan_name' => 'standard',
            'status' => 'cancelled',
            'cancelled_at' => now()->subDay(),
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
        ]);

        $response = $this->actingAs($user)
            ->post(route('subscription.cancel.post'));

        $response->assertRedirect()
            ->assertSessionHas('info', 'Subscription is already cancelled.');
    }

    /**
     * Test cancelSubscription successfully cancels Midtrans subscription.
     */
    public function test_cancel_subscription_successfully_cancels_midtrans_subscription(): void
    {
        $user = User::factory()->create();
        
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'provider' => 'midtrans',
            'midtrans_subscription_id' => 'sub_123',
            'midtrans_customer_id' => 'cus_123',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
        ]);

        // Mock MidtransSubscriptionService
        $mockService = Mockery::mock(MidtransSubscriptionService::class);
        $mockService->shouldReceive('cancelSubscription')
            ->once()
            ->with('sub_123')
            ->andReturn(true);
        
        $this->app->instance(MidtransSubscriptionService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('subscription.cancel.post'));

        $response->assertRedirect(route('subscription.manage'))
            ->assertSessionHas('success');

        // Verify subscription was updated
        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
    }

    /**
     * Test cancelSubscription handles service failure.
     */
    public function test_cancel_subscription_handles_service_failure(): void
    {
        $user = User::factory()->create();
        
        Subscription::create([
            'user_id' => $user->id,
            'provider' => 'midtrans',
            'midtrans_subscription_id' => 'sub_123',
            'midtrans_customer_id' => 'cus_123',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
        ]);

        // Mock MidtransSubscriptionService to return failure
        $mockService = Mockery::mock(MidtransSubscriptionService::class);
        $mockService->shouldReceive('cancelSubscription')
            ->once()
            ->with('sub_123')
            ->andReturn(false);
        
        $this->app->instance(MidtransSubscriptionService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('subscription.cancel.post'));

        $response->assertRedirect()
            ->assertSessionHas('error');
    }

    /**
     * Test all routes require authentication.
     */
    public function test_all_routes_require_authentication(): void
    {
        $routes = [
            ['get', route('subscription.pricing')],
            ['post', route('subscription.checkout')],
            ['get', route('subscription.success')],
            ['get', route('subscription.cancel')],
            ['get', route('subscription.error')],
            ['get', route('subscription.manage')],
            ['post', route('subscription.cancel.post')],
        ];

        foreach ($routes as [$method, $route]) {
            $response = $this->$method($route);
            $response->assertRedirect(route('login'));
        }
    }
}

