<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppDataSharingConsent;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppDataSharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppDataSharingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_consent_keeps_only_user_owned_selected_contacts(): void
    {
        $service = app(WhatsAppDataSharingService::class);

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $account = WhatsAppAccount::factory()->create([
            'user_id' => $owner->id,
            'phone_number_id' => '20001',
            'is_active' => true,
        ]);

        $ownedContact = WhatsAppContact::query()->create([
            'user_id' => $owner->id,
            'phone_number_id' => $account->phone_number_id,
            'wa_id' => '628121111111',
            'name' => 'Owned',
            'unread_count' => 0,
        ]);

        $foreignContact = WhatsAppContact::query()->create([
            'user_id' => $otherUser->id,
            'phone_number_id' => '99999',
            'wa_id' => '628129999999',
            'name' => 'Foreign',
            'unread_count' => 0,
        ]);

        $consent = $service->upsertConsent(
            $account,
            $owner->id,
            [
                'business_profile_shared' => true,
                'contacts_shared' => true,
                'conversation_history_shared' => true,
                'sharing_mode' => WhatsAppDataSharingConsent::SHARING_MODE_SELECTED,
                'selected_contact_ids' => [$ownedContact->id, $foreignContact->id],
            ],
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertSame([$ownedContact->id], $consent->selected_contact_ids);
        $this->assertSame(6, $consent->conversation_months);
        $this->assertSame(WhatsAppDataSharingConsent::STATUS_ACTIVE, $consent->status);
    }

    public function test_get_shareable_message_count_applies_six_month_filter_and_selection(): void
    {
        $service = app(WhatsAppDataSharingService::class);

        $user = User::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'user_id' => $user->id,
            'phone_number_id' => '20002',
            'is_active' => true,
        ]);

        $contactOne = WhatsAppContact::query()->create([
            'user_id' => $user->id,
            'phone_number_id' => $account->phone_number_id,
            'wa_id' => '628120000001',
            'name' => 'One',
            'unread_count' => 0,
        ]);

        $contactTwo = WhatsAppContact::query()->create([
            'user_id' => $user->id,
            'phone_number_id' => $account->phone_number_id,
            'wa_id' => '628120000002',
            'name' => 'Two',
            'unread_count' => 0,
        ]);

        WhatsAppMessage::query()->insert([
            'user_id' => $user->id,
            'phone_number_id' => $account->phone_number_id,
            'contact_id' => $contactOne->id,
            'message_id' => 'svc.recent.1',
            'direction' => 'incoming',
            'type' => 'text',
            'content' => 'recent one',
            'status' => 'read',
            'is_read' => true,
            'created_at' => now()->subMonths(1),
            'updated_at' => now()->subMonths(1),
        ]);

        WhatsAppMessage::query()->insert([
            'user_id' => $user->id,
            'phone_number_id' => $account->phone_number_id,
            'contact_id' => $contactTwo->id,
            'message_id' => 'svc.recent.2',
            'direction' => 'incoming',
            'type' => 'text',
            'content' => 'recent two',
            'status' => 'read',
            'is_read' => true,
            'created_at' => now()->subMonths(2),
            'updated_at' => now()->subMonths(2),
        ]);

        WhatsAppMessage::query()->insert([
            'user_id' => $user->id,
            'phone_number_id' => $account->phone_number_id,
            'contact_id' => $contactOne->id,
            'message_id' => 'svc.old.1',
            'direction' => 'incoming',
            'type' => 'text',
            'content' => 'old',
            'status' => 'read',
            'is_read' => true,
            'created_at' => now()->subMonths(9),
            'updated_at' => now()->subMonths(9),
        ]);

        $selectedCount = $service->getShareableMessageCount(
            $user->id,
            $account->phone_number_id,
            [$contactOne->id],
            true
        );

        $allCount = $service->getShareableMessageCount(
            $user->id,
            $account->phone_number_id,
            [],
            true
        );

        $this->assertSame(1, $selectedCount);
        $this->assertSame(2, $allCount);
    }
}
