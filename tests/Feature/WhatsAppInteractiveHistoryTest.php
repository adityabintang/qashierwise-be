<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppMessageHistory;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\Button;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\ButtonAction;
use Netflie\WhatsAppCloudApi\Message\ButtonReplyMessage;
use Netflie\WhatsAppCloudApi\Message\TextMessage;
use Netflie\WhatsAppCloudApi\Request\MessageRequest\RequestButtonReplyMessage;
use Netflie\WhatsAppCloudApi\Request\MessageRequest\RequestTextMessage;
use Netflie\WhatsAppCloudApi\Response;
use Tests\TestCase;

class WhatsAppInteractiveHistoryTest extends TestCase
{
    public function test_sent_bot_buttons_are_available_in_contact_history(): void
    {
        $this->artisan('migrate', ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2024_11_15_042414_create_whatsapp_accounts_table.php',
            'database/migrations/2024_11_15_042421_create_whatsapp_contacts_table.php',
            'database/migrations/2024_11_15_042421_create_whatsapp_messages_table.php',
            'database/migrations/2025_01_07_074337_add_phone_number_id_to_whatsapp_tables.php',
            'database/migrations/2025_01_27_140612_add_is_master_admin_to_users_table.php',
            'database/migrations/2025_11_23_093114_add_is_read_to_whatsapp_messages_table.php',
            'database/migrations/2025_12_15_000001_add_embedded_signup_fields_to_whatsapp_accounts_table.php',
        ]])->assertSuccessful();

        $user = User::factory()->create(['is_master_admin' => true]);
        $account = WhatsAppAccount::factory()->for($user)->create();
        $contact = WhatsAppContact::factory()->for($user)->create(['phone_number_id' => $account->phone_number_id]);
        $action = new ButtonAction([new Button('pickup', 'Ambil sendiri')]);
        $request = new RequestButtonReplyMessage(
            new ButtonReplyMessage($contact->wa_id, 'Pilih metode pengambilan', $action),
            'test-token',
            $account->phone_number_id,
        );
        $response = new Response($request, '{"messages":[{"id":"wamid.menu"}]}', 200);

        WhatsAppMessageHistory::recordInteractive($contact, $response);
        WhatsAppMessage::withoutGlobalScopes()->where('message_id', 'wamid.menu')->update(['status' => 'read']);
        WhatsAppMessageHistory::recordInteractive($contact, $response);

        $url = "/api/whatsapp/contacts/{$contact->id}/messages";
        $history = $this->actingAs($user, 'sanctum')->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'read')
            ->assertJsonPath('data.0.interactive_details.body', 'Pilih metode pengambilan')
            ->assertJsonPath('data.0.interactive_details.buttons.0.reply.title', 'Ambil sendiri');

        $text = '**Pesanan siap**';
        $textRequest = new RequestTextMessage(new TextMessage($contact->wa_id, $text), 'test-token', $account->phone_number_id);
        $textResponse = new Response($textRequest, '{"messages":[{"id":"wamid.ready"}]}', 200);
        WhatsAppMessageHistory::recordText($contact, $textResponse, $text);

        $cursor = $history->json('data.0.id');
        $live = $this->getJson($url.'?after_id='.$cursor)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction', 'outgoing')
            ->assertJsonPath('data.0.content', $text)
            ->assertJsonPath('has_more', false);
        $this->getJson($url.'?after_id='.$live->json('data.0.id'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
