<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\MidtransSnapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransSnapServiceTest extends TestCase
{
    use RefreshDatabase;

    private MidtransSnapService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Set up test configuration
        Config::set('midtrans.server_key', 'test_server_key');
        Config::set('midtrans.client_key', 'test_client_key');
        Config::set('midtrans.is_production', false);
        Config::set('subscription.plans', [
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
                    '3_months' => [
                        'id' => 'pro_3_months',
                        'name' => '3 Bulan',
                        'months' => 3,
                        'price' => 1050000,
                        'price_per_month' => 350000,
                        'discount' => 0,
                    ],
                ],
            ],
        ]);

        $this->service = new MidtransSnapService;
    }

    public function test_is_configured_returns_true_when_keys_are_set(): void
    {
        $this->assertTrue($this->service->isConfigured());
    }

    public function test_is_configured_returns_false_when_server_key_is_missing(): void
    {
        Config::set('midtrans.server_key', '');
        $service = new MidtransSnapService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_false_when_client_key_is_missing(): void
    {
        Config::set('midtrans.client_key', '');
        $service = new MidtransSnapService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_false_when_both_keys_are_missing(): void
    {
        Config::set('midtrans.server_key', '');
        Config::set('midtrans.client_key', '');
        $service = new MidtransSnapService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_create_subscription_snap_token_returns_null_for_invalid_plan(): void
    {
        $result = $this->service->createSubscriptionSnapToken($this->user, 'invalid_plan', '1_month');

        $this->assertNull($result);
    }

    public function test_create_subscription_snap_token_success(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'test_snap_token_123',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v3/redirection/test_snap_token_123',
            ], 200),
        ]);

        $result = $this->service->createSubscriptionSnapToken($this->user, 'pro', '1_month');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('snap_token', $result);
        $this->assertArrayHasKey('redirect_url', $result);
        $this->assertEquals('test_snap_token_123', $result['snap_token']);
        $this->assertStringContainsString('midtrans.com', $result['redirect_url']);
    }

    public function test_create_subscription_snap_token_handles_api_error(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'error_messages' => ['Invalid request'],
            ], 400),
        ]);

        $result = $this->service->createSubscriptionSnapToken($this->user, 'pro', '1_month');

        $this->assertNull($result);
    }

    public function test_create_subscription_snap_token_handles_network_error(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(null, 500),
        ]);

        $result = $this->service->createSubscriptionSnapToken($this->user, 'pro', '1_month');

        $this->assertNull($result);
    }

    public function test_create_subscription_snap_token_generates_correct_payload(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => function ($request) {
                $body = json_decode($request->body(), true);

                // Verify payload structure
                $this->assertArrayHasKey('transaction_details', $body);
                $this->assertArrayHasKey('item_details', $body);
                $this->assertArrayHasKey('customer_details', $body);
                $this->assertArrayHasKey('custom_field1', $body);
                $this->assertArrayHasKey('custom_field2', $body);
                $this->assertArrayHasKey('custom_field3', $body);

                // Verify transaction details
                $this->assertEquals(350000, $body['transaction_details']['gross_amount']);
                $this->assertStringStartsWith('SUB-', $body['transaction_details']['order_id']);

                // Verify item details
                $this->assertEquals('pro_1_month', $body['item_details'][0]['id']);
                $this->assertEquals(350000, $body['item_details'][0]['price']);
                $this->assertEquals(1, $body['item_details'][0]['quantity']);

                // Verify customer details
                $this->assertEquals('Test User', $body['customer_details']['first_name']);
                $this->assertEquals('test@example.com', $body['customer_details']['email']);

                // Verify custom fields
                $this->assertEquals('pro', $body['custom_field1']);
                $this->assertEquals('1_month', $body['custom_field2']);
                $this->assertEquals((string) $this->user->id, $body['custom_field3']);

                return Http::response([
                    'token' => 'test_token',
                    'redirect_url' => 'https://test.com',
                ], 200);
            },
        ]);

        $this->service->createSubscriptionSnapToken($this->user, 'pro', '1_month');
    }

    public function test_create_subscription_snap_token_uses_production_url_when_configured(): void
    {
        Config::set('midtrans.is_production', true);
        $service = new MidtransSnapService;

        Http::fake([
            'app.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'prod_token',
                'redirect_url' => 'https://app.midtrans.com/snap/v3/redirection/prod_token',
            ], 200),
        ]);

        $result = $service->createSubscriptionSnapToken($this->user, 'pro', '1_month');

        $this->assertNotNull($result);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'app.midtrans.com');
        });
    }
}
