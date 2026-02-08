<?php

namespace App\Services\PaymentProviders;

use App\Contracts\PaymentProviderInterface;
use App\DTOs\QrisRequest;
use App\DTOs\QrisResponse;
use App\DTOs\TransactionStatus;
use App\DTOs\ValidationResult;
use App\DTOs\WebhookTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Doku payment provider implementation.
 *
 * Implements QRIS generation using Doku SNAP API.
 * Documentation: https://developers.doku.com
 */
class DokuProvider implements PaymentProviderInterface
{
    private const BASE_URL_PRODUCTION = 'https://api.doku.com';

    private const BASE_URL_SANDBOX = 'https://api-sandbox.doku.com';

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('app.env') === 'production'
            ? self::BASE_URL_PRODUCTION
            : self::BASE_URL_SANDBOX;
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderName(): string
    {
        return 'doku';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredCredentialFields(): array
    {
        return ['client_id', 'secret_key'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(array $credentials): ValidationResult
    {
        try {
            // Validate required fields
            if (! isset($credentials['client_id']) || ! isset($credentials['secret_key'])) {
                return ValidationResult::failure(
                    'Missing required credentials: client_id and secret_key',
                    'MISSING_CREDENTIALS'
                );
            }

            // Test API call to get access token (SNAP API)
            $response = Http::withHeaders([
                'Client-Id' => $credentials['client_id'],
                'Client-Secret' => $credentials['secret_key'],
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/snap/v1/access-token/b2b", [
                'grantType' => 'client_credentials',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['accessToken'])) {
                    return ValidationResult::success([
                        'token_type' => $data['tokenType'] ?? 'Bearer',
                        'expires_in' => $data['expiresIn'] ?? null,
                    ]);
                }
            }

            $errorMessage = $response->json('responseMessage')
                ?? $response->json('message')
                ?? 'Invalid credentials';

            return ValidationResult::failure(
                $errorMessage,
                $response->json('responseCode') ?? 'VALIDATION_FAILED',
                ['status_code' => $response->status()]
            );

        } catch (\Exception $e) {
            Log::error('Doku credential validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ValidationResult::failure(
                'Network error or invalid response from Doku API',
                'NETWORK_ERROR',
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateQris(array $credentials, QrisRequest $request): QrisResponse
    {
        try {
            // First, get access token
            $tokenResponse = Http::withHeaders([
                'Client-Id' => $credentials['client_id'],
                'Client-Secret' => $credentials['secret_key'],
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/snap/v1/access-token/b2b", [
                'grantType' => 'client_credentials',
            ]);

            if (! $tokenResponse->successful()) {
                throw new \Exception('Failed to obtain access token: '.$tokenResponse->body());
            }

            $accessToken = $tokenResponse->json('accessToken');

            // Generate QRIS using SNAP API
            $expiryMinutes = $request->expiryMinutes ?? 30;
            $expiresAt = now()->addMinutes($expiryMinutes);

            $qrisResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Client-Id' => $credentials['client_id'],
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/snap/v1/qr/qr-mpm-generate", [
                'partnerReferenceNo' => $request->orderId,
                'amount' => [
                    'value' => number_format($request->amount, 2, '.', ''),
                    'currency' => 'IDR',
                ],
                'merchantId' => $credentials['client_id'],
                'storeLabel' => $request->description ?? 'QRIS Payment',
                'terminalLabel' => 'WEB',
                'validityPeriod' => $expiryMinutes.'m',
            ]);

            if (! $qrisResponse->successful()) {
                throw new \Exception('Failed to generate QRIS: '.$qrisResponse->body());
            }

            $data = $qrisResponse->json();

            return new QrisResponse(
                qrCodeUrl: $data['qrContent'] ?? $data['qrCode'] ?? '',
                providerTransactionId: $data['referenceNo'] ?? $request->orderId,
                orderId: $request->orderId,
                amount: $request->amount,
                expiresAt: $expiresAt,
                metadata: [
                    'provider' => 'doku',
                    'merchant_id' => $credentials['client_id'],
                    'response_code' => $data['responseCode'] ?? null,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Doku QRIS generation failed', [
                'order_id' => $request->orderId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkTransactionStatus(array $credentials, string $transactionId): TransactionStatus
    {
        try {
            // Get access token
            $tokenResponse = Http::withHeaders([
                'Client-Id' => $credentials['client_id'],
                'Client-Secret' => $credentials['secret_key'],
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/snap/v1/access-token/b2b", [
                'grantType' => 'client_credentials',
            ]);

            if (! $tokenResponse->successful()) {
                throw new \Exception('Failed to obtain access token');
            }

            $accessToken = $tokenResponse->json('accessToken');

            // Check transaction status
            $statusResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Client-Id' => $credentials['client_id'],
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/snap/v1/qr/qr-mpm-query", [
                'originalPartnerReferenceNo' => $transactionId,
            ]);

            if (! $statusResponse->successful()) {
                throw new \Exception('Failed to check transaction status');
            }

            $data = $statusResponse->json();
            $status = $this->mapDokuStatus($data['transactionStatusCode'] ?? $data['latestTransactionStatus'] ?? 'pending');

            return new TransactionStatus(
                status: $status,
                transactionId: $transactionId,
                amount: isset($data['amount']['value']) ? (float) $data['amount']['value'] : null,
                settledAt: $status === TransactionStatus::STATUS_SETTLEMENT ? now() : null,
                metadata: [
                    'provider' => 'doku',
                    'response_code' => $data['responseCode'] ?? null,
                    'original_status' => $data['transactionStatusCode'] ?? null,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Doku transaction status check failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Map Doku status codes to standard status.
     */
    private function mapDokuStatus(string $dokuStatus): string
    {
        return match (strtolower($dokuStatus)) {
            '00', 'success', 'settlement' => TransactionStatus::STATUS_SETTLEMENT,
            'pending', '01' => TransactionStatus::STATUS_PENDING,
            'expired', '03' => TransactionStatus::STATUS_EXPIRE,
            'cancelled', 'cancel', '02' => TransactionStatus::STATUS_CANCEL,
            default => TransactionStatus::STATUS_FAILED,
        };
    }

    /**
     * {@inheritdoc}
     */
    public function verifyWebhook(array $payload, string $signature, array $credentials): bool
    {
        try {
            // Doku uses HMAC-SHA256 for webhook verification
            $secretKey = $credentials['secret_key'] ?? '';

            // Construct the signature string (adjust based on Doku's documentation)
            $signatureString = json_encode($payload);
            $computedSignature = hash_hmac('sha256', $signatureString, $secretKey);

            return hash_equals($computedSignature, $signature);
        } catch (\Exception $e) {
            Log::error('Doku webhook verification error', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function parseWebhookPayload(array $payload): WebhookTransaction
    {
        $status = $this->mapDokuStatus($payload['transactionStatusCode'] ?? $payload['latestTransactionStatus'] ?? 'pending');

        $paidAt = null;
        if ($status === TransactionStatus::STATUS_SETTLEMENT && isset($payload['transactionDate'])) {
            $paidAt = $payload['transactionDate'];
        }

        return new WebhookTransaction(
            externalId: $payload['originalPartnerReferenceNo'] ?? $payload['partnerReferenceNo'] ?? '',
            status: $status,
            amount: isset($payload['amount']['value']) ? (float) $payload['amount']['value'] : 0.0,
            paidAt: $paidAt,
            provider: 'doku',
            referenceId: $payload['referenceNo'] ?? null,
            metadata: [
                'response_code' => $payload['responseCode'] ?? null,
                'original_status' => $payload['transactionStatusCode'] ?? null,
            ]
        );
    }
}
