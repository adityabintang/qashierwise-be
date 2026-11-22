<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\WhatsAppAccountService;
use Netflie\WhatsAppCloudApi\Request\MessageReadRequest;
use Netflie\WhatsAppCloudApi\Response;
use Netflie\WhatsAppCloudApi\Response\ResponseException;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;
use Tests\TestCase;

class WhatsAppContactMarkReadTest extends TestCase
{
    public function test_missing_whatsapp_message_is_marked_read_locally_and_other_errors_can_be_retried(): void
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
        $contact = WhatsAppContact::factory()->for($user)->create([
            'phone_number_id' => $account->phone_number_id,
            'unread_count' => 3,
        ]);

        foreach (['wamid.missing', 'wamid.retry', 'wamid.ok'] as $messageId) {
            $contact->messages()->create([
                'user_id' => $user->id,
                'phone_number_id' => $account->phone_number_id,
                'message_id' => $messageId,
                'direction' => 'incoming',
                'status' => 'delivered',
            ]);
        }

        $missingRequest = new MessageReadRequest('wamid.missing', 'test-token', $account->phone_number_id);
        $retryRequest = new MessageReadRequest('wamid.retry', 'test-token', $account->phone_number_id);
        $successRequest = new MessageReadRequest('wamid.ok', 'test-token', $account->phone_number_id);

        $client = $this->mock(WhatsAppCloudApi::class);
        $client->expects('markMessageAsRead')->with('wamid.missing')->andThrow(new ResponseException(new Response(
            $missingRequest,
            json_encode(['error' => [
                'code' => 100,
                'error_data' => ['details' => 'Message ID: wamid.missing does not exist. Please check the message ID provided.'],
            ]]),
            400,
        )));
        $client->expects('markMessageAsRead')->with('wamid.retry')->andThrow(new ResponseException(new Response(
            $retryRequest,
            json_encode(['error' => [
                'code' => 100,
                'error_data' => ['details' => 'Another invalid parameter.'],
            ]]),
            400,
        )));
        $client->expects('markMessageAsRead')->with('wamid.retry')->andReturn(new Response($retryRequest, '{"success":true}', 200));
        $client->expects('markMessageAsRead')->with('wamid.ok')->andReturn(new Response($successRequest, '{"success":true}', 200));
        $this->mock(WhatsAppAccountService::class)->shouldReceive('getClientForUser')->with($user->id)->andReturn($client);

        $url = "/api/whatsapp/contacts/{$contact->id}/mark-read";
        $this->actingAs($user, 'sanctum')->postJson($url)->assertOk()->assertJson([
            'success' => true,
            'updated_count' => 2,
            'failed_count' => 1,
            'unread_count' => 1,
        ]);

        $missingMessage = $contact->messages()->where('message_id', 'wamid.missing')->firstOrFail();
        $this->assertTrue($missingMessage->is_read);
        $this->assertSame('read', $missingMessage->status);
        $this->assertNotNull($missingMessage->read_at);
        $this->assertSame(1, $contact->refresh()->unread_count);

        $this->postJson($url)->assertOk()->assertJson([
            'success' => true,
            'updated_count' => 1,
            'failed_count' => 0,
            'unread_count' => 0,
        ]);
        $this->assertSame(0, $contact->refresh()->unread_count);
    }
}
