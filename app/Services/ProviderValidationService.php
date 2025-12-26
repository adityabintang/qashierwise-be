<?php

namespace App\Services;

use App\Models\PaymentProviderCredential;
use App\Services\PaymentProviders\ProviderFactory;
use App\DTOs\ValidationResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Service for validating payment provider credentials.
 * 
 * Handles credential validation, connection status updates, and error capture.
 */
class ProviderValidationService
{
    /**
     * Create a new provider validation service instance.
     */
    public function __construct(
        private ProviderFactory $providerFactory,
        private EncryptionService $encryption
    ) {}

    /**
     * Validate credentials by making a test API call to the provider.
     *
     * @param PaymentProviderCredential $credential The credential to validate
     * @return ValidationResult The validation result
     * @throws RuntimeException If validation process fails
     */
    public function validateCredentials(PaymentProviderCredential $credential): ValidationResult
    {
        try {
            // Get the provider implementation
            $provider = $this->providerFactory->make($credential->provider);

            // Decrypt credentials for validation
            $decryptedCredentials = $this->encryption->decryptCredentials(
                $credential->user,
                $credential->credentials_encrypted
            );

            // Validate credentials with the provider
            $validationResult = $provider->validateCredentials($decryptedCredentials);

            // Update connection status based on validation result
            $this->updateConnectionStatus($credential, $validationResult);

            return $validationResult;
        } catch (\Exception $e) {
            // Capture provider error
            $validationResult = ValidationResult::failure(
                $e->getMessage(),
                'validation_exception'
            );

            $this->captureProviderError($credential, $e->getMessage());

            return $validationResult;
        }
    }

    /**
     * Update the connection status of a credential based on validation result.
     *
     * @param PaymentProviderCredential $credential The credential to update
     * @param ValidationResult $validationResult The validation result
     * @return bool True if update was successful
     */
    public function updateConnectionStatus(
        PaymentProviderCredential $credential,
        ValidationResult $validationResult
    ): bool {
        try {
            return DB::transaction(function () use ($credential, $validationResult) {
                $status = $validationResult->isValid
                    ? PaymentProviderCredential::STATUS_VALID
                    : PaymentProviderCredential::STATUS_INVALID;

                $credential->update([
                    'connection_status' => $status,
                    'validation_error' => $validationResult->errorMessage,
                    'last_validated_at' => now(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            \Log::error("Failed to update connection status", [
                'credential_id' => $credential->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Capture and store provider error messages.
     *
     * @param PaymentProviderCredential $credential The credential that failed validation
     * @param string $errorMessage The error message from the provider
     * @return bool True if error was captured successfully
     */
    public function captureProviderError(
        PaymentProviderCredential $credential,
        string $errorMessage
    ): bool {
        try {
            return DB::transaction(function () use ($credential, $errorMessage) {
                $credential->update([
                    'connection_status' => PaymentProviderCredential::STATUS_INVALID,
                    'validation_error' => $errorMessage,
                    'last_validated_at' => now(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            \Log::error("Failed to capture provider error", [
                'credential_id' => $credential->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Manually trigger revalidation of credentials.
     * This can be called by users to check if their credentials are still valid.
     *
     * @param PaymentProviderCredential $credential The credential to revalidate
     * @return ValidationResult The validation result
     */
    public function revalidate(PaymentProviderCredential $credential): ValidationResult
    {
        // Reset validation status before revalidating
        $credential->update([
            'connection_status' => PaymentProviderCredential::STATUS_PENDING,
            'validation_error' => null,
        ]);

        // Perform validation
        return $this->validateCredentials($credential);
    }

    /**
     * Validate multiple credentials for a user.
     * Useful for batch validation operations.
     *
     * @param \Illuminate\Database\Eloquent\Collection $credentials Collection of credentials
     * @return array Array of validation results keyed by credential ID
     */
    public function validateMultiple($credentials): array
    {
        $results = [];

        foreach ($credentials as $credential) {
            try {
                $results[$credential->id] = $this->validateCredentials($credential);
            } catch (\Exception $e) {
                $results[$credential->id] = ValidationResult::failure(
                    $e->getMessage(),
                    'validation_exception'
                );
            }
        }

        return $results;
    }

    /**
     * Check if credentials need revalidation and validate if necessary.
     *
     * @param PaymentProviderCredential $credential The credential to check
     * @return ValidationResult|null Validation result if revalidation was performed, null otherwise
     */
    public function validateIfNeeded(PaymentProviderCredential $credential): ?ValidationResult
    {
        if ($credential->needsRevalidation()) {
            return $this->validateCredentials($credential);
        }

        return null;
    }

    /**
     * Get validation status summary for a credential.
     *
     * @param PaymentProviderCredential $credential The credential to check
     * @return array Status summary with connection status, last validated time, and error
     */
    public function getValidationStatus(PaymentProviderCredential $credential): array
    {
        return [
            'provider' => $credential->provider,
            'connection_status' => $credential->connection_status,
            'is_valid' => $credential->isValid(),
            'needs_revalidation' => $credential->needsRevalidation(),
            'last_validated_at' => $credential->last_validated_at?->toIso8601String(),
            'validation_error' => $credential->validation_error,
        ];
    }
}
