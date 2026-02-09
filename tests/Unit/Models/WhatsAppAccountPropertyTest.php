<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\WhatsAppAccount;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for WhatsAppAccount model
 *
 * Feature: whatsapp-embedded-signup
 */
class WhatsAppAccountPropertyTest extends TestCase
{
    use RefreshDatabase;
    use TestTrait;

    /**
     * Feature: whatsapp-embedded-signup, Property 2: Access Token Encryption
     * Validates: Requirements 2.2
     *
     * For any access token string stored in the database, the raw database value
     * SHALL NOT equal the original plaintext token (encryption is applied).
     */
    #[Test]
    public function access_token_is_encrypted_in_database(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::suchThat(
                    fn ($s) => strlen($s) >= 10 && strlen($s) <= 200,
                    Generators::string()
                )
            )
            ->then(function (string $accessToken) {
                // Create a user for the WhatsApp account
                $user = User::factory()->create();

                // Create a WhatsApp account with the generated access token
                $account = WhatsAppAccount::create([
                    'user_id' => $user->id,
                    'phone_number_id' => 'test_'.uniqid(),
                    'business_account_id' => 'test_business_'.uniqid(),
                    'access_token' => $accessToken,
                    'is_active' => true,
                ]);

                // Get the raw value from the database
                $rawValue = DB::table('whatsapp_accounts')
                    ->where('id', $account->id)
                    ->value('access_token');

                // Property: The raw database value should NOT equal the plaintext token
                $this->assertNotEquals(
                    $accessToken,
                    $rawValue,
                    'Access token should be encrypted in database'
                );

                // Property: When retrieved through the model, it should equal the original
                $retrievedAccount = WhatsAppAccount::withoutGlobalScopes()->find($account->id);
                $this->assertNotNull($retrievedAccount, 'Account should be retrievable without global scopes');
                $this->assertEquals(
                    $accessToken,
                    $retrievedAccount->access_token,
                    'Access token should be decrypted when retrieved through model'
                );

                // Clean up
                $account->delete();
                $user->delete();
            });
    }
}
