<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogEmbeddedSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_config_endpoint_returns_catalog_config_id(): void
    {
        config([
            'whatsapp.catalog_embedded_signup.app_id' => 'test-app-id',
            'whatsapp.catalog_embedded_signup.app_secret' => 'test-app-secret',
            'whatsapp.catalog_embedded_signup.config_id' => 'catalog-config-123',
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/whatsapp/catalog/embedded-signup/config');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.config_id', 'catalog-config-123');
    }

    public function test_catalog_callback_stores_catalog_access_token(): void
    {
        config([
            'whatsapp.catalog_embedded_signup.app_id' => 'test-app-id',
            'whatsapp.catalog_embedded_signup.app_secret' => 'test-app-secret',
            'whatsapp.catalog_embedded_signup.config_id' => 'catalog-config-123',
        ]);

        Http::fake([
            'https://graph.facebook.com/*/oauth/access_token*' => Http::response([
                'access_token' => 'catalog-token-456',
                'expires_in' => 3600,
            ], 200),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $account = WhatsAppAccount::factory()->create([
            'user_id' => $user->id,
            'access_token' => 'wa-token-123',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/whatsapp/catalog/embedded-signup/callback', [
            'code' => 'auth-code-xyz',
            'business_id' => 'biz-789',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $account->refresh();

        $this->assertSame('wa-token-123', $account->access_token);
        $this->assertSame('catalog-token-456', $account->catalog_access_token);
        $this->assertSame('biz-789', $account->catalog_business_id);
        $this->assertNotNull($account->catalog_token_expires_at);
    }
}
