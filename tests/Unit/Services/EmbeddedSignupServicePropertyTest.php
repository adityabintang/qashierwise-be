<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\EmbeddedSignupService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for EmbeddedSignupService
 *
 * Feature: whatsapp-embedded-signup
 */
class EmbeddedSignupServicePropertyTest extends TestCase
{
    use RefreshDatabase;
    use TestTrait;

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
     * Feature: whatsapp-embedded-signup, Property 1: Credential Storage Completeness
     * Validates: Requirements 2.1, 2.4
     *
     * For any valid credentials response from Meta API containing phone_number_id,
     * waba_id, access_token, display_name, verified_name, and quality_rating,
     * storing these credentials SHALL result in a WhatsAppAccount record
     * containing all these fields with correct values.
     */
    #[Test]
    public function credential_storage_contains_all_required_fields(): void
    {
        $qualityRatings = ['GREEN', 'YELLOW', 'RED'];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($qualityRatings)
            )
            ->then(function (string $qualityRating) {
                // Create a user
                $user = User::factory()->create();

                // Generate credentials
                $credentials = [
                    'phone_number_id' => $this->generatePhoneNumberId(),
                    'waba_id' => $this->generateWabaId(),
                    'access_token' => $this->generateAccessToken(),
                    'display_name' => '+1 555 '.rand(100, 999).' '.rand(1000, 9999),
                    'verified_name' => 'Test Business '.uniqid(),
                    'quality_rating' => $qualityRating,
                    'coexistence_enabled' => true,
                ];

                // Store credentials using the service
                $service = new EmbeddedSignupService;
                $account = $service->storeCredentials($user->id, $credentials);

                // Property: All required fields must be stored correctly
                // Note: API uses display_name/verified_name, DB uses display_phone_number/name
                $this->assertEquals($user->id, $account->user_id);
                $this->assertEquals($credentials['phone_number_id'], $account->phone_number_id);
                $this->assertEquals($credentials['waba_id'], $account->waba_id);
                $this->assertEquals($credentials['access_token'], $account->access_token);
                $this->assertEquals($credentials['display_name'], $account->display_phone_number);
                $this->assertEquals($credentials['verified_name'], $account->name);
                $this->assertEquals($credentials['quality_rating'], $account->quality_rating);
                $this->assertTrue($account->is_active);
                $this->assertTrue($account->coexistence_enabled);
                $this->assertEquals('embedded_signup', $account->connection_method);

                // Verify the record exists in database
                $this->assertDatabaseHas('whatsapp_accounts', [
                    'id' => $account->id,
                    'user_id' => $user->id,
                    'phone_number_id' => $credentials['phone_number_id'],
                    'waba_id' => $credentials['waba_id'],
                ]);

                // Clean up
                $account->delete();
                $user->delete();
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 3: Upsert Prevents Duplicates
     * Validates: Requirements 2.3
     *
     * For any user who already has a WhatsAppAccount record, storing new credentials
     * SHALL result in exactly one WhatsAppAccount record for that user (update, not insert).
     */
    #[Test]
    public function upsert_prevents_duplicate_accounts(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(2, 5) // Number of times to store credentials
            )
            ->then(function (int $storeCount) {
                // Create a user
                $user = User::factory()->create();
                $service = new EmbeddedSignupService;

                // Store credentials multiple times
                for ($i = 0; $i < $storeCount; $i++) {
                    $credentials = [
                        'phone_number_id' => $this->generatePhoneNumberId(),
                        'waba_id' => $this->generateWabaId(),
                        'access_token' => $this->generateAccessToken(),
                        'display_name' => '+1 555 '.rand(100, 999).' '.rand(1000, 9999),
                        'verified_name' => 'Test Business '.uniqid(),
                        'quality_rating' => 'GREEN',
                        'coexistence_enabled' => true,
                    ];

                    $service->storeCredentials($user->id, $credentials);
                }

                // Property: There should be exactly ONE account for this user
                $accountCount = WhatsAppAccount::where('user_id', $user->id)->count();

                $this->assertEquals(
                    1,
                    $accountCount,
                    "Expected exactly 1 account for user after {$storeCount} stores, got {$accountCount}"
                );

                // Clean up
                WhatsAppAccount::where('user_id', $user->id)->delete();
                $user->delete();
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 1: Credential Storage Completeness
     * Validates: Requirements 2.1, 2.4
     *
     * Test that credentials with various display name formats are stored correctly.
     */
    #[Test]
    public function credential_storage_handles_various_display_names(): void
    {
        $displayNames = [
            '+1 555 123 4567',
            '+44 20 7946 0958',
            '+62 812 3456 7890',
            '+1 (555) 123-4567',
            '15551234567',
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($displayNames)
            )
            ->then(function (string $displayName) {
                $user = User::factory()->create();
                $service = new EmbeddedSignupService;

                $credentials = [
                    'phone_number_id' => $this->generatePhoneNumberId(),
                    'waba_id' => $this->generateWabaId(),
                    'access_token' => $this->generateAccessToken(),
                    'display_name' => $displayName,
                    'verified_name' => 'Test Business',
                    'quality_rating' => 'GREEN',
                    'coexistence_enabled' => true,
                ];

                $account = $service->storeCredentials($user->id, $credentials);

                // Property: Display name should be stored exactly as provided
                // Note: API uses display_name, DB uses display_phone_number
                $this->assertEquals($displayName, $account->display_phone_number);

                // Clean up
                $account->delete();
                $user->delete();
            });
    }
}
