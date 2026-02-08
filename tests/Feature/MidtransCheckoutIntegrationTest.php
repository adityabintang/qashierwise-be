<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MidtransCheckoutIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Set up test configuration
        Config::set('midtrans.server_key', 'test_server_key');
        Config::set('midtrans.client_key', 'test_client_key');
        Config::set('midtrans.is_production', false);
        Config::set('subscription.plans', [
            'standard' => [
                'id' => 'standard',
                'name' => 'Standard',
                'price' => 99000,
            ],
            'pro' => [
                'id' => 'pro',
                'name' => 'Pro',
                'price' => 199000,
            ],
        ]);
    }

    public function test_successful_checkout_with_sandbox_credentials(): void
    {
        Sanctum::actingAs($this->user);

        // Mock successful Midtrans response
        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'test_snap_token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v3/redirection/test_snap_token',
            ], 200),
        ]);

        $response = $this->postJson('/api/subscription/checkout', [
            'plan_id' => 'standard',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'snap_token' => 'test_snap_token',
                    'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v3/redirection/test_snap_token',
                ],
            ]);
    }

    public function test_checkout_requires_authentication(): void
    {
        $response = $this->postJson('/api/subscription/checkout', [
            'plan_id' => 'standard',
        ]);

        $response->assertStatus(401);
    }

    public function test_checkout_validates_plan_id(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/subscription/checkout', [
            'plan_id' => 'invalid_plan',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PLAN',
                    'message' => 'Invalid plan selected',
                ],
            ]);
    }

    public function test_checkout_requires_plan_id(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/subscription/checkout', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PLAN',
                ],
            ]);
    }

    public function test_checkout_returns_503_when_midtrans_not_configured(): void
    {
        Sanctum::actingAs($this->user);

        // Clear Midtrans configuration
        Config::set('midtrans.server_key', '');
        Config::set('midtrans.client_key', '');

        $response = $this->postJson('/api/subscription/checkout', [
            'plan_id' => 'standard',
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'MIDTRANS_CONFIG_MISSING',
                    'message' => 'Subscription service is not configured',
                ],
            ]);
    }

    public function test_checkout_returns_500_when_midtrans_api_fails(): void
    {
        Sanctum::actingAs($this->user);

        // Mock failed Midtrans response
        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'error_messages' => ['Invalid request'],
            ], 400),
        ]);

        $response = $this->postJson('/api/subscription/checkout', [
            'plan_id' => 'standard',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'CHECKOUT_FAILED',
                    'message' => 'Failed to create checkout session',
                ],
            ]);
    }

    public function test_checkout_works_for_pro_plan(): void
    {
        Sanctum::actingAs($this->user);

        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'pro_snap_token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v3/redirection/pro_snap_token',
            ], 200),
        ]);

        $response = $this->postJson('/api/subscription/checkout', [
            'plan_id' => 'pro',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'snap_token' => 'pro_snap_token',
                ],
            ]);

        // Verify correct amount was sent to Midtrans
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['transaction_details']['gross_amount'] === 199000;
        });
    }

    public function test_checkout_sends_correct_user_data_to_midtrans(): void
    {
        Sanctum::actingAs($this->user);

        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'test_token',
                'redirect_url' => 'https://test.com',
            ], 200),
        ]);

        $this->postJson('/api/subscription/checkout', [
            'plan_id' => 'standard',
        ]);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['customer_details']['first_name'] === 'Test User'
                && $body['customer_details']['email'] === 'test@example.com'
                && $body['custom_field3'] === (string) $this->user->id;
        });
    }
}
