<?php

namespace App\Services\PaymentProviders;

use App\Contracts\PaymentProviderInterface;
use App\DTOs\QrisRequest;
use App\DTOs\QrisResponse;
use App\DTOs\TransactionStatus;
use App\DTOs\ValidationResult;
use App\DTOs\WebhookTransaction;
use App\Exceptions\ProviderException;
use App\Services\CredentialAuditService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Xendit payment provider implementation.
 *
 * Implements QRIS generation using Xendit QR Code API.
 * Documentation: https://developers.xendit.co/api-reference/#create-qr-code
 */
class XenditProvider implements PaymentProviderInterface
{
    private const BASE_URL = 'https://api.xendit.co';

    /**
     * Credential audit service for logging credential access.
     */
    protected ?CredentialAuditService $auditService = null;

    /**
     * Set the audit service for logging.
     */
    public function setAuditService(CredentialAuditService $auditService): self
    {
        $this->auditService = $auditService;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderName(): string
    {
        return 'xendit';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredCredentialFields(): array
    {
        return ['api_key', 'webhook_token'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(array $credentials): ValidationResult
    {
        $startTime = microtime(true);

        try {
            // Validate required fields
            if (! isset($credentials['api_key'])) {
                Log::warning('Xendit credential validation failed: missing api_key', [
                    'provider' => 'xendit',
                    'has_webhook_token' => isset($credentials['webhook_token']),
                ]);

                return ValidationResult::failure(
                    'Missing required credential: api_key',
                    'MISSING_CREDENTIALS'
                );
            }

            Log::info('Xendit credential validation started', [
                'provider' => 'xendit',
                'has_api_key' => isset($credentials['api_key']),
                'has_webhook_token' => isset($credentials['webhook_token']),
            ]);

            // Test API call - get balance to validate credentials
            $response = Http::withBasicAuth($credentials['api_key'], '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->timeout(10)
                ->get(self::BASE_URL.'/balance');

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                Log::info('Xendit credential validation successful', [
                    'provider' => 'xendit',
                    'duration_ms' => $duration,
                    'status_code' => $response->status(),
                ]);

                return ValidationResult::success([
                    'balance' => $response->json('balance'),
                ]);
            }

            // 401 means invalid credentials
            if ($response->status() === 401) {
                Log::warning('Xendit credential validation failed: invalid credentials', [
                    'provider' => 'xendit',
                    'status_code' => 401,
                    'duration_ms' => $duration,
                ]);

                return ValidationResult::failure(
                    'Invalid API key',
                    'INVALID_CREDENTIALS',
                    ['status_code' => 401]
                );
            }

            $errorMessage = $this->sanitizeErrorMessage(
                $response->json('message') ?? $response->json('error_code') ?? 'Invalid credentials'
            );

            Log::error('Xendit credential validation failed', [
                'provider' => 'xendit',
                'status_code' => $response->status(),
                'error_code' => $response->json('error_code'),
                'duration_ms' => $duration,
            ]);

            return ValidationResult::failure(
                $errorMessage,
                $response->json('error_code') ?? 'VALIDATION_FAILED',
                ['status_code' => $response->status()]
            );

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Xendit credential validation failed: connection error', [
                'provider' => 'xendit',
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            return ValidationResult::failure(
                'Unable to connect to Xendit API. Please check your internet connection.',
                'NETWORK_ERROR',
                ['exception' => 'Connection timeout']
            );

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Xendit credential validation failed: unexpected error', [
                'provider' => 'xendit',
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => $duration,
                'trace' => $e->getTraceAsString(),
            ]);

            return ValidationResult::failure(
                'Network error or invalid response from Xendit API',
                'NETWORK_ERROR',
                ['exception' => $this->sanitizeErrorMessage($e->getMessage())]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateQris(array $credentials, QrisRequest $request): QrisResponse
    {
        $startTime = microtime(true);

        try {
            if (! isset($credentials['api_key'])) {
                Log::error('Xendit QRIS generation failed: missing API key', [
                    'provider' => 'xendit',
                    'order_id' => $request->orderId,
                ]);

                throw ProviderException::credentialError(
                    'xendit',
                    'Missing API key',
                    'MISSING_API_KEY'
                );
            }

            $expiryMinutes = $request->expiryMinutes ?? 30;
            $expiresAt = now()->addMinutes($expiryMinutes);

            // Prepare callback URL
            $callbackUrl = config('app.url').'/api/webhooks/xendit';

            Log::info('Xendit QRIS generation started', [
                'provider' => 'xendit',
                'order_id' => $request->orderId,
                'amount' => $request->amount,
                'expiry_minutes' => $expiryMinutes,
            ]);

            // Generate QRIS using Xendit QR Code API
            // API version 2022-07-31 requires 'reference_id' instead of 'external_id'
            $response = Http::withBasicAuth($credentials['api_key'], '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'api-version' => '2022-07-31',
                ])
                ->timeout(30)
                ->post(self::BASE_URL.'/qr_codes', [
                    'reference_id' => $request->orderId,
                    'type' => 'DYNAMIC',
                    'currency' => 'IDR',
                    'amount' => (int) $request->amount,
                ]);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if (! $response->successful()) {
                $errorMessage = $this->sanitizeErrorMessage(
                    $response->json('message') ?? $response->json('error_code') ?? 'Failed to generate QRIS'
                );

                $errorCode = $response->json('error_code');

                Log::error('Xendit QRIS generation failed', [
                    'provider' => 'xendit',
                    'order_id' => $request->orderId,
                    'status_code' => $response->status(),
                    'error_code' => $errorCode,
                    'duration_ms' => $duration,
                ]);

                // Check for credential errors
                if ($response->status() === 401 || $errorCode === 'API_VALIDATION_ERROR') {
                    throw ProviderException::credentialError(
                        'xendit',
                        $errorMessage,
                        $errorCode
                    );
                }

                // Check for network errors
                if ($response->status() >= 500) {
                    throw ProviderException::networkError('xendit');
                }

                throw ProviderException::providerError(
                    'xendit',
                    $errorMessage,
                    $errorCode
                );
            }

            $data = $response->json();

            Log::info('Xendit QRIS generation successful', [
                'provider' => 'xendit',
                'order_id' => $request->orderId,
                'qr_id' => $data['id'] ?? null,
                'status' => $data['status'] ?? null,
                'duration_ms' => $duration,
            ]);

            // Convert qr_string to QR code image URL
            // Xendit returns qr_string (raw QRIS data), not an image URL
            $qrString = $data['qr_string'] ?? '';
            $qrCodeUrl = $this->generateQrCodeImageUrl($qrString);

            return new QrisResponse(
                qrCodeUrl: $qrCodeUrl,
                providerTransactionId: $data['id'] ?? $request->orderId,
                orderId: $request->orderId,
                amount: $request->amount,
                expiresAt: $expiresAt,
                metadata: [
                    'provider' => 'xendit',
                    'status' => $data['status'] ?? null,
                    'type' => $data['type'] ?? null,
                    'qr_string' => $qrString, // Store original qr_string in metadata
                ]
            );

        } catch (ProviderException $e) {
            Log::error('Xendit QRIS generation failed with provider exception', [
                'provider' => 'xendit',
                'order_id' => $request->orderId,
                'error' => $e->getMessage(),
                'error_type' => $e->getErrorType(),
                'error_code' => $e->getProviderErrorCode(),
            ]);

            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Xendit QRIS generation failed: connection error', [
                'provider' => 'xendit',
                'order_id' => $request->orderId,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            throw ProviderException::networkError('xendit', $e);
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Xendit QRIS generation failed: unexpected error', [
                'provider' => 'xendit',
                'order_id' => $request->orderId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => $duration,
            ]);

            throw ProviderException::networkError('xendit', $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkTransactionStatus(array $credentials, string $transactionId): TransactionStatus
    {
        $startTime = microtime(true);

        try {
            if (! isset($credentials['api_key'])) {
                Log::error('Xendit transaction status check failed: missing API key', [
                    'provider' => 'xendit',
                    'transaction_id' => $transactionId,
                ]);

                throw ProviderException::credentialError(
                    'xendit',
                    'Missing API key',
                    'MISSING_API_KEY'
                );
            }

            Log::info('Xendit transaction status check started', [
                'provider' => 'xendit',
                'transaction_id' => $transactionId,
            ]);

            // Check QR code status
            $response = Http::withBasicAuth($credentials['api_key'], '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->timeout(10)
                ->get(self::BASE_URL."/qr_codes/{$transactionId}");

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if (! $response->successful()) {
                $errorMessage = $this->sanitizeErrorMessage(
                    $response->json('message') ?? 'Failed to check transaction status'
                );

                Log::error('Xendit transaction status check failed', [
                    'provider' => 'xendit',
                    'transaction_id' => $transactionId,
                    'status_code' => $response->status(),
                    'duration_ms' => $duration,
                ]);

                throw ProviderException::providerError('xendit', $errorMessage);
            }

            $data = $response->json();
            $status = $this->mapXenditStatus($data['status'] ?? 'ACTIVE');

            Log::info('Xendit transaction status check successful', [
                'provider' => 'xendit',
                'transaction_id' => $transactionId,
                'status' => $status,
                'original_status' => $data['status'] ?? null,
                'duration_ms' => $duration,
            ]);

            return new TransactionStatus(
                status: $status,
                transactionId: $transactionId,
                amount: isset($data['amount']) ? (float) $data['amount'] : null,
                settledAt: $status === TransactionStatus::STATUS_SETTLEMENT && isset($data['updated'])
                    ? new \DateTime($data['updated'])
                    : null,
                metadata: [
                    'provider' => 'xendit',
                    'external_id' => $data['external_id'] ?? null,
                    'original_status' => $data['status'] ?? null,
                    'type' => $data['type'] ?? null,
                ]
            );

        } catch (ProviderException $e) {
            Log::error('Xendit transaction status check failed with provider exception', [
                'provider' => 'xendit',
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'error_type' => $e->getErrorType(),
            ]);

            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Xendit transaction status check failed: connection error', [
                'provider' => 'xendit',
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            throw ProviderException::networkError('xendit', $e);
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Xendit transaction status check failed: unexpected error', [
                'provider' => 'xendit',
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => $duration,
            ]);

            throw ProviderException::networkError('xendit', $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyWebhook(array $payload, string $signature, array $credentials): bool
    {
        try {
            // Xendit uses webhook token for verification
            // The signature is sent in the x-callback-token header
            $webhookToken = $credentials['webhook_token'] ?? null;

            if (! $webhookToken) {
                Log::warning('Xendit webhook verification failed: missing webhook token', [
                    'provider' => 'xendit',
                    'has_signature' => ! empty($signature),
                ]);

                return false;
            }

            // Xendit sends the webhook token directly in the header
            // Compare it with the stored token
            $isValid = hash_equals($webhookToken, $signature);

            Log::info('Xendit webhook verification completed', [
                'provider' => 'xendit',
                'is_valid' => $isValid,
                'external_id' => $payload['external_id'] ?? null,
            ]);

            return $isValid;

        } catch (\Exception $e) {
            Log::error('Xendit webhook verification error', [
                'provider' => 'xendit',
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function parseWebhookPayload(array $payload): WebhookTransaction
    {
        try {
            $status = $this->mapXenditStatus($payload['status'] ?? 'ACTIVE');

            // Extract paid_at timestamp if status is COMPLETED
            $paidAt = null;
            if ($status === TransactionStatus::STATUS_SETTLEMENT && isset($payload['updated'])) {
                $paidAt = $payload['updated'];
            }

            // API version 2022-07-31 uses 'reference_id' instead of 'external_id'
            // Support both for backward compatibility
            $externalId = $payload['reference_id'] ?? $payload['external_id'] ?? $payload['id'] ?? '';

            Log::info('Xendit webhook payload parsed', [
                'provider' => 'xendit',
                'reference_id' => $externalId,
                'status' => $status,
                'original_status' => $payload['status'] ?? null,
                'amount' => $payload['amount'] ?? 0,
            ]);

            return new WebhookTransaction(
                externalId: $externalId,
                status: $status,
                amount: isset($payload['amount']) ? (float) $payload['amount'] : 0.0,
                paidAt: $paidAt,
                provider: 'xendit',
                referenceId: $payload['id'] ?? null,
                metadata: [
                    'type' => $payload['type'] ?? null,
                    'currency' => $payload['currency'] ?? null,
                    'original_status' => $payload['status'] ?? null,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Xendit webhook payload parsing failed', [
                'provider' => 'xendit',
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'payload_keys' => array_keys($payload),
            ]);

            throw $e;
        }
    }

    /**
     * Sanitize error messages to remove sensitive information.
     * Removes API keys, tokens, and other credentials from error messages.
     *
     * @param  string  $message  The error message to sanitize
     * @return string The sanitized error message
     */
    private function sanitizeErrorMessage(string $message): string
    {
        // Remove anything that looks like an API key or token
        // Xendit API keys typically start with xnd_
        $message = preg_replace('/xnd_[a-zA-Z0-9_-]+/', '[REDACTED_API_KEY]', $message);

        // Remove bearer tokens
        $message = preg_replace('/Bearer\s+[a-zA-Z0-9_-]+/', 'Bearer [REDACTED_TOKEN]', $message);

        // Remove basic auth credentials
        $message = preg_replace('/Basic\s+[a-zA-Z0-9+\/=]+/', 'Basic [REDACTED_CREDENTIALS]', $message);

        // Remove anything that looks like a secret key or password
        $message = preg_replace('/(secret|password|key|token)[\s:=]+[^\s,}]+/i', '$1=[REDACTED]', $message);

        // Remove webhook tokens
        $message = preg_replace('/webhook[_-]?token[\s:=]+[^\s,}]+/i', 'webhook_token=[REDACTED]', $message);

        return $message;
    }

    /**
     * Map Xendit status codes to standard status.
     *
     * Xendit QR Code statuses:
     * - ACTIVE: QR code is active and awaiting payment
     * - COMPLETED: Payment has been received
     * - INACTIVE: QR code has expired or been deactivated
     */
    private function mapXenditStatus(string $xenditStatus): string
    {
        return match (strtoupper($xenditStatus)) {
            'COMPLETED' => TransactionStatus::STATUS_SETTLEMENT,
            'ACTIVE' => TransactionStatus::STATUS_PENDING,
            'INACTIVE' => TransactionStatus::STATUS_EXPIRE,
            default => TransactionStatus::STATUS_FAILED,
        };
    }

    /**
     * Generate QR code image URL from QRIS string data.
     *
     * Xendit API returns qr_string (raw QRIS data), not an image URL.
     * This method converts the QRIS string to a displayable QR code image URL
     * using a QR code generator service.
     *
     * @param  string  $qrString  The raw QRIS string data from Xendit
     * @return string QR code image URL
     */
    private function generateQrCodeImageUrl(string $qrString): string
    {
        if (empty($qrString)) {
            return '';
        }

        // Use QR Server API to generate QR code image from QRIS string
        $encodedData = urlencode($qrString);

        return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedData}";
    }
}
