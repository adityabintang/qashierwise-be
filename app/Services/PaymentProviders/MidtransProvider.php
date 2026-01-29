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
 * Midtrans payment provider implementation.
 *
 * Implements QRIS generation using Midtrans Charge API.
 * Documentation: https://docs.midtrans.com
 */
class MidtransProvider implements PaymentProviderInterface
{
    private const BASE_URL_PRODUCTION = 'https://api.midtrans.com';

    private const BASE_URL_SANDBOX = 'https://api.sandbox.midtrans.com';

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
        return 'midtrans';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredCredentialFields(): array
    {
        return ['server_key', 'client_key'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(array $credentials): ValidationResult
    {
        try {
            // Validate required fields
            if (! isset($credentials['server_key']) || ! isset($credentials['client_key'])) {
                return ValidationResult::failure(
                    'Missing required credentials: server_key and client_key',
                    'MISSING_CREDENTIALS'
                );
            }

            // Test API call - try to get status of a dummy transaction
            // This will fail but validates the credentials
            $testOrderId = 'test-'.time();
            $response = Http::withBasicAuth($credentials['server_key'], '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/v2/{$testOrderId}/status");

            // 404 with valid auth means credentials are valid
            if ($response->status() === 404) {
                return ValidationResult::success([
                    'environment' => config('app.env'),
                ]);
            }

            // 401 means invalid credentials
            if ($response->status() === 401) {
                return ValidationResult::failure(
                    'Invalid server key',
                    'INVALID_CREDENTIALS',
                    ['status_code' => 401]
                );
            }

            // Any other successful response means credentials work
            if ($response->successful()) {
                return ValidationResult::success([
                    'environment' => config('app.env'),
                ]);
            }

            $errorMessage = $response->json('status_message')
                ?? $response->json('error_message')
                ?? 'Invalid credentials';

            return ValidationResult::failure(
                $errorMessage,
                $response->json('status_code') ?? 'VALIDATION_FAILED',
                ['status_code' => $response->status()]
            );

        } catch (\Exception $e) {
            Log::error('Midtrans credential validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ValidationResult::failure(
                'Network error or invalid response from Midtrans API',
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
            $expiryMinutes = $request->expiryMinutes ?? 30;
            $expiresAt = now()->addMinutes($expiryMinutes);

            // Generate QRIS using Midtrans Charge API
            $response = Http::withBasicAuth($credentials['server_key'], '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/v2/charge", [
                    'payment_type' => 'qris',
                    'transaction_details' => [
                        'order_id' => $request->orderId,
                        'gross_amount' => (int) $request->amount,
                    ],
                    'qris' => [
                        'acquirer' => 'gopay',
                    ],
                    'custom_expiry' => [
                        'expiry_duration' => $expiryMinutes,
                        'unit' => 'minute',
                    ],
                ]);

            if (! $response->successful()) {
                $errorMessage = $response->json('status_message')
                    ?? $response->json('error_message')
                    ?? 'Failed to generate QRIS';
                throw new \Exception($errorMessage.': '.$response->body());
            }

            $data = $response->json();

            // Extract QR code URL from actions array
            $qrCodeUrl = '';
            if (isset($data['actions'])) {
                foreach ($data['actions'] as $action) {
                    if ($action['name'] === 'generate-qr-code') {
                        $qrCodeUrl = $action['url'] ?? '';
                        break;
                    }
                }
            }

            return new QrisResponse(
                qrCodeUrl: $qrCodeUrl,
                providerTransactionId: $data['transaction_id'] ?? $request->orderId,
                orderId: $request->orderId,
                amount: $request->amount,
                expiresAt: $expiresAt,
                metadata: [
                    'provider' => 'midtrans',
                    'transaction_status' => $data['transaction_status'] ?? null,
                    'status_code' => $data['status_code'] ?? null,
                    'acquirer' => $data['acquirer'] ?? null,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Midtrans QRIS generation failed', [
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
            // Check transaction status
            $response = Http::withBasicAuth($credentials['server_key'], '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/v2/{$transactionId}/status");

            if (! $response->successful()) {
                throw new \Exception('Failed to check transaction status: '.$response->body());
            }

            $data = $response->json();
            $status = $this->mapMidtransStatus($data['transaction_status'] ?? 'pending');

            $settledAt = null;
            if ($status === TransactionStatus::STATUS_SETTLEMENT && isset($data['settlement_time'])) {
                try {
                    $settledAt = new \DateTime($data['settlement_time']);
                } catch (\Exception $e) {
                    // Ignore date parsing errors
                }
            }

            return new TransactionStatus(
                status: $status,
                transactionId: $transactionId,
                amount: isset($data['gross_amount']) ? (float) $data['gross_amount'] : null,
                settledAt: $settledAt,
                metadata: [
                    'provider' => 'midtrans',
                    'order_id' => $data['order_id'] ?? null,
                    'payment_type' => $data['payment_type'] ?? null,
                    'original_status' => $data['transaction_status'] ?? null,
                    'status_code' => $data['status_code'] ?? null,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Midtrans transaction status check failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Map Midtrans status codes to standard status.
     */
    private function mapMidtransStatus(string $midtransStatus): string
    {
        return match (strtolower($midtransStatus)) {
            'capture', 'settlement' => TransactionStatus::STATUS_SETTLEMENT,
            'pending' => TransactionStatus::STATUS_PENDING,
            'expire' => TransactionStatus::STATUS_EXPIRE,
            'cancel', 'deny' => TransactionStatus::STATUS_CANCEL,
            default => TransactionStatus::STATUS_FAILED,
        };
    }

    /**
     * {@inheritdoc}
     */
    public function verifyWebhook(array $payload, string $signature, array $credentials): bool
    {
        try {
            // Midtrans uses SHA512 hash for signature verification
            $serverKey = $credentials['server_key'] ?? '';

            // Construct signature string: order_id + status_code + gross_amount + server_key
            $orderId = $payload['order_id'] ?? '';
            $statusCode = $payload['status_code'] ?? '';
            $grossAmount = $payload['gross_amount'] ?? '';

            $signatureString = $orderId.$statusCode.$grossAmount.$serverKey;
            $computedSignature = hash('sha512', $signatureString);

            return hash_equals($computedSignature, $signature);
        } catch (\Exception $e) {
            Log::error('Midtrans webhook verification error', [
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
        $status = $this->mapMidtransStatus($payload['transaction_status'] ?? 'pending');

        $paidAt = null;
        if ($status === TransactionStatus::STATUS_SETTLEMENT && isset($payload['settlement_time'])) {
            $paidAt = $payload['settlement_time'];
        }

        return new WebhookTransaction(
            externalId: $payload['order_id'] ?? '',
            status: $status,
            amount: isset($payload['gross_amount']) ? (float) $payload['gross_amount'] : 0.0,
            paidAt: $paidAt,
            provider: 'midtrans',
            referenceId: $payload['transaction_id'] ?? null,
            metadata: [
                'payment_type' => $payload['payment_type'] ?? null,
                'transaction_status' => $payload['transaction_status'] ?? null,
                'status_code' => $payload['status_code'] ?? null,
                'acquirer' => $payload['acquirer'] ?? null,
            ]
        );
    }
}
