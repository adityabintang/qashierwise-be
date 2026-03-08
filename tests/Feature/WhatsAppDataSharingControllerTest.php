<?php

namespace Tests\Feature;

use App\Jobs\SyncWhatsAppDataJob;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppDataSharingConsent;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppDataSharingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_data_sharing_info(): void
    {
        $this->getJson('/api/whatsapp/data-sharing/info')->assertUnauthorized();
    }

    public function test_authenticated_user_can_get_data_sharing_info(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/whatsapp/data-sharing/info')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.disclosure.constraints.history_months', 6);
    }

    public function test_user_can_store_consent_with_selected_chats_and_sync_job_is_queued(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'user_id' => $user->id,
            'phone_number_id' => '10001',
            'is_active' => true,
        ]);

        $contactOne = WhatsAppContact::query()->create([
            'user_id' => $user->id,
            'phone_number_id' => $account->phone_number_id,
            'wa_id' => '628111111111',
            'name' => 'Alice',
            'unread_count' => 0,
        ]);

        $contactTwo = WhatsAppContact::query()->create([
            'user_id' => $user->id,
            'phone_number_id' => $account->phone_number_id,
            'wa_id' => '628222222222',
            'name' => 'Bob',
            'unread_count' => 0,
        ]);

        $payload = [
            'business_profile_shared' => true,
            'contacts_shared' => true,
            'conversation_history_shared' => true,
            'sharing_mode' => 'selected',
            'selected_contact_ids' => [$contactOne->id, $contactTwo->id],
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/data-sharing/consent', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sharing_mode', 'selected')
            ->assertJsonPath('data.conversation_months', 6);

        $consent = WhatsAppDataSharingConsent::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($consent);
        $this->assertSame(WhatsAppDataSharingConsent::STATUS_ACTIVE, $consent->status);
        $this->assertSame(6, $consent->conversation_months);
        $this->assertSame([$contactOne->id, $contactTwo->id], $consent->selected_contact_ids);

        Queue::assertPushed(SyncWhatsAppDataJob::class);
    }

    public function test_selected_mode_requires_selected_contact_ids(): void
    {
        $user = User::factory()->create();
        WhatsAppAccount::factory()->create([
            'user_id' => $user->id,
            'phone_number_id' => '10002',
            'is_active' => true,
        ]);

        $payload = [
            'business_profile_shared' => true,
            'contacts_shared' => true,
            'conversation_history_shared' => true,
            'sharing_mode' => 'selected',
            'selected_contact_ids' => [],
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/data-sharing/consent', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['selected_contact_ids']);
    }

    public function test_contacts_preview_returns_recent_message_counts(): void
    {
        $user = User::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'user_id' => $user->id,
            'phone_number_id' => '10003',
            'is_active' => true,
        ]);

        $contact = WhatsAppContact::query()->create([
            'user_id' => $user->id,
            'phone_number_id' => $account->phone_number_id,
            'wa_id' => '628333333333',
            'name' => 'Charlie',
            'unread_count' => 0,
        ]);

        WhatsAppMessage::query()->insert([
            'user_id' => $user->id,
            'phone_number_id' => $account->phone_number_id,
            'contact_id' => $contact->id,
            'message_id' => 'wamid.recent.1',
            'direction' => 'incoming',
            'type' => 'text',
            'content' => 'hello',
            'status' => 'read',
            'is_read' => true,
            'created_at' => now()->subMonths(2),
            'updated_at' => now()->subMonths(2),
        ]);

        WhatsAppMessage::query()->insert([
            'user_id' => $user->id,
            'phone_number_id' => $account->phone_number_id,
            'contact_id' => $contact->id,
            'message_id' => 'wamid.old.1',
            'direction' => 'incoming',
            'type' => 'text',
            'content' => 'old',
            'status' => 'read',
            'is_read' => true,
            'created_at' => now()->subMonths(8),
            'updated_at' => now()->subMonths(8),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/whatsapp/contacts/preview')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.message_count_6_months', 1);
    }

    public function test_user_can_revoke_existing_consent(): void
    {
        $user = User::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'user_id' => $user->id,
            'phone_number_id' => '10004',
            'is_active' => true,
        ]);

        WhatsAppDataSharingConsent::query()->create([
            'user_id' => $user->id,
            'whatsapp_account_id' => $account->id,
            'business_profile_shared' => true,
            'contacts_shared' => true,
            'conversation_history_shared' => true,
            'conversation_months' => 6,
            'sharing_mode' => 'all',
            'status' => WhatsAppDataSharingConsent::STATUS_ACTIVE,
            'consent_given_at' => now(),
            'consent_updated_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/whatsapp/data-sharing/consent')
            ->assertOk()
            ->assertJsonPath('data.status', WhatsAppDataSharingConsent::STATUS_REVOKED);
    }
}
