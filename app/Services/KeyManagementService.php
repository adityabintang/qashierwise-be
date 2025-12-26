<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEncryptionKey;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class KeyManagementService
{
    /**
     * Get the encryption key for a user.
     * If the key doesn't exist, it will be generated and stored.
     *
     * @param User $user The user to get the encryption key for
     * @return string The decrypted user encryption key
     * @throws RuntimeException If key retrieval or generation fails
     */
    public function getUserEncryptionKey(User $user): string
    {
        $keyRecord = UserEncryptionKey::where('user_id', $user->id)->first();

        if ($keyRecord === null) {
            // Generate and store a new key if it doesn't exist
            return $this->generateUserKey($user);
        }

        try {
            // Decrypt the stored key using Laravel's master key (APP_KEY)
            return Crypt::decryptString($keyRecord->encryption_key_encrypted);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to decrypt user encryption key: ' . $e->getMessage());
        }
    }

    /**
     * Generate a new encryption key for a user using secure random bytes.
     *
     * @param User $user The user to generate the key for
     * @return string The generated encryption key
     * @throws RuntimeException If key generation or storage fails
     */
    public function generateUserKey(User $user): string
    {
        try {
            // Generate a secure random 32-byte (256-bit) key
            $key = Str::random(32);

            // Store the key
            $this->storeUserKey($user, $key);

            return $key;
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to generate user encryption key: ' . $e->getMessage());
        }
    }

    /**
     * Store a user's encryption key with master key encryption.
     *
     * @param User $user The user to store the key for
     * @param string $key The encryption key to store
     * @return bool True if storage was successful
     * @throws InvalidArgumentException If the key is invalid
     * @throws RuntimeException If storage fails
     */
    public function storeUserKey(User $user, string $key): bool
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Encryption key cannot be empty');
        }

        if (strlen($key) !== 32) {
            throw new InvalidArgumentException('Encryption key must be exactly 32 bytes');
        }

        try {
            // Encrypt the key using Laravel's master key (APP_KEY)
            $encryptedKey = Crypt::encryptString($key);

            // Store or update the key record
            UserEncryptionKey::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'encryption_key_encrypted' => $encryptedKey,
                    'key_version' => 1,
                ]
            );

            return true;
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to store user encryption key: ' . $e->getMessage());
        }
    }

    /**
     * Rotate a user's encryption key.
     * This generates a new key and increments the key version.
     *
     * @param User $user The user to rotate the key for
     * @return string The new encryption key
     * @throws RuntimeException If key rotation fails
     */
    public function rotateKey(User $user): string
    {
        try {
            // Generate a new secure random key
            $newKey = Str::random(32);

            // Get the current key record
            $keyRecord = UserEncryptionKey::where('user_id', $user->id)->first();

            // Encrypt the new key
            $encryptedKey = Crypt::encryptString($newKey);

            if ($keyRecord !== null) {
                // Update existing record with incremented version
                $keyRecord->update([
                    'encryption_key_encrypted' => $encryptedKey,
                    'key_version' => $keyRecord->key_version + 1,
                ]);
            } else {
                // Create new record if it doesn't exist
                UserEncryptionKey::create([
                    'user_id' => $user->id,
                    'encryption_key_encrypted' => $encryptedKey,
                    'key_version' => 1,
                ]);
            }

            return $newKey;
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to rotate user encryption key: ' . $e->getMessage());
        }
    }

    /**
     * Derive a key from user-specific data.
     * This is used as an additional layer of security.
     *
     * @param User $user The user to derive the key from
     * @return string The derived key
     */
    private function deriveKeyFromUserSecret(User $user): string
    {
        // Use user ID and email as the basis for key derivation
        // This provides an additional layer of security
        $userSecret = $user->id . '|' . $user->email;
        
        // Use PBKDF2 for key derivation
        return hash_pbkdf2('sha256', $userSecret, config('app.key'), 10000, 32, true);
    }
}
