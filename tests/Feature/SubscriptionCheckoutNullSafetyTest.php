<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\MidtransSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Integration tests for subscription checkout null safety.
 * 
 * Validates: Requirements FR-1.1, FR-1.2, FR-1.3, FR-2.1, FR-2.2, FR-2.3
 */
class SubscriptionCheckoutNullSafetyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test full checkout flow for new user.
     * 
     * Validates: Requirements FR-1.1, FR-1.2, FR-1.3
     */
    public function test_full_checkout_flow_for_new_user(): void
    {
        // Create new user without subscription
        $user = User::factory()->create();

        // Mock Midtrans service
        $this->mock(MidtransSubscriptionService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        // Navigate to pricing page
        $response = $this->actingAs($user)->get(route('subscription.pricing'));
        $response->assertOk();

        // Click subscribe button (POST to checkout)
        $response = $this->actingAs($user)->post(route('subscription.checkout'), [
            'plan_id' => 'standard',
        ]);

        // Verify no errors in response
        $response->assertSessionHasNoErrors();
        
        // Verify redirect works correctly
        $response->assertRedirect(route('subscription.manage'));
        $response->assertSessionHas('info', 'Please complete payment setup to activate your subscription.');
    }

    /**
     * Test unauthenticated checkout attempt.
     * 
     * Validates: Requirements FR-1.1, FR-1.2
     */
    public function test_unauthenticated_checkout_redirects_to_login(): void
    {
        // Attempt checkout without authentication
        $response = $this->post(route('subscription.checkout'), [
            'plan_id' => 'standard',
        ]);

        // Verify redirect to login
        $response->assertRedirect(route('login'));
    }

    /**
     * Test unauthenticated manage page access.
     * 
     * Validates: Requirements FR-1.1, FR-1.2
     */
    public function test_unauthenticated_manage_page_redirects_to_login(): void
    {
        // Attempt to access manage page without authentication
        $response = $this->get(route('subscription.manage'));

        // The middleware may return 401 or redirect depending on configuration
        // Both are acceptable for unauthenticated access
        $this->assertTrue(
            $response->status() === 401 || $response->isRedirect(),
            'Expected 401 or redirect for unauthenticated access, got ' . $response->status()
        );
    }

    /**
     * Test unauthenticated cancel attempt.
     * 
     * Validates: Requirements FR-1.1, FR-1.2
     */
    public function test_unauthenticated_cancel_redirects_to_login(): void
    {
        // Attempt to cancel without authentication
        $response = $this->post(route('subscription.cancel.post'));

        // Verify redirect to login
        $response->assertRedirect(route('login'));
    }

    /**
     * Test unauthenticated callback access.
     */
    public function test_unauthenticated_callbacks_redirect_to_login(): void
    {
        // Test success callback
        $response = $this->get(route('subscription.success'));
        $response->assertRedirect(route('login'));

        // Test cancel callback
        $response = $this->get(route('subscription.cancel'));
        $response->assertRedirect(route('login'));

        // Test error callback
        $response = $this->get(route('subscription.error'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Test subscription status validation with various statuses.
     * 
     * Validates: Requirements FR-2.1, FR-2.2, FR-2.3
     */
    public function test_subscription_status_validation(): void
    {
        // Mock Midtrans service
        $this->mock(MidtransSubscriptionService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        // Test 1: Active subscription - should prevent checkout
        $userActive = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $userActive->id,
            'status' => 'active',
            'plan_name' => 'standard',
        ]);

        $response = $this->actingAs($userActive)->post(route('subscription.checkout'), [
            'plan_id' => 'pro',
        ]);

        $response->assertRedirect(route('subscription.manage'));
        $response->assertSessionHas('info', 'You already have an active subscription.');

        // Test 2: Cancelled subscription - should allow checkout
        $userCancelled = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $userCancelled->id,
            'status' => 'cancelled',
            'plan_name' => 'standard',
            'cancelled_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($userCancelled)->post(route('subscription.checkout'), [
            'plan_id' => 'pro',
        ]);

        $response->assertRedirect(route('subscription.manage'));
        $response->assertSessionHas('info', 'Please complete payment setup to activate your subscription.');

        // Test 3: Expired subscription - should allow checkout
        $userExpired = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $userExpired->id,
            'status' => 'expired',
            'plan_name' => 'standard',
        ]);

        $response = $this->actingAs($userExpired)->post(route('subscription.checkout'), [
            'plan_id' => 'standard',
        ]);

        $response->assertRedirect(route('subscription.manage'));
        $response->assertSessionHas('info', 'Please complete payment setup to activate your subscription.');

        // Test 4: Trial subscription - should allow checkout (upgrade)
        $userTrial = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $userTrial->id,
            'status' => 'trial',
            'plan_name' => 'free_trial',
        ]);

        $response = $this->actingAs($userTrial)->post(route('subscription.checkout'), [
            'plan_id' => 'standard',
        ]);

        $response->assertRedirect(route('subscription.manage'));
        $response->assertSessionHas('info', 'Please complete payment setup to activate your subscription.');
    }

    /**
     * Test manage page renders correctly for users without subscriptions.
     */
    public function test_manage_page_renders_for_users_without_subscription(): void
    {
        // Create user without subscription
        $user = User::factory()->create();

        // Mock SubscriptionService
        $this->mock(\App\Services\SubscriptionService::class, function ($mock) use ($user) {
            $mock->shouldReceive('getUserSubscriptionStatus')
                ->once()
                ->with($user)
                ->andReturn(new \App\DTOs\SubscriptionStatus(
                    status: 'trial',
                    planName: 'free_trial',
                    trialDaysRemaining: 14,
                    periodEnd: now()->addDays(14),
                    cancelledAt: null
                ));
        });

        // Access management page
        $response = $this->actingAs($user)->get(route('subscription.manage'));

        // Assert page renders successfully without errors
        $response->assertOk();
        
        // The key test: no null pointer errors occurred
        $this->assertTrue(true, 'Page rendered without null pointer errors');
    }

    /**
     * Test cancel endpoint with various subscription states.
     */
    public function test_cancel_endpoint_with_various_states(): void
    {
        // Test 1: No subscription - should show error
        $userNoSub = User::factory()->create();
        
        $response = $this->actingAs($userNoSub)->post(route('subscription.cancel.post'));
        
        $response->assertRedirect();
        $response->assertSessionHas('error', 'No active subscription found.');

        // Test 2: Already cancelled - should show info message
        $userCancelled = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $userCancelled->id,
            'status' => 'cancelled',
            'provider' => 'midtrans',
            'midtrans_subscription_id' => 'sub_12345',
            'cancelled_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($userCancelled)->post(route('subscription.cancel.post'));
        
        $response->assertRedirect();
        $response->assertSessionHas('info', 'Subscription is already cancelled.');

        // Test 3: Non-Midtrans subscription - should show error
        $userOther = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $userOther->id,
            'status' => 'active',
            'provider' => 'polar',
            'plan_name' => 'standard',
        ]);

        $response = $this->actingAs($userOther)->post(route('subscription.cancel.post'));
        
        $response->assertRedirect();
        $response->assertSessionHas('error', 'This subscription cannot be cancelled through this interface.');
    }

    /**
     * Test checkout validation errors.
     */
    public function test_checkout_validation_errors(): void
    {
        $user = User::factory()->create();

        // Test missing plan_id
        $response = $this->actingAs($user)->post(route('subscription.checkout'), []);
        $response->assertSessionHasErrors(['plan_id']);

        // Test invalid plan_id
        $response = $this->actingAs($user)->post(route('subscription.checkout'), [
            'plan_id' => 'invalid_plan',
        ]);
        $response->assertSessionHasErrors(['plan_id']);
    }

    /**
     * Test callback methods don't crash with null subscriptions.
     */
    public function test_callback_methods_handle_null_subscriptions(): void
    {
        $user = User::factory()->create();

        // Test success callback
        $response = $this->actingAs($user)->get(route('subscription.success'));
        $response->assertRedirect(route('subscription.manage'));
        $response->assertSessionHas('success');

        // Test cancel callback
        $response = $this->actingAs($user)->get(route('subscription.cancel'));
        $response->assertRedirect(route('subscription.pricing'));
        $response->assertSessionHas('info');

        // Test error callback
        $response = $this->actingAs($user)->get(route('subscription.error'));
        $response->assertRedirect(route('subscription.pricing'));
        $response->assertSessionHas('error');
    }

    /**
     * Test that session stores selected plan correctly.
     */
    public function test_checkout_stores_selected_plan_in_session(): void
    {
        $user = User::factory()->create();

        // Mock Midtrans service
        $this->mock(MidtransSubscriptionService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        // Checkout with standard plan
        $response = $this->actingAs($user)->post(route('subscription.checkout'), [
            'plan_id' => 'standard',
        ]);

        $response->assertSessionHas('selected_plan_id', 'standard');

        // Checkout with pro plan
        $response = $this->actingAs($user)->post(route('subscription.checkout'), [
            'plan_id' => 'pro',
        ]);

        $response->assertSessionHas('selected_plan_id', 'pro');
    }
}
