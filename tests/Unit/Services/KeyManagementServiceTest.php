<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\UserEncryptionKey;
use App\Services\KeyManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeyManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected KeyManagementService $keyManagementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyManagementService = new KeyManagementService;
    }

    public function test_can_generate_user_key(): void
    {
        $user = User::factory()->create();

        $key = $this->keyManagementService->generateUserKey($user);

        // Verify key is 32 bytes
        $this->assertEquals(32, strlen($key));

        // Verify key is stored in database
        $this->assertDatabaseHas('user_encryption_keys', [
            'user_id' => $user->id,
            'key_version' => 1,
        ]);
    }

    public function test_can_retrieve_user_encryption_key(): void
    {
        $user = User::factory()->create();

        // Generate a key
        $originalKey = $this->keyManagementService->generateUserKey($user);

        // Retrieve the key
        $retrievedKey = $this->keyManagementService->getUserEncryptionKey($user);

        // Keys should match
        $this->assertEquals($originalKey, $retrievedKey);
    }

    public function test_get_user_encryption_key_generates_if_not_exists(): void
    {
        $user = User::factory()->create();

        // No key exists yet
        $this->assertDatabaseMissing('user_encryption_keys', [
            'user_id' => $user->id,
        ]);

        // Get key should generate one
        $key = $this->keyManagementService->getUserEncryptionKey($user);

        // Verify key was generated and stored
        $this->assertNotEmpty($key);
        $this->assertEquals(32, strlen($key));
        $this->assertDatabaseHas('user_encryption_keys', [
            'user_id' => $user->id,
        ]);
    }

    public function test_different_users_have_different_keys(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $key1 = $this->keyManagementService->getUserEncryptionKey($user1);
        $key2 = $this->keyManagementService->getUserEncryptionKey($user2);

        // Keys should be different
        $this->assertNotEquals($key1, $key2);
    }

    public function test_can_rotate_user_key(): void
    {
        $user = User::factory()->create();

        // Generate initial key
        $originalKey = $this->keyManagementService->generateUserKey($user);

        // Rotate the key
        $newKey = $this->keyManagementService->rotateKey($user);

        // Keys should be different
        $this->assertNotEquals($originalKey, $newKey);

        // New key should be 32 bytes
        $this->assertEquals(32, strlen($newKey));

        // Key version should be incremented
        $keyRecord = UserEncryptionKey::where('user_id', $user->id)->first();
        $this->assertEquals(2, $keyRecord->key_version);

        // Retrieved key should match new key
        $retrievedKey = $this->keyManagementService->getUserEncryptionKey($user);
        $this->assertEquals($newKey, $retrievedKey);
    }

    public function test_store_user_key_validates_key_length(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Encryption key must be exactly 32 bytes');

        $this->keyManagementService->storeUserKey($user, 'short_key');
    }

    public function test_store_user_key_validates_empty_key(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Encryption key cannot be empty');

        $this->keyManagementService->storeUserKey($user, '');
    }
}
