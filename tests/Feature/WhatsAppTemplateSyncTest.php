<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppTemplateSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_sync_is_available_in_production_with_debug_disabled(): void
    {
        $this->app->instance('env', 'production');
        config(['app.debug' => false]);
        $this->withoutVite();

        $owner = User::factory()->create();
        $account = WhatsAppAccount::factory()->for($owner)->create();
        Http::preventStrayRequests();
        Http::fake([
            "graph.facebook.com/*/{$account->waba_id}/message_templates*" => Http::response([
                'data' => [[
                    'id' => '123456789',
                    'name' => 'order_confirmation',
                    'language' => 'en',
                    'status' => 'APPROVED',
                    'category' => 'UTILITY',
                    'components' => [['type' => 'BODY', 'text' => 'Your order is confirmed.']],
                ]],
            ]),
        ]);

        $this->actingAs($owner)->get('/dashboard/templates')
            ->assertOk()
            ->assertSee('@click="refreshTemplates()"', false);

        $this->actingAs($owner, 'sanctum')->getJson('/api/whatsapp/templates?refresh=true')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'order_confirmation');

        $this->assertDatabaseHas('whatsapp_templates', [
            'whatsapp_account_id' => $account->id,
            'template_id' => '123456789',
        ]);
    }
}
