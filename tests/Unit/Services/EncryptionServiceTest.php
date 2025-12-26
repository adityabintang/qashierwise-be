<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\EncryptionService;
use App\Services\KeyManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncryptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EncryptionService $encryptionService;
    protected KeyManagementService $keyManagementService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->keyManagementService = new KeyManagementService();
        $this->encryptionService = new EncryptionService($this->keyManagementService);
    }

    public function test_can_encrypt_and_decrypt_credentials(): void
    {
        // Create a test user
        $user = User::factory()->create();

        // Test credentials
        $credentials = [
            'api_key' => 'test_api_key_12345',
            'secret_key' => 'test_secret_key_67890',
        ];

        // Encrypt credentials
        $encrypted = $this->encryptionService->encryptCredentials($user, $credentials);

        // Verify encrypted data is not empty and is different from original
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals(json_encode($credentials), $encrypted);

        // Decrypt credentials
        $decrypted = $this->encryptionService->decryptCredentials($user, $encrypted);

        // Verify decrypted credentials match original
        $this->assertEquals($credentials, $decrypted);
    }

    public function test_encryption_uses_user_specific_keys(): void
    {
        // Create two different users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $credentials = [
            'api_key' => 'shared_api_key',
            'secret' => 'shared_secret',
        ];

        // Encrypt same credentials for both users
        $encrypted1 = $this->encryptionService->encryptCredentials($user1, $credentials);
        $encrypted2 = $this->encryptionService->encryptCredentials($user2, $credentials);

        // Encrypted data should be different due to different user keys and IVs
        $this->assertNotEquals($encrypted1, $encrypted2);

        // Each user should be able to decrypt their own credentials
        $decrypted1 = $this->encryptionService->decryptCredentials($user1, $encrypted1);
        $decrypted2 = $this->encryptionService->decryptCredentials($user2, $encrypted2);

        $this->assertEquals($credentials, $decrypted1);
        $this->assertEquals($credentials, $decrypted2);
    }

    public function test_cannot_decrypt_with_wrong_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $credentials = ['api_key' => 'test_key'];

        // Encrypt with user1
        $encrypted = $this->encryptionService->encryptCredentials($user1, $credentials);

        // Try to decrypt with user2 - should fail
        $this->expectException(\RuntimeException::class);
        $this->encryptionService->decryptCredentials($user2, $encrypted);
    }

    public function test_empty_credentials_throw_exception(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Credentials cannot be empty');
        
        $this->encryptionService->encryptCredentials($user, []);
    }

    public function test_empty_encrypted_data_throws_exception(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Encrypted data cannot be empty');
        
        $this->encryptionService->decryptCredentials($user, '');
    }
}
