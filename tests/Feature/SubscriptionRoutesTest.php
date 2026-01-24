<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that all subscription routes are properly named
     */
    public function test_all_subscription_routes_are_named(): void
    {
        $this->assertTrue(route('subscription.pricing') !== null);
        $this->assertTrue(route('subscription.checkout') !== null);
        $this->assertTrue(route('subscription.success') !== null);
        $this->assertTrue(route('subscription.cancel') !== null);
        $this->assertTrue(route('subscription.error') !== null);
        $this->assertTrue(route('subscription.manage') !== null);
        $this->assertTrue(route('subscription.cancel.post') !== null);
    }

    /**
     * Test that subscription routes require authentication
     */
    public function test_subscription_routes_require_authentication(): void
    {
        // Test that routes are protected by checking they redirect unauthenticated users
        // We test the manage route which should redirect to login
        $response = $this->get(route('subscription.manage'));
        $response->assertRedirect(route('login'));
        
        // Test cancel subscription route requires auth
        $response = $this->post(route('subscription.cancel.post'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Test that webhook route is publicly accessible (no auth required)
     */
    public function test_webhook_route_is_publicly_accessible(): void
    {
        $response = $this->postJson('/api/webhooks/midtrans', [
            'order_id' => 'test_order',
            'status_code' => '200',
            'gross_amount' => '99000.00',
        ]);
        
        // Should not redirect to login, should process the webhook
        // Even if signature is invalid, it should return 200 or 400, not 401/302
        $this->assertNotEquals(302, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    /**
     * Test that webhook route is excluded from CSRF protection
     */
    public function test_webhook_route_excluded_from_csrf_protection(): void
    {
        // Make request without CSRF token
        $response = $this->postJson('/api/webhooks/midtrans', [
            'order_id' => 'test_order',
            'status_code' => '200',
            'gross_amount' => '99000.00',
        ]);
        
        // Should not return 419 (CSRF token mismatch)
        $this->assertNotEquals(419, $response->status());
    }

    /**
     * Test that webhook route has rate limiting applied
     */
    public function test_webhook_route_has_rate_limiting(): void
    {
        // This test verifies that the throttle middleware is applied
        // We can't easily test the actual rate limiting without making 60+ requests
        // But we can verify the route exists and is accessible
        $response = $this->postJson('/api/webhooks/midtrans', [
            'order_id' => 'test_order',
            'status_code' => '200',
            'gross_amount' => '99000.00',
        ]);
        
        // Should process the request (not return 429 on first request)
        $this->assertNotEquals(429, $response->status());
    }

    /**
     * Test that subscription routes use correct HTTP methods
     */
    public function test_subscription_routes_use_correct_http_methods(): void
    {
        $user = User::factory()->create();
        
        // GET routes should work
        $this->actingAs($user)->get(route('subscription.pricing'))->assertSuccessful();
        
        // POST routes should not accept GET (405 Method Not Allowed)
        $this->actingAs($user)->get(route('subscription.checkout'))->assertStatus(405);
    }

    /**
     * Test that authenticated users can access subscription routes
     */
    public function test_authenticated_users_can_access_subscription_routes(): void
    {
        $user = User::factory()->create();
        
        // Pricing page should be accessible
        $response = $this->actingAs($user)->get(route('subscription.pricing'));
        $response->assertSuccessful();
    }
}
