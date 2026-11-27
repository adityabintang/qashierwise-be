<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppContactPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_can_be_sorted_and_searched_across_pages(): void
    {
        $user = User::factory()->create();
        $account = WhatsAppAccount::factory()->for($user)->create();
        $contacts = WhatsAppContact::factory()->for($user)->createMany([
            ['name' => 'Zara', 'phone_number_id' => $account->phone_number_id, 'created_at' => '2025-01-01'],
            ['name' => 'Ayu', 'phone_number_id' => $account->phone_number_id, 'created_at' => '2025-02-01'],
            ['name' => 'ayu', 'phone_number_id' => $account->phone_number_id, 'created_at' => '2025-03-01'],
        ]);
        $other = WhatsAppAccount::factory()->create();
        WhatsAppContact::factory()->create([
            'name' => 'Ayu dari tenant lain',
            'user_id' => $other->user_id,
            'phone_number_id' => $other->phone_number_id,
        ]);
        WhatsAppMessage::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'phone_number_id' => $account->phone_number_id,
            'contact_id' => $contacts[0]->id,
            'message_id' => 'wamid.inbox-unread',
            'direction' => 'incoming',
            'type' => 'text',
            'is_read' => false,
        ]);

        $this->actingAs($user, 'sanctum');
        $endpoint = '/api/whatsapp/contacts?per_page=1';
        $this->getJson($endpoint.'&sort=name_asc')->assertOk()
            ->assertJsonPath('data.0.id', $contacts[1]->id)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('pagination.last_page', 3);
        $this->getJson($endpoint.'&sort=name_asc&page=2')->assertOk()
            ->assertJsonPath('data.0.id', $contacts[2]->id)
            ->assertJsonPath('pagination.current_page', 2);
        $this->getJson($endpoint.'&sort=name_asc&page=3')->assertOk()
            ->assertJsonPath('data.0.id', $contacts[0]->id);
        $this->getJson($endpoint.'&sort=name_desc')->assertOk()
            ->assertJsonPath('data.0.id', $contacts[0]->id);
        $this->getJson($endpoint.'&sort=recent')->assertOk()
            ->assertJsonPath('data.0.id', $contacts[2]->id);
        $this->getJson($endpoint.'&sort=oldest')->assertOk()
            ->assertJsonPath('data.0.id', $contacts[0]->id);
        $this->getJson($endpoint.'&sort=name_asc&search=AYU&page=2')->assertOk()
            ->assertJsonPath('data.0.id', $contacts[2]->id)
            ->assertJsonPath('total', 2);
        $this->getJson($endpoint)->assertOk()
            ->assertJsonPath('data.0.id', $contacts[0]->id);
    }
}
