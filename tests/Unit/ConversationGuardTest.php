<?php

namespace Tests\Unit;

use App\Enums\UserIntent;
use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\ConversationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationGuardTest extends TestCase
{
    use RefreshDatabase;

    private ConversationGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = $this->app->make(ConversationGuard::class);
    }

    public function test_guard_sets_delivery_type_from_message(): void
    {
        [$aiAgent, $conversation] = $this->makeConversation();

        $reply = $this->guard->resolve($conversation, $aiAgent, 'delivery', UserIntent::UNKNOWN);

        $this->assertNotNull($reply);
        $this->assertSame('delivery', $conversation->getDeliveryType());
        $this->assertSame(5000.0, $conversation->getOngkir());
        $this->assertStringContainsString('Delivery dipilih', $reply);
    }

    public function test_guard_saves_delivery_address_and_requests_notes(): void
    {
        [$aiAgent, $conversation] = $this->makeConversation();

        $conversation->setDeliveryType('delivery');
        $conversation->setOngkir(5000.0);

        $reply = $this->guard->resolve($conversation, $aiAgent, 'Jl Mawar No 5 RT 01', UserIntent::UNKNOWN);

        $this->assertNotNull($reply);
        $this->assertSame('Jl Mawar No 5 RT 01', $conversation->getDeliveryAddress());
        $this->assertStringContainsString('catatan khusus', strtolower($reply));
    }

    public function test_guard_sets_delivery_notes_when_customer_has_none(): void
    {
        [$aiAgent, $conversation] = $this->makeConversation();

        $conversation->setDeliveryType('pickup');

        $reply = $this->guard->resolve($conversation, $aiAgent, 'tidak ada', UserIntent::UNKNOWN);

        $this->assertNotNull($reply);
        $this->assertNull($conversation->getDeliveryNotes());
        $this->assertStringContainsString('Tidak ada catatan', $reply);
    }

    public function test_guard_uses_summary_when_context_is_ambiguous(): void
    {
        [$aiAgent, $conversation] = $this->makeConversation(withCart: false);

        $conversation->setSummary([
            'summary' => 'User ingin pesan makanan.',
            'intent' => 'order_food',
            'key_data' => [
                'products' => ['nasi goreng'],
                'reservation_date' => null,
                'reservation_time' => null,
                'people_count' => null,
                'order_items' => [],
                'total_estimate' => null,
            ],
            'missing_information' => [],
            'generated_at' => now()->toIso8601String(),
        ]);

        $reply = $this->guard->resolve($conversation, $aiAgent, '...', UserIntent::UNKNOWN);

        $this->assertNotNull($reply);
        $this->assertStringContainsString('pesan', strtolower($reply));
    }

    private function makeConversation(bool $withCart = true): array
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $account = WhatsAppAccount::factory()->create(['user_id' => $user->id]);
        $contact = WhatsAppContact::factory()->create(['user_id' => $user->id]);

        $aiAgent = AiAgent::factory()->create([
            'whatsapp_account_id' => $account->id,
            'default_store_id' => $store->id,
            'order_enabled' => true,
            'delivery_enabled' => true,
            'default_ongkir' => 5000,
        ]);

        $conversation = AiAgentConversation::create([
            'ai_agent_id' => $aiAgent->id,
            'whatsapp_contact_id' => $contact->id,
            'messages' => [],
            'order_context' => [],
            'expires_at' => now()->addHours(24),
        ]);

        if ($withCart) {
            $conversation->updateCart([
                [
                    'product_id' => 1,
                    'product_name' => 'Nasi Goreng',
                    'price' => 20000,
                    'quantity' => 1,
                ],
            ]);
        }

        return [$aiAgent, $conversation];
    }
}
