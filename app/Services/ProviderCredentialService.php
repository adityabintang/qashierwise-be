<?php

namespace App\Services;

use App\Models\PaymentProviderCredential;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service for managing payment provider credentials.
 *
 * Handles storing, updating, deleting, and managing active provider credentials
 * with encryption and validation.
 */
class ProviderCredentialService
{
    /**
     * Create a new provider credential service instance.
     */
    public function __construct(
        private EncryptionService $encryption,
        private ProviderValidationService $validation
    ) {}

    /**
     * Store new provider credentials with encryption and validation.
     *
     * @param  User  $user  The user storing the credentials
     * @param  string  $provider  The provider name (doku, xendit, midtrans, duitku)
     * @param  array  $credentials  The provider-specific credentials
     * @return PaymentProviderCredential The stored credential record
     *
     * @throws InvalidArgumentException If provider is invalid or credentials are empty
     * @throws RuntimeException If storage fails
     */
    public function storeCredentials(
        User $user,
        string $provider,
        array $credentials
    ): PaymentProviderCredential {
        // Validate provider
        if (! PaymentProviderCredential::isValidProvider($provider)) {
            throw new InvalidArgumentException("Invalid provider: {$provider}");
        }

        if (empty($credentials)) {
            throw new InvalidArgumentException('Credentials cannot be empty');
        }

        try {
            return DB::transaction(function () use ($user, $provider, $credentials) {
                // Encrypt credentials
                $encryptedCredentials = $this->encryption->encryptCredentials($user, $credentials);

                // Create or update credential record
                $credential = PaymentProviderCredential::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'provider' => $provider,
                    ],
                    [
                        'credentials_encrypted' => $encryptedCredentials,
                        'connection_status' => PaymentProviderCredential::STATUS_PENDING,
                        'validation_error' => null,
                        'last_validated_at' => null,
                    ]
                );

                // Validate credentials asynchronously (don't block on validation)
                // The validation service will update the connection status
                try {
                    $validationResult = $this->validation->validateCredentials($credential);
                } catch (\Exception $e) {
                    // Log validation error but don't fail the storage
                    \Log::warning('Failed to validate credentials during storage', [
                        'user_id' => $user->id,
                        'provider' => $provider,
                        'error' => $e->getMessage(),
                    ]);
                }

                return $credential->fresh();
            });
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to store credentials: {$e->getMessage()}");
        }
    }

    /**
     * Update existing provider credentials with re-encryption.
     *
     * @param  PaymentProviderCredential  $credential  The credential to update
     * @param  array  $newCredentials  The new credentials
     * @return bool True if update was successful
     *
     * @throws InvalidArgumentException If credentials are empty
     * @throws RuntimeException If update fails
     */
    public function updateCredentials(
        PaymentProviderCredential $credential,
        array $newCredentials
    ): bool {
        if (empty($newCredentials)) {
            throw new InvalidArgumentException('Credentials cannot be empty');
        }

        try {
            return DB::transaction(function () use ($credential, $newCredentials) {
                // Re-encrypt with the same user's encryption key
                $encryptedCredentials = $this->encryption->encryptCredentials(
                    $credential->user,
                    $newCredentials
                );

                // Update credential record
                $credential->update([
                    'credentials_encrypted' => $encryptedCredentials,
                    'connection_status' => PaymentProviderCredential::STATUS_PENDING,
                    'validation_error' => null,
                    'last_validated_at' => null,
                ]);

                // Validate updated credentials
                try {
                    $this->validation->validateCredentials($credential);
                } catch (\Exception $e) {
                    \Log::warning('Failed to validate credentials during update', [
                        'credential_id' => $credential->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                return true;
            });
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to update credentials: {$e->getMessage()}");
        }
    }

    /**
     * Delete provider credentials with secure wipe.
     *
     * @param  PaymentProviderCredential  $credential  The credential to delete
     * @return bool True if deletion was successful
     *
     * @throws RuntimeException If deletion fails
     */
    public function deleteCredentials(PaymentProviderCredential $credential): bool
    {
        try {
            return DB::transaction(function () use ($credential) {
                $wasActive = $credential->is_active;
                $userId = $credential->user_id;
                $provider = $credential->provider;

                // Securely wipe the encrypted data before deletion
                $credential->update([
                    'credentials_encrypted' => '',
                    'is_active' => false,
                ]);

                // Delete the credential
                $deleted = $credential->delete();

                // If this was the active provider, log a warning
                if ($wasActive) {
                    \Log::info('Active provider deleted', [
                        'user_id' => $userId,
                        'provider' => $provider,
                    ]);
                }

                return $deleted;
            });
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to delete credentials: {$e->getMessage()}");
        }
    }

    /**
     * Set a provider as active with constraint enforcement.
     * Ensures only one provider is active at a time and validates credentials exist.
     *
     * @param  User  $user  The user setting the active provider
     * @param  string  $provider  The provider to activate
     * @return bool True if activation was successful
     *
     * @throws InvalidArgumentException If provider is invalid or credentials don't exist
     * @throws RuntimeException If activation fails
     */
    public function setActiveProvider(User $user, string $provider): bool
    {
        // Validate provider
        if (! PaymentProviderCredential::isValidProvider($provider)) {
            throw new InvalidArgumentException("Invalid provider: {$provider}");
        }

        try {
            return DB::transaction(function () use ($user, $provider) {
                // Find the credential for this provider
                $credential = PaymentProviderCredential::where('user_id', $user->id)
                    ->where('provider', $provider)
                    ->first();

                if (! $credential) {
                    throw new InvalidArgumentException(
                        "No credentials found for provider: {$provider}"
                    );
                }

                // Deactivate all other providers for this user
                PaymentProviderCredential::where('user_id', $user->id)
                    ->where('provider', '!=', $provider)
                    ->update(['is_active' => false]);

                // Activate the selected provider
                $credential->update(['is_active' => true]);

                return true;
            });
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to set active provider: {$e->getMessage()}");
        }
    }

    /**
     * Get the active provider for a user.
     *
     * @param  User  $user  The user to get the active provider for
     * @return PaymentProviderCredential|null The active provider credential or null
     */
    public function getActiveProvider(User $user): ?PaymentProviderCredential
    {
        return PaymentProviderCredential::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all configured providers for a user.
     *
     * @param  User  $user  The user to get providers for
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserProviders(User $user)
    {
        return PaymentProviderCredential::where('user_id', $user->id)
            ->orderBy('is_active', 'desc')
            ->orderBy('provider')
            ->get();
    }

    /**
     * Check if a user has any configured providers.
     *
     * @param  User  $user  The user to check
     * @return bool True if user has at least one configured provider
     */
    public function hasConfiguredProviders(User $user): bool
    {
        return PaymentProviderCredential::where('user_id', $user->id)->exists();
    }

    /**
     * Decrypt credentials for use in API calls.
     * This method should only be called when credentials are needed for provider API calls.
     *
     * @param  PaymentProviderCredential  $credential  The credential to decrypt
     * @return array The decrypted credentials
     *
     * @throws RuntimeException If decryption fails
     */
    public function decryptCredentials(PaymentProviderCredential $credential): array
    {
        try {
            return $this->encryption->decryptCredentials(
                $credential->user,
                $credential->credentials_encrypted
            );
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to decrypt credentials: {$e->getMessage()}");
        }
    }
}
