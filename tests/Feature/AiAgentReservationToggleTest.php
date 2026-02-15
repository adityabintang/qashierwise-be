<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentReservationToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_enabled_is_saved_and_returned(): void
    {
        $user = User::factory()->create();
        WhatsAppAccount::factory()->create(['user_id' => $user->id]);

        $payload = [
            'bot_name' => 'Reservasi Bot',
            'system_prompt' => 'Bantu pelanggan melakukan reservasi.',
            'business_info' => [],
            'default_store_id' => null,
            'order_enabled' => false,
            'qris_enabled' => false,
            'reservation_enabled' => true,
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->postJson('/api/ai-agent', $payload)
            ->assertOk()
            ->assertJsonPath('data.reservation_enabled', true);

        $this->actingAs($user)
            ->getJson('/api/ai-agent')
            ->assertOk()
            ->assertJsonPath('data.reservation_enabled', true);
    }

    public function test_test_endpoint_blocks_menu_when_order_feature_disabled(): void
    {
        $user = User::factory()->create();
        WhatsAppAccount::factory()->create(['user_id' => $user->id]);

        $payload = [
            'bot_name' => 'Reservasi Bot',
            'system_prompt' => 'Bantu pelanggan.',
            'business_info' => [],
            'default_store_id' => null,
            'order_enabled' => false,
            'qris_enabled' => false,
            'reservation_enabled' => false,
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->postJson('/api/ai-agent', $payload)
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/api/ai-agent/test', [
                'message' => 'menu yang ada apa aja?',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ai_response', 'Maaf, fitur order/menu via chat sedang nonaktif saat ini.');
    }
}
