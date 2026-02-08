<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppRlsTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;

    private User $user2;

    private WhatsAppAccount $account1;

    private WhatsAppAccount $account2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two users with their own WhatsApp accounts
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();

        $this->account1 = WhatsAppAccount::withoutGlobalScopes()->create([
            'user_id' => $this->user1->id,
            'phone_number_id' => 'phone_001',
            'business_account_id' => 'biz_001',
            'access_token' => 'token_001',
            'is_active' => true,
        ]);

        $this->account2 = WhatsAppAccount::withoutGlobalScopes()->create([
            'user_id' => $this->user2->id,
            'phone_number_id' => 'phone_002',
            'business_account_id' => 'biz_002',
            'access_token' => 'token_002',
            'is_active' => true,
        ]);
    }

    /**
     * Test that user can only see their own contacts.
     */
    public function test_user_can_only_see_their_own_contacts(): void
    {
        // Create contacts for both users
        $contact1 = WhatsAppContact::withoutGlobalScopes()->create([
            'user_id' => $this->user1->id,
            'phone_number_id' => $this->account1->phone_number_id,
            'wa_id' => '628123456789',
            'name' => 'Contact User 1',
        ]);

        $contact2 = WhatsAppContact::withoutGlobalScopes()->create([
            'user_id' => $this->user2->id,
            'phone_number_id' => $this->account2->phone_number_id,
            'wa_id' => '628987654321',
            'name' => 'Contact User 2',
        ]);

        // Act as user 1
        $this->actingAs($this->user1);

        $contacts = WhatsAppContact::all();
        $this->assertCount(1, $contacts);
        $this->assertEquals('Contact User 1', $contacts->first()->name);

        // Act as user 2
        $this->actingAs($this->user2);

        $contacts = WhatsAppContact::all();
        $this->assertCount(1, $contacts);
        $this->assertEquals('Contact User 2', $contacts->first()->name);
    }

    /**
     * Test that user can only see messages for their active account.
     */
    public function test_user_can_only_see_messages_for_active_account(): void
    {
        // Create contacts for both users
        $contact1 = WhatsAppContact::withoutGlobalScopes()->create([
            'user_id' => $this->user1->id,
            'phone_number_id' => $this->account1->phone_number_id,
            'wa_id' => '628123456789',
            'name' => 'Contact User 1',
        ]);

        $contact2 = WhatsAppContact::withoutGlobalScopes()->create([
            'user_id' => $this->user2->id,
            'phone_number_id' => $this->account2->phone_number_id,
            'wa_id' => '628987654321',
            'name' => 'Contact User 2',
        ]);

        // Create messages for both users
        $message1 = WhatsAppMessage::withoutGlobalScopes()->create([
            'user_id' => $this->user1->id,
            'phone_number_id' => $this->account1->phone_number_id,
            'contact_id' => $contact1->id,
            'message_id' => 'msg_001',
            'direction' => 'incoming',
            'type' => 'text',
            'content' => 'Hello from User 1',
            'status' => 'delivered',
        ]);

        $message2 = WhatsAppMessage::withoutGlobalScopes()->create([
            'user_id' => $this->user2->id,
            'phone_number_id' => $this->account2->phone_number_id,
            'contact_id' => $contact2->id,
            'message_id' => 'msg_002',
            'direction' => 'incoming',
            'type' => 'text',
            'content' => 'Hello from User 2',
            'status' => 'delivered',
        ]);

        // Act as user 1
        $this->actingAs($this->user1);

        $messages = WhatsAppMessage::all();
        $this->assertCount(1, $messages);
        $this->assertEquals('Hello from User 1', $messages->first()->content);

        // Act as user 2
        $this->actingAs($this->user2);

        $messages = WhatsAppMessage::all();
        $this->assertCount(1, $messages);
        $this->assertEquals('Hello from User 2', $messages->first()->content);
    }

    /**
     * Test that user can only see templates for their active account.
     */
    public function test_user_can_only_see_templates_for_active_account(): void
    {
        // Create templates for both accounts
        $template1 = WhatsAppTemplate::withoutGlobalScopes()->create([
            'whatsapp_account_id' => $this->account1->id,
            'name' => 'template_user1',
            'language' => 'en',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'body' => 'Hello from template 1',
        ]);

        $template2 = WhatsAppTemplate::withoutGlobalScopes()->create([
            'whatsapp_account_id' => $this->account2->id,
            'name' => 'template_user2',
            'language' => 'en',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'body' => 'Hello from template 2',
        ]);

        // Act as user 1
        $this->actingAs($this->user1);

        $templates = WhatsAppTemplate::all();
        $this->assertCount(1, $templates);
        $this->assertEquals('template_user1', $templates->first()->name);

        // Act as user 2
        $this->actingAs($this->user2);

        $templates = WhatsAppTemplate::all();
        $this->assertCount(1, $templates);
        $this->assertEquals('template_user2', $templates->first()->name);
    }

    /**
     * Test that user with multiple accounts only sees data from active account.
     */
    public function test_user_with_multiple_accounts_only_sees_active_account_data(): void
    {
        // Create second account for user 1 (inactive)
        $account1Inactive = WhatsAppAccount::withoutGlobalScopes()->create([
            'user_id' => $this->user1->id,
            'phone_number_id' => 'phone_001_inactive',
            'business_account_id' => 'biz_001_inactive',
            'access_token' => 'token_001_inactive',
            'is_active' => false,
        ]);

        // Create contacts for both accounts of user 1
        $contactActive = WhatsAppContact::withoutGlobalScopes()->create([
            'user_id' => $this->user1->id,
            'phone_number_id' => $this->account1->phone_number_id,
            'wa_id' => '628123456789',
            'name' => 'Contact Active Account',
        ]);

        $contactInactive = WhatsAppContact::withoutGlobalScopes()->create([
            'user_id' => $this->user1->id,
            'phone_number_id' => $account1Inactive->phone_number_id,
            'wa_id' => '628111222333',
            'name' => 'Contact Inactive Account',
        ]);

        // Create templates for both accounts
        $templateActive = WhatsAppTemplate::withoutGlobalScopes()->create([
            'whatsapp_account_id' => $this->account1->id,
            'name' => 'template_active',
            'language' => 'en',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'body' => 'Active template',
        ]);

        $templateInactive = WhatsAppTemplate::withoutGlobalScopes()->create([
            'whatsapp_account_id' => $account1Inactive->id,
            'name' => 'template_inactive',
            'language' => 'en',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'body' => 'Inactive template',
        ]);

        // Act as user 1
        $this->actingAs($this->user1);

        // Should only see contacts from active account
        $contacts = WhatsAppContact::all();
        $this->assertCount(1, $contacts);
        $this->assertEquals('Contact Active Account', $contacts->first()->name);

        // Should only see templates from active account
        $templates = WhatsAppTemplate::all();
        $this->assertCount(1, $templates);
        $this->assertEquals('template_active', $templates->first()->name);
    }

    /**
     * Test that unauthenticated users see no data.
     */
    public function test_unauthenticated_users_see_no_data(): void
    {
        // Create data for user 1
        $contact = WhatsAppContact::withoutGlobalScopes()->create([
            'user_id' => $this->user1->id,
            'phone_number_id' => $this->account1->phone_number_id,
            'wa_id' => '628123456789',
            'name' => 'Test Contact',
        ]);

        $message = WhatsAppMessage::withoutGlobalScopes()->create([
            'user_id' => $this->user1->id,
            'phone_number_id' => $this->account1->phone_number_id,
            'contact_id' => $contact->id,
            'message_id' => 'msg_test',
            'direction' => 'incoming',
            'type' => 'text',
            'content' => 'Test message',
            'status' => 'delivered',
        ]);

        $template = WhatsAppTemplate::withoutGlobalScopes()->create([
            'whatsapp_account_id' => $this->account1->id,
            'name' => 'test_template',
            'language' => 'en',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'body' => 'Test template body',
        ]);

        // Without authentication, should see no data
        $this->assertCount(0, WhatsAppContact::all());
        $this->assertCount(0, WhatsAppMessage::all());
        $this->assertCount(0, WhatsAppTemplate::all());
    }

    /**
     * Test that user with no active account sees no data.
     */
    public function test_user_with_no_active_account_sees_no_data(): void
    {
        // Deactivate user 1's account
        $this->account1->update(['is_active' => false]);

        // Create data for user 1
        $contact = WhatsAppContact::withoutGlobalScopes()->create([
            'user_id' => $this->user1->id,
            'phone_number_id' => $this->account1->phone_number_id,
            'wa_id' => '628123456789',
            'name' => 'Test Contact',
        ]);

        $template = WhatsAppTemplate::withoutGlobalScopes()->create([
            'whatsapp_account_id' => $this->account1->id,
            'name' => 'test_template',
            'language' => 'en',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'body' => 'Test template body',
        ]);

        // Act as user 1
        $this->actingAs($this->user1);

        // Should see no data because no active account
        $this->assertCount(0, WhatsAppContact::all());
        $this->assertCount(0, WhatsAppTemplate::all());
    }
}
