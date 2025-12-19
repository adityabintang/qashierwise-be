<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\EmbeddedSignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddedSignupWebhookSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test subscribing WABA to webhooks successfully.
     */
    public function test_subscribe_to_webhooks_success(): void
    {
        $user = User::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'user_id' => $user->id,
            'waba_id' => 'test_waba_123',
            'access_token' => 'test_token',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/whatsapp/subscribe-webhooks');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully subscribed to webhooks',
            ]);
    }

    /**
     * Test subscribing when no WhatsApp account is connected.
     */
    public function test_subscribe_to_webhooks_no_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/whatsapp/subscribe-webhooks');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No WhatsApp account connected',
            ]);
    }

    /**
     * Test getting webhook subscription status.
     */
    public function test_get_webhook_status_success(): void
    {
        $user = User::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'user_id' => $user->id,
            'waba_id' => 'test_waba_123',
            'access_token' => 'test_token',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [
                    [
                        'whatsapp_business_api_data' => [
                            'id' => config('whatsapp.app_id'),
                            'name' => 'Test App',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/whatsapp/webhook-status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'subscribed' => true,
                    'waba_id' => 'test_waba_123',
                ],
            ]);
    }

    /**
     * Test EmbeddedSignupService subscribeToWebhooks method.
     */
    public function test_embedded_signup_service_subscribe_to_webhooks(): void
    {
        Http::fake([
            'graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true], 200),
        ]);

        $service = app(EmbeddedSignupService::class);
        $result = $service->subscribeToWebhooks('test_token', 'test_waba_id');

        $this->assertTrue($result['success']);
    }

    /**
     * Test EmbeddedSignupService checkWebhookSubscription detects subscribed app.
     */
    public function test_embedded_signup_service_check_subscription_subscribed(): void
    {
        $appId = config('whatsapp.embedded_signup.app_id') ?: '1484241559512577';

        Http::fake([
            'graph.facebook.com/*/subscribed_apps' => Http::response([
                'data' => [
                    [
                        'whatsapp_business_api_data' => [
                            'id' => $appId,
                            'name' => 'Qashierwise',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(EmbeddedSignupService::class);
        $result = $service->checkWebhookSubscription('test_token', 'test_waba_id');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['subscribed']);
    }

    /**
     * Test EmbeddedSignupService checkWebhookSubscription detects not subscribed.
     */
    public function test_embedded_signup_service_check_subscription_not_subscribed(): void
    {
        Http::fake([
            'graph.facebook.com/*/subscribed_apps' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $service = app(EmbeddedSignupService::class);
        $result = $service->checkWebhookSubscription('test_token', 'test_waba_id');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['subscribed']);
    }
}
