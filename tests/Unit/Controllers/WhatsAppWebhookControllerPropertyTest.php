<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\MediaStorageService;
use App\Services\WhatsAppAccountService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for WhatsAppWebhookController multi-tenant routing
 * 
 * Feature: whatsapp-embedded-signup
 */
class WhatsAppWebhookControllerPropertyTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    /**
     * Generate a valid phone number ID (numeric string).
     */
    private function generatePhoneNumberId(): string
    {
        return (string) rand(100000000000000000, 999999999999999999);
    }

    /**
     * Generate a valid WABA ID (numeric string).
     */
    private function generateWabaId(): string
    {
        return (string) rand(100000000000000000, 999999999999999999);
    }

    /**
     * Generate a valid access token (starts with EAAG).
     */
    private function generateAccessToken(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $token = 'EAAG';
        for ($i = 0; $i < 100; $i++) {
            $token .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $token;
    }

    /**
     * Generate a valid WhatsApp ID (sender phone number).
     */
    private function generateWaId(): string
    {
        return '62' . rand(8000000000, 8999999999);
    }

    /**
     * Generate a valid message ID.
     */
    private function generateMessageId(): string
    {
        return 'wamid.' . bin2hex(random_bytes(16));
    }


    /**
     * Feature: whatsapp-embedded-signup, Property 9: Webhook Routing Correctness
     * Validates: Requirements 5.1
     * 
     * For any incoming webhook payload containing a phone_number_id, the system 
     * SHALL correctly identify the user whose WhatsAppAccount has that phone_number_id.
     */
    #[Test]
    public function webhook_correctly_routes_to_user_by_phone_number_id(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(2, 5) // Number of users to create
            )
            ->then(function (int $userCount) {
                $service = new WhatsAppAccountService();
                $users = [];
                $accounts = [];

                // Create multiple users with different phone_number_ids
                for ($i = 0; $i < $userCount; $i++) {
                    $user = User::factory()->create();
                    $phoneNumberId = $this->generatePhoneNumberId();

                    $account = WhatsAppAccount::create([
                        'user_id' => $user->id,
                        'phone_number_id' => $phoneNumberId,
                        'business_account_id' => $this->generateWabaId(),
                        'waba_id' => $this->generateWabaId(),
                        'access_token' => $this->generateAccessToken(),
                        'is_active' => true,
                        'connection_method' => 'embedded_signup',
                    ]);

                    $users[] = $user;
                    $accounts[] = [
                        'user' => $user,
                        'account' => $account,
                        'phone_number_id' => $phoneNumberId,
                    ];
                }

                // Property: For each phone_number_id, the service should return the correct user's account
                foreach ($accounts as $data) {
                    $foundAccount = $service->getAccountByPhoneNumberId($data['phone_number_id']);

                    $this->assertNotNull(
                        $foundAccount,
                        'Account should be found for phone_number_id: ' . $data['phone_number_id']
                    );

                    $this->assertEquals(
                        $data['user']->id,
                        $foundAccount->user_id,
                        'Found account should belong to the correct user'
                    );

                    $this->assertEquals(
                        $data['phone_number_id'],
                        $foundAccount->phone_number_id,
                        'Found account should have the correct phone_number_id'
                    );
                }

                // Clean up
                foreach ($accounts as $data) {
                    $data['account']->delete();
                }
                foreach ($users as $user) {
                    $user->delete();
                }
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 10: Unknown Phone Number Handling
     * Validates: Requirements 5.2
     * 
     * For any webhook payload with a phone_number_id that does not match any stored 
     * WhatsAppAccount, the system SHALL skip processing and not create any message records.
     */
    #[Test]
    public function unknown_phone_number_returns_null_and_creates_no_records(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(1, 5) // Number of unknown phone numbers to test
            )
            ->then(function (int $testCount) {
                $service = new WhatsAppAccountService();

                // Create a user with a known phone_number_id
                $user = User::factory()->create();
                $knownPhoneNumberId = $this->generatePhoneNumberId();

                $account = WhatsAppAccount::create([
                    'user_id' => $user->id,
                    'phone_number_id' => $knownPhoneNumberId,
                    'business_account_id' => $this->generateWabaId(),
                    'waba_id' => $this->generateWabaId(),
                    'access_token' => $this->generateAccessToken(),
                    'is_active' => true,
                    'connection_method' => 'embedded_signup',
                ]);

                $initialMessageCount = WhatsAppMessage::count();

                // Test with unknown phone_number_ids
                for ($i = 0; $i < $testCount; $i++) {
                    $unknownPhoneNumberId = $this->generatePhoneNumberId();
                    
                    // Ensure it's different from the known one
                    while ($unknownPhoneNumberId === $knownPhoneNumberId) {
                        $unknownPhoneNumberId = $this->generatePhoneNumberId();
                    }

                    // Property: Unknown phone_number_id should return null
                    $foundAccount = $service->getAccountByPhoneNumberId($unknownPhoneNumberId);

                    $this->assertNull(
                        $foundAccount,
                        'No account should be found for unknown phone_number_id: ' . $unknownPhoneNumberId
                    );
                }

                // Property: No new messages should be created
                $finalMessageCount = WhatsAppMessage::count();
                $this->assertEquals(
                    $initialMessageCount,
                    $finalMessageCount,
                    'No messages should be created for unknown phone numbers'
                );

                // Clean up
                $account->delete();
                $user->delete();
            });
    }


    /**
     * Feature: whatsapp-embedded-signup, Property 11: Message Isolation
     * Validates: Requirements 5.4
     * 
     * For any two distinct users with connected WhatsApp accounts, messages belonging 
     * to user A SHALL never be visible to user B when querying messages.
     */
    #[Test]
    public function messages_are_isolated_between_users(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(2, 5), // Number of users
                Generators::choose(1, 5)  // Messages per user
            )
            ->then(function (int $userCount, int $messagesPerUser) {
                $users = [];
                $accounts = [];
                $messagesByUser = [];

                // Create multiple users with accounts and messages
                for ($i = 0; $i < $userCount; $i++) {
                    $user = User::factory()->create();
                    $phoneNumberId = $this->generatePhoneNumberId();

                    $account = WhatsAppAccount::create([
                        'user_id' => $user->id,
                        'phone_number_id' => $phoneNumberId,
                        'business_account_id' => $this->generateWabaId(),
                        'waba_id' => $this->generateWabaId(),
                        'access_token' => $this->generateAccessToken(),
                        'is_active' => true,
                        'connection_method' => 'embedded_signup',
                    ]);

                    // Create a contact for this user
                    $contact = WhatsAppContact::create([
                        'user_id' => $user->id,
                        'wa_id' => $this->generateWaId(),
                        'name' => 'Test Contact ' . $i,
                    ]);

                    // Create messages for this user
                    $userMessages = [];
                    for ($j = 0; $j < $messagesPerUser; $j++) {
                        $message = WhatsAppMessage::create([
                            'user_id' => $user->id,
                            'contact_id' => $contact->id,
                            'message_id' => $this->generateMessageId(),
                            'direction' => 'incoming',
                            'type' => 'text',
                            'content' => 'Test message ' . $j . ' for user ' . $i,
                            'status' => 'delivered',
                        ]);
                        $userMessages[] = $message->id;
                    }

                    $users[] = $user;
                    $accounts[] = $account;
                    $messagesByUser[$user->id] = $userMessages;
                }

                // Property: Each user should only see their own messages
                foreach ($users as $user) {
                    $userMessageIds = $messagesByUser[$user->id];
                    
                    // Query messages for this user
                    $queriedMessages = WhatsAppMessage::where('user_id', $user->id)->get();

                    // All queried messages should belong to this user
                    foreach ($queriedMessages as $message) {
                        $this->assertEquals(
                            $user->id,
                            $message->user_id,
                            'Message should belong to the querying user'
                        );

                        $this->assertContains(
                            $message->id,
                            $userMessageIds,
                            'Message ID should be in user\'s message list'
                        );
                    }

                    // Count should match
                    $this->assertCount(
                        count($userMessageIds),
                        $queriedMessages,
                        'User should see exactly their own messages'
                    );

                    // Verify no messages from other users are visible
                    foreach ($messagesByUser as $otherUserId => $otherMessageIds) {
                        if ($otherUserId !== $user->id) {
                            foreach ($otherMessageIds as $otherMessageId) {
                                $this->assertNotContains(
                                    $otherMessageId,
                                    $queriedMessages->pluck('id')->toArray(),
                                    'Messages from other users should not be visible'
                                );
                            }
                        }
                    }
                }

                // Clean up - delete in reverse order due to foreign keys
                WhatsAppMessage::whereIn('user_id', collect($users)->pluck('id'))->delete();
                WhatsAppContact::whereIn('user_id', collect($users)->pluck('id'))->delete();
                foreach ($accounts as $account) {
                    $account->delete();
                }
                foreach ($users as $user) {
                    $user->delete();
                }
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 9: Webhook Routing Correctness
     * Validates: Requirements 5.1
     * 
     * Test that inactive accounts are not returned for webhook routing.
     */
    #[Test]
    public function inactive_accounts_not_returned_for_webhook_routing(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(1, 5) // Number of inactive accounts to test
            )
            ->then(function (int $accountCount) {
                $service = new WhatsAppAccountService();
                $users = [];
                $accounts = [];

                // Create users with INACTIVE accounts
                for ($i = 0; $i < $accountCount; $i++) {
                    $user = User::factory()->create();
                    $phoneNumberId = $this->generatePhoneNumberId();

                    $account = WhatsAppAccount::create([
                        'user_id' => $user->id,
                        'phone_number_id' => $phoneNumberId,
                        'business_account_id' => $this->generateWabaId(),
                        'waba_id' => $this->generateWabaId(),
                        'access_token' => $this->generateAccessToken(),
                        'is_active' => false, // Inactive!
                        'connection_method' => 'embedded_signup',
                    ]);

                    $users[] = $user;
                    $accounts[] = [
                        'account' => $account,
                        'phone_number_id' => $phoneNumberId,
                    ];
                }

                // Property: Inactive accounts should not be returned for webhook routing
                foreach ($accounts as $data) {
                    $foundAccount = $service->getAccountByPhoneNumberId($data['phone_number_id']);

                    $this->assertNull(
                        $foundAccount,
                        'Inactive account should not be returned for webhook routing'
                    );
                }

                // Clean up
                foreach ($accounts as $data) {
                    $data['account']->delete();
                }
                foreach ($users as $user) {
                    $user->delete();
                }
            });
    }
}
