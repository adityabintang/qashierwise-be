<?php

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;
use RuntimeException;

class EncryptionService
{
    /**
     * The key management service.
     */
    protected KeyManagementService $keyManager;

    /**
     * Create a new encryption service instance.
     */
    public function __construct(KeyManagementService $keyManager)
    {
        $this->keyManager = $keyManager;
    }

    /**
     * Encrypt credentials using AES-256-CBC with user-specific keys.
     *
     * @param  User  $user  The user whose credentials are being encrypted
     * @param  array  $credentials  The credentials to encrypt
     * @return string The encrypted credentials with IV prepended
     *
     * @throws InvalidArgumentException If credentials are invalid
     * @throws RuntimeException If encryption fails
     */
    public function encryptCredentials(User $user, array $credentials): string
    {
        if (empty($credentials)) {
            throw new InvalidArgumentException('Credentials cannot be empty');
        }

        try {
            // Get the user's encryption key
            $key = $this->keyManager->getUserEncryptionKey($user);

            // Generate a secure random IV (16 bytes for AES-256-CBC)
            $iv = $this->generateIV();

            // Convert credentials array to JSON
            $plaintext = json_encode($credentials);

            if ($plaintext === false) {
                throw new RuntimeException('Failed to encode credentials to JSON');
            }

            // Encrypt using AES-256-CBC
            $encrypted = openssl_encrypt(
                $plaintext,
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($encrypted === false) {
                throw new RuntimeException('Encryption failed: '.openssl_error_string());
            }

            // Prepend IV to encrypted data and base64 encode the result
            // Format: base64(iv + encrypted_data)
            return base64_encode($iv.$encrypted);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to encrypt credentials: '.$e->getMessage());
        }
    }

    /**
     * Decrypt credentials with proper IV handling.
     *
     * @param  User  $user  The user whose credentials are being decrypted
     * @param  string  $encryptedData  The encrypted credentials with IV prepended
     * @return array The decrypted credentials
     *
     * @throws InvalidArgumentException If encrypted data is invalid
     * @throws RuntimeException If decryption fails
     */
    public function decryptCredentials(User $user, string $encryptedData): array
    {
        if (empty($encryptedData)) {
            throw new InvalidArgumentException('Encrypted data cannot be empty');
        }

        try {
            // Get the user's encryption key
            $key = $this->keyManager->getUserEncryptionKey($user);

            // Base64 decode the data
            $decoded = base64_decode($encryptedData, true);

            if ($decoded === false) {
                throw new RuntimeException('Failed to decode encrypted data');
            }

            // Extract IV (first 16 bytes) and encrypted data
            $ivLength = 16;
            if (strlen($decoded) < $ivLength) {
                throw new RuntimeException('Encrypted data is too short to contain IV');
            }

            $iv = substr($decoded, 0, $ivLength);
            $encrypted = substr($decoded, $ivLength);

            // Decrypt using AES-256-CBC
            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                throw new RuntimeException('Decryption failed: '.openssl_error_string());
            }

            // Decode JSON back to array
            $credentials = json_decode($decrypted, true);

            if ($credentials === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Failed to decode decrypted credentials: '.json_last_error_msg());
            }

            return $credentials;
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to decrypt credentials: '.$e->getMessage());
        }
    }

    /**
     * Rotate a user's encryption key and re-encrypt all their credentials.
     * This method should be called by a service that manages credentials.
     *
     * @param  User  $user  The user whose key is being rotated
     * @return string The new encryption key
     *
     * @throws RuntimeException If key rotation fails
     */
    public function rotateUserKey(User $user): string
    {
        try {
            // Generate a new key
            $newKey = $this->keyManager->rotateKey($user);

            // Note: The caller is responsible for re-encrypting all credentials
            // with the new key. This service only handles the key rotation.

            return $newKey;
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to rotate user key: '.$e->getMessage());
        }
    }

    /**
     * Generate a secure random IV for AES-256-CBC.
     *
     * @return string The generated IV (16 bytes)
     *
     * @throws RuntimeException If IV generation fails
     */
    private function generateIV(): string
    {
        // AES-256-CBC requires a 16-byte IV
        $iv = openssl_random_pseudo_bytes(16, $strong);

        if ($iv === false || ! $strong) {
            throw new RuntimeException('Failed to generate secure IV');
        }

        return $iv;
    }

    /**
     * Derive a user-specific key from the user's data.
     * This is used internally by the encryption process.
     *
     * @param  User  $user  The user to derive the key from
     * @return string The derived key
     */
    private function deriveUserKey(User $user): string
    {
        // This method delegates to KeyManagementService
        return $this->keyManager->getUserEncryptionKey($user);
    }
}
