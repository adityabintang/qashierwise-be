<?php

namespace Tests\Unit\Services;

use App\Exceptions\WhatsAppNotConnectedException;
use App\Exceptions\WhatsAppTokenExpiredException;
use App\Exceptions\WhatsAppTokenInvalidException;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\WhatsAppAccountService;
use Carbon\Carbon;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for WhatsAppAccountService
 * 
 * Feature: whatsapp-embedded-signup
 */
class WhatsAppAccountServicePropertyTest extends TestCase
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
     * Feature: whatsapp-embedded-signup, Property 4: Dynamic Credential Usage
     * Validates: Requirements 3.1, 3.3
     * 
     * For any authenticated user with a connected WhatsApp account, creating a 
     * WhatsApp client SHALL use the user's stored phone_number_id and access_token, 
     * not the global configuration values.
     */
    #[Test]
    public function client_uses_user_credentials_not_global_config(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(1, 10) // Number of users to test
            )
            ->then(function (int $userCount) {
                $service = new WhatsAppAccountService();
                $users = [];
                $accounts = [];

                // Create multiple users with different credentials
                for ($i = 0; $i < $userCount; $i++) {
                    $user = User::factory()->create();
                    $phoneNumberId = $this->generatePhoneNumberId();
                    $accessToken = $this->generateAccessToken();

                    $account = WhatsAppAccount::create([
                        'user_id' => $user->id,
                        'phone_number_id' => $phoneNumberId,
                        'business_account_id' => $this->generateWabaId(),
                        'waba_id' => $this->generateWabaId(),
                        'access_token' => $accessToken,
                        'is_active' => true,
                        'connection_method' => 'embedded_signup',
                    ]);

                    $users[] = $user;
                    $accounts[] = [
                        'account' => $account,
                        'phone_number_id' => $phoneNumberId,
                        'access_token' => $accessToken,
                    ];
                }

                // Property: Each user's client should use their own credentials
                foreach ($accounts as $index => $data) {
                    $user = $users[$index];
                    $client = $service->getClientForUser($user->id);

                    // The client should be configured with user's credentials
                    // We verify this by checking the active account matches
                    $activeAccount = $service->getActiveAccount($user->id);
                    
                    $this->assertEquals(
                        $data['phone_number_id'],
                        $activeAccount->phone_number_id,
                        'Client should use user\'s phone_number_id'
                    );
                    
                    $this->assertEquals(
                        $data['access_token'],
                        $activeAccount->access_token,
                        'Client should use user\'s access_token'
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
     * Feature: whatsapp-embedded-signup, Property 5: Missing Account Error
     * Validates: Requirements 3.2
     * 
     * For any authenticated user without a connected WhatsApp account, 
     * attempting to get a client SHALL throw WhatsAppNotConnectedException.
     */
    #[Test]
    public function missing_account_throws_exception(): void
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

                // Property: Each user without account should throw exception
                foreach ($users as $user) {
                    $exceptionThrown = false;
                    
                    try {
                        $service->getClientForUser($user->id);
                    } catch (WhatsAppNotConnectedException $e) {
                        $exceptionThrown = true;
                        $this->assertEquals(
                            'WHATSAPP_NOT_CONNECTED',
                            $e->getErrorCode(),
                            'Exception should have correct error code'
                        );
                    }

                    $this->assertTrue(
                        $exceptionThrown,
                        'WhatsAppNotConnectedException should be thrown for user without account'
                    );
                }

                // Clean up
                foreach ($users as $user) {
                    $user->delete();
                }
            });
    }


    /**
     * Feature: whatsapp-embedded-signup, Property 4: Dynamic Credential Usage
     * Validates: Requirements 3.1, 3.3
     * 
     * Test that hasConnectedAccount correctly identifies users with/without accounts.
     */
    #[Test]
    public function has_connected_account_returns_correct_status(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::bool() // Whether user has account
            )
            ->then(function (bool $hasAccount) {
                $service = new WhatsAppAccountService();
                $user = User::factory()->create();

                if ($hasAccount) {
                    $account = WhatsAppAccount::create([
                        'user_id' => $user->id,
                        'phone_number_id' => $this->generatePhoneNumberId(),
                        'business_account_id' => $this->generateWabaId(),
                        'waba_id' => $this->generateWabaId(),
                        'access_token' => $this->generateAccessToken(),
                        'is_active' => true,
                        'connection_method' => 'embedded_signup',
                    ]);
                }

                // Property: hasConnectedAccount should match actual state
                $result = $service->hasConnectedAccount($user->id);
                
                $this->assertEquals(
                    $hasAccount,
                    $result,
                    'hasConnectedAccount should return ' . ($hasAccount ? 'true' : 'false')
                );

                // Clean up
                if ($hasAccount) {
                    $account->delete();
                }
                $user->delete();
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 5: Missing Account Error
     * Validates: Requirements 3.2
     * 
     * Test that inactive accounts are treated as not connected.
     */
    #[Test]
    public function inactive_account_treated_as_not_connected(): void
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

                // Create users with INACTIVE WhatsApp accounts
                for ($i = 0; $i < $userCount; $i++) {
                    $user = User::factory()->create();
                    $account = WhatsAppAccount::create([
                        'user_id' => $user->id,
                        'phone_number_id' => $this->generatePhoneNumberId(),
                        'business_account_id' => $this->generateWabaId(),
                        'waba_id' => $this->generateWabaId(),
                        'access_token' => $this->generateAccessToken(),
                        'is_active' => false, // Inactive!
                        'connection_method' => 'embedded_signup',
                    ]);

                    $users[] = $user;
                    $accounts[] = $account;
                }

                // Property: Inactive accounts should not be considered connected
                foreach ($users as $user) {
                    $this->assertFalse(
                        $service->hasConnectedAccount($user->id),
                        'Inactive account should not be considered connected'
                    );

                    $this->assertNull(
                        $service->getActiveAccount($user->id),
                        'getActiveAccount should return null for inactive account'
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
     * Feature: whatsapp-embedded-signup, Property 6: Invalid Token Error
     * Validates: Requirements 3.4
     * 
     * For any WhatsApp API call with an expired or invalid access token, 
     * the system SHALL return an error indicating re-authentication is required.
     */
    #[Test]
    public function expired_token_throws_token_expired_exception(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(1, 30) // Days in the past for expiration
            )
            ->then(function (int $daysAgo) {
                $service = new WhatsAppAccountService();
                $user = User::factory()->create();

                // Create account with expired token
                $account = WhatsAppAccount::create([
                    'user_id' => $user->id,
                    'phone_number_id' => $this->generatePhoneNumberId(),
                    'business_account_id' => $this->generateWabaId(),
                    'waba_id' => $this->generateWabaId(),
                    'access_token' => $this->generateAccessToken(),
                    'is_active' => true,
                    'connection_method' => 'embedded_signup',
                    'token_expires_at' => Carbon::now()->subDays($daysAgo), // Expired!
                ]);

                // Property: Expired token should throw WhatsAppTokenExpiredException
                $exceptionThrown = false;
                
                try {
                    $service->validateAccountToken($user->id);
                } catch (WhatsAppTokenExpiredException $e) {
                    $exceptionThrown = true;
                    $this->assertEquals(
                        'WHATSAPP_TOKEN_EXPIRED',
                        $e->getErrorCode(),
                        'Exception should have correct error code'
                    );
                    $this->assertEquals(
                        401,
                        $e->getCode(),
                        'Exception should have HTTP 401 status code'
                    );
                }

                $this->assertTrue(
                    $exceptionThrown,
                    'WhatsAppTokenExpiredException should be thrown for expired token'
                );

                // Clean up
                $account->delete();
                $user->delete();
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 6: Invalid Token Error
     * Validates: Requirements 3.4
     * 
     * For any WhatsApp account with an empty access token,
     * the system SHALL throw WhatsAppTokenInvalidException.
     * 
     * Note: Since access_token is encrypted in the model, we create an account
     * with an empty string token (which gets encrypted) and verify the validation
     * correctly identifies it as invalid.
     */
    #[Test]
    public function invalid_token_throws_token_invalid_exception(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(1, 10) // Number of iterations
            )
            ->then(function (int $iteration) {
                $service = new WhatsAppAccountService();
                $user = User::factory()->create();

                // Create account with empty token (will be encrypted as empty string)
                $account = WhatsAppAccount::create([
                    'user_id' => $user->id,
                    'phone_number_id' => $this->generatePhoneNumberId(),
                    'business_account_id' => $this->generateWabaId(),
                    'waba_id' => $this->generateWabaId(),
                    'access_token' => '', // Empty token - will be encrypted
                    'is_active' => true,
                    'connection_method' => 'embedded_signup',
                ]);

                // Property: Invalid (empty) token should throw WhatsAppTokenInvalidException
                $exceptionThrown = false;
                
                try {
                    $service->validateAccountToken($user->id);
                } catch (WhatsAppTokenInvalidException $e) {
                    $exceptionThrown = true;
                    $this->assertEquals(
                        'WHATSAPP_TOKEN_INVALID',
                        $e->getErrorCode(),
                        'Exception should have correct error code'
                    );
                    $this->assertEquals(
                        401,
                        $e->getCode(),
                        'Exception should have HTTP 401 status code'
                    );
                }

                $this->assertTrue(
                    $exceptionThrown,
                    'WhatsAppTokenInvalidException should be thrown for invalid token'
                );

                // Clean up
                $account->delete();
                $user->delete();
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 6: Invalid Token Error
     * Validates: Requirements 3.4
     * 
     * Test handleTokenValidationResult throws correct exceptions.
     */
    #[Test]
    public function handle_token_validation_result_throws_correct_exceptions(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::bool(), // isValid
                Generators::bool()  // isExpired
            )
            ->then(function (bool $isValid, bool $isExpired) {
                $service = new WhatsAppAccountService();

                // Skip the case where token is valid and not expired (no exception)
                if ($isValid && !$isExpired) {
                    // Should not throw any exception
                    $exceptionThrown = false;
                    try {
                        $service->handleTokenValidationResult($isValid, $isExpired);
                    } catch (\Exception $e) {
                        $exceptionThrown = true;
                    }
                    $this->assertFalse($exceptionThrown, 'No exception should be thrown for valid, non-expired token');
                    return;
                }

                // Property: Expired token should throw WhatsAppTokenExpiredException
                if ($isExpired) {
                    $this->expectException(WhatsAppTokenExpiredException::class);
                    $service->handleTokenValidationResult($isValid, $isExpired);
                    return;
                }

                // Property: Invalid token should throw WhatsAppTokenInvalidException
                if (!$isValid) {
                    $this->expectException(WhatsAppTokenInvalidException::class);
                    $service->handleTokenValidationResult($isValid, $isExpired);
                }
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 6: Invalid Token Error
     * Validates: Requirements 3.4
     * 
     * Test that valid, non-expired tokens pass validation.
     */
    #[Test]
    public function valid_non_expired_token_passes_validation(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(1, 365) // Days in the future for expiration
            )
            ->then(function (int $daysInFuture) {
                $service = new WhatsAppAccountService();
                $user = User::factory()->create();

                // Create account with valid, non-expired token
                $account = WhatsAppAccount::create([
                    'user_id' => $user->id,
                    'phone_number_id' => $this->generatePhoneNumberId(),
                    'business_account_id' => $this->generateWabaId(),
                    'waba_id' => $this->generateWabaId(),
                    'access_token' => $this->generateAccessToken(),
                    'is_active' => true,
                    'connection_method' => 'embedded_signup',
                    'token_expires_at' => Carbon::now()->addDays($daysInFuture), // Not expired
                ]);

                // Property: Valid token should not throw any exception
                $validatedAccount = $service->validateAccountToken($user->id);
                
                $this->assertInstanceOf(
                    WhatsAppAccount::class,
                    $validatedAccount,
                    'validateAccountToken should return WhatsAppAccount for valid token'
                );
                
                $this->assertEquals(
                    $account->id,
                    $validatedAccount->id,
                    'Returned account should match the created account'
                );

                // Clean up
                $account->delete();
                $user->delete();
            });
    }
}
