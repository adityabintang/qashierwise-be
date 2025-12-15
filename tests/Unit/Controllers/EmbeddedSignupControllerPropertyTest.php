<?php

namespace Tests\Unit\Controllers;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\WhatsAppAccountService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for EmbeddedSignupController
 * 
 * Feature: whatsapp-embedded-signup
 */
class EmbeddedSignupControllerPropertyTest extends TestCase
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
     * Generate a random display phone number.
     */
    private function generateDisplayPhoneNumber(): string
    {
        return '+1' . rand(1000000000, 9999999999);
    }

    /**
     * Generate a random business name.
     */
    private function generateBusinessName(): string
    {
        $names = ['Acme Corp', 'Tech Solutions', 'Global Services', 'Digital Hub', 'Smart Business'];
        return $names[array_rand($names)] . ' ' . rand(1, 999);
    }

    /**
     * Generate a random quality rating.
     */
    private function generateQualityRating(): string
    {
        $ratings = ['GREEN', 'YELLOW', 'RED'];
        return $ratings[array_rand($ratings)];
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 7: Account Status Completeness
     * Validates: Requirements 4.1, 4.3
     * 
     * For any user with a connected WhatsApp account, retrieving account status 
     * SHALL return phone_number, display_name, verified_name, quality_rating, 
     * is_active, and coexistence_enabled fields.
     */
    #[Test]
    public function account_status_contains_all_required_fields(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::bool(), // coexistence_enabled
                Generators::elements('GREEN', 'YELLOW', 'RED') // quality_rating
            )
            ->then(function (bool $coexistenceEnabled, string $qualityRating) {
                $service = new WhatsAppAccountService();
                $user = User::factory()->create();

                $phoneNumber = $this->generateDisplayPhoneNumber();
                $verifiedName = $this->generateBusinessName();

                // Create account with all fields
                $account = WhatsAppAccount::create([
                    'user_id' => $user->id,
                    'phone_number_id' => $this->generatePhoneNumberId(),
                    'business_account_id' => $this->generateWabaId(),
                    'waba_id' => $this->generateWabaId(),
                    'access_token' => $this->generateAccessToken(),
                    'display_phone_number' => $phoneNumber,
                    'name' => $verifiedName,
                    'quality_rating' => $qualityRating,
                    'is_active' => true,
                    'coexistence_enabled' => $coexistenceEnabled,
                    'connection_method' => 'embedded_signup',
                ]);

                // Get account status
                $status = $service->getAccountStatus($user->id);

                // Property: Status must contain all required fields
                $this->assertNotNull($status, 'Status should not be null for connected account');
                
                // Check all required fields exist
                $this->assertArrayHasKey('phone_number', $status, 'Status must contain phone_number');
                $this->assertArrayHasKey('display_name', $status, 'Status must contain display_name');
                $this->assertArrayHasKey('verified_name', $status, 'Status must contain verified_name');
                $this->assertArrayHasKey('quality_rating', $status, 'Status must contain quality_rating');
                $this->assertArrayHasKey('is_active', $status, 'Status must contain is_active');
                $this->assertArrayHasKey('coexistence_enabled', $status, 'Status must contain coexistence_enabled');

                // Verify field values match what was stored
                $this->assertEquals($phoneNumber, $status['phone_number'], 'phone_number should match');
                $this->assertEquals($phoneNumber, $status['display_name'], 'display_name should match');
                $this->assertEquals($verifiedName, $status['verified_name'], 'verified_name should match');
                $this->assertEquals($qualityRating, $status['quality_rating'], 'quality_rating should match');
                $this->assertTrue($status['is_active'], 'is_active should be true');
                $this->assertEquals($coexistenceEnabled, $status['coexistence_enabled'], 'coexistence_enabled should match');

                // Clean up
                $account->delete();
                $user->delete();
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 8: Disconnect Deactivates Account
     * Validates: Requirements 4.2
     * 
     * For any connected WhatsApp account, calling disconnect SHALL set is_active 
     * to false and the account SHALL no longer be used for messaging.
     */
    #[Test]
    public function disconnect_deactivates_account(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(1, 5) // Number of users to test
            )
            ->then(function (int $userCount) {
                $service = new WhatsAppAccountService();
                $users = [];
                $accounts = [];

                // Create users with active WhatsApp accounts
                for ($i = 0; $i < $userCount; $i++) {
                    $user = User::factory()->create();
                    $account = WhatsAppAccount::create([
                        'user_id' => $user->id,
                        'phone_number_id' => $this->generatePhoneNumberId(),
                        'business_account_id' => $this->generateWabaId(),
                        'waba_id' => $this->generateWabaId(),
                        'access_token' => $this->generateAccessToken(),
                        'display_phone_number' => $this->generateDisplayPhoneNumber(),
                        'name' => $this->generateBusinessName(),
                        'quality_rating' => $this->generateQualityRating(),
                        'is_active' => true,
                        'coexistence_enabled' => true,
                        'connection_method' => 'embedded_signup',
                    ]);

                    $users[] = $user;
                    $accounts[] = $account;
                }

                // Property: After disconnect, account should be inactive
                foreach ($users as $index => $user) {
                    // Verify account is active before disconnect
                    $this->assertTrue(
                        $service->hasConnectedAccount($user->id),
                        'Account should be connected before disconnect'
                    );

                    // Disconnect the account
                    $result = $service->deactivateAccount($user->id);
                    $this->assertTrue($result, 'deactivateAccount should return true');

                    // Verify account is no longer active
                    $this->assertFalse(
                        $service->hasConnectedAccount($user->id),
                        'Account should not be connected after disconnect'
                    );

                    // Verify getActiveAccount returns null
                    $this->assertNull(
                        $service->getActiveAccount($user->id),
                        'getActiveAccount should return null after disconnect'
                    );

                    // Verify the account record still exists but is inactive
                    $accounts[$index]->refresh();
                    $this->assertFalse(
                        $accounts[$index]->is_active,
                        'Account is_active should be false after disconnect'
                    );
                }

                // Clean up
                foreach ($accounts as $account) {
                    $account->delete();
                }
                foreach ($users as $user) {
                    $user->delete();
                }
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 13: Coexistence Flag Persistence
     * Validates: Requirements 8.2, 8.3
     * 
     * For any WhatsApp account connected via Embedded Signup v4, the coexistence_enabled 
     * flag SHALL be stored as true and SHALL be included in account status responses.
     */
    #[Test]
    public function coexistence_flag_persisted_and_returned(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::bool() // coexistence_enabled value
            )
            ->then(function (bool $coexistenceEnabled) {
                $service = new WhatsAppAccountService();
                $user = User::factory()->create();

                // Create account with specific coexistence flag
                $account = WhatsAppAccount::create([
                    'user_id' => $user->id,
                    'phone_number_id' => $this->generatePhoneNumberId(),
                    'business_account_id' => $this->generateWabaId(),
                    'waba_id' => $this->generateWabaId(),
                    'access_token' => $this->generateAccessToken(),
                    'display_phone_number' => $this->generateDisplayPhoneNumber(),
                    'name' => $this->generateBusinessName(),
                    'quality_rating' => $this->generateQualityRating(),
                    'is_active' => true,
                    'coexistence_enabled' => $coexistenceEnabled,
                    'connection_method' => 'embedded_signup',
                ]);

                // Property 1: Coexistence flag should be persisted correctly
                $account->refresh();
                $this->assertEquals(
                    $coexistenceEnabled,
                    $account->coexistence_enabled,
                    'coexistence_enabled should be persisted correctly in database'
                );

                // Property 2: Coexistence flag should be included in status response
                $status = $service->getAccountStatus($user->id);
                $this->assertArrayHasKey(
                    'coexistence_enabled',
                    $status,
                    'coexistence_enabled should be included in status response'
                );
                $this->assertEquals(
                    $coexistenceEnabled,
                    $status['coexistence_enabled'],
                    'coexistence_enabled in status should match stored value'
                );

                // Clean up
                $account->delete();
                $user->delete();
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 7: Account Status Completeness
     * Validates: Requirements 4.1, 4.3
     * 
     * Test that account status returns null for users without accounts.
     */
    #[Test]
    public function account_status_returns_null_for_no_account(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(1, 5) // Number of users to test
            )
            ->then(function (int $userCount) {
                $service = new WhatsAppAccountService();
                $users = [];

                // Create users WITHOUT WhatsApp accounts
                for ($i = 0; $i < $userCount; $i++) {
                    $users[] = User::factory()->create();
                }

                // Property: Status should be null for users without accounts
                foreach ($users as $user) {
                    $status = $service->getAccountStatus($user->id);
                    $this->assertNull(
                        $status,
                        'Account status should be null for user without account'
                    );
                }

                // Clean up
                foreach ($users as $user) {
                    $user->delete();
                }
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 8: Disconnect Deactivates Account
     * Validates: Requirements 4.2
     * 
     * Test that disconnect returns false for users without accounts.
     */
    #[Test]
    public function disconnect_returns_false_for_no_account(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(1, 5) // Number of users to test
            )
            ->then(function (int $userCount) {
                $service = new WhatsAppAccountService();
                $users = [];

                // Create users WITHOUT WhatsApp accounts
                for ($i = 0; $i < $userCount; $i++) {
                    $users[] = User::factory()->create();
                }

                // Property: Disconnect should return false for users without accounts
                foreach ($users as $user) {
                    $result = $service->deactivateAccount($user->id);
                    $this->assertFalse(
                        $result,
                        'deactivateAccount should return false for user without account'
                    );
                }

                // Clean up
                foreach ($users as $user) {
                    $user->delete();
                }
            });
    }
}
