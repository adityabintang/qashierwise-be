<?php

namespace App\Contracts;

use App\DTOs\QrisRequest;
use App\DTOs\QrisResponse;
use App\DTOs\TransactionStatus;
use App\DTOs\ValidationResult;
use App\DTOs\WebhookTransaction;

/**
 * Interface for payment provider implementations.
 *
 * All payment providers (Doku, Xendit, Midtrans, Duitku) must implement this interface
 * to ensure consistent behavior across different providers.
 */
interface PaymentProviderInterface
{
    /**
     * Validate the provided credentials by making a test API call.
     *
     * @param  array  $credentials  Provider-specific credentials
     * @return ValidationResult Result of the validation attempt
     */
    public function validateCredentials(array $credentials): ValidationResult;

    /**
     * Generate a QRIS code using the provider's API.
     *
     * @param  array  $credentials  Provider-specific credentials
     * @param  QrisRequest  $request  QRIS generation request details
     * @return QrisResponse Response containing QR code URL and transaction details
     */
    public function generateQris(array $credentials, QrisRequest $request): QrisResponse;

    /**
     * Check the status of a transaction.
     *
     * @param  array  $credentials  Provider-specific credentials
     * @param  string  $transactionId  Provider-specific transaction ID
     * @return TransactionStatus Current status of the transaction
     */
    public function checkTransactionStatus(array $credentials, string $transactionId): TransactionStatus;

    /**
     * Verify webhook signature from provider.
     *
     * @param  array  $payload  Webhook payload data
     * @param  string  $signature  Signature from webhook headers
     * @param  array  $credentials  Provider-specific credentials for verification
     * @return bool True if signature is valid, false otherwise
     */
    public function verifyWebhook(array $payload, string $signature, array $credentials): bool;

    /**
     * Parse webhook payload into standard format.
     *
     * @param  array  $payload  Raw webhook payload from provider
     * @return WebhookTransaction Standardized transaction data
     */
    public function parseWebhookPayload(array $payload): WebhookTransaction;

    /**
     * Get the name of the provider.
     *
     * @return string Provider name (e.g., 'doku', 'xendit', 'midtrans', 'duitku')
     */
    public function getProviderName(): string;

    /**
     * Get the list of required credential fields for this provider.
     *
     * @return array Array of required field names
     */
    public function getRequiredCredentialFields(): array;
}
