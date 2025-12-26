<?php

namespace App\Services\PaymentProviders;

use App\Contracts\PaymentProviderInterface;
use App\DTOs\QrisRequest;
use App\DTOs\QrisResponse;
use App\DTOs\TransactionStatus;
use App\DTOs\ValidationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Xendit payment provider implementation.
 * 
 * Implements QRIS generation using Xendit QR Codes API.
 * Documentation: https://developers.xendit.co/api-reference
 */
class XenditProvider implements PaymentProviderInterface
{
    private const BASE_URL = 'https://api.xendit.co';

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
        return ['api_key', 'callback_token'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(array $credentials): ValidationResult
    {
        try {
            // Validate required fields
            if (!isset($credentials['api_key']) || !isset($credentials['callback_token'])) {
                return ValidationResult::failure(
                    'Missing required credentials: api_key and callback_token',
                    'MISSING_CREDENTIALS'
                );
            }

            // Test API call to get balance (simple validation endpoint)
            $response = Http::withBasicAuth($credentials['api_key'], '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->get(self::BASE_URL . '/balance');

            if ($response->successful()) {
                $data = $response->json();
                
                return ValidationResult::success([
                    'balance' => $data['balance'] ?? null,
                    'account_type' => 'LIVE',
                ]);
            }

            $errorMessage = $response->json('message') 
                ?? $response->json('error_code') 
                ?? 'Invalid credentials';
            
            return ValidationResult::failure(
                $errorMessage,
                $response->json('error_code') ?? 'VALIDATION_FAILED',
                ['status_code' => $response->status()]
            );

        } catch (\Exception $e) {
            Log::error('Xendit credential validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ValidationResult::failure(
                'Network error or invalid response from Xendit API',
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

            // Generate QRIS using Xendit QR Codes API
            $response = Http::withBasicAuth($credentials['api_key'], '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-CALLBACK-TOKEN' => $credentials['callback_token'],
                ])
                ->post(self::BASE_URL . '/qr_codes', [
                    'external_id' => $request->orderId,
                    'type' => 'DYNAMIC',
                    'callback_url' => config('app.url') . '/api/webhooks/xendit',
                    'amount' => $request->amount,
                    'currency' => 'IDR',
                    'description' => $request->description ?? 'QRIS Payment',
                ]);

            if (!$response->successful()) {
                $errorMessage = $response->json('message') ?? 'Failed to generate QRIS';
                throw new \Exception($errorMessage . ': ' . $response->body());
            }

            $data = $response->json();

            return new QrisResponse(
                qrCodeUrl: $data['qr_string'] ?? '',
                providerTransactionId: $data['id'] ?? $request->orderId,
                orderId: $request->orderId,
                amount: $request->amount,
                expiresAt: $expiresAt,
                metadata: [
                    'provider' => 'xendit',
                    'qr_code_id' => $data['id'] ?? null,
                    'status' => $data['status'] ?? null,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Xendit QRIS generation failed', [
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
            // Check QR code status
            $response = Http::withBasicAuth($credentials['api_key'], '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->get(self::BASE_URL . "/qr_codes/{$transactionId}");

            if (!$response->successful()) {
                throw new \Exception('Failed to check transaction status: ' . $response->body());
            }

            $data = $response->json();
            $status = $this->mapXenditStatus($data['status'] ?? 'ACTIVE');

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
                ]
            );

        } catch (\Exception $e) {
            Log::error('Xendit transaction status check failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Map Xendit status codes to standard status.
     */
    private function mapXenditStatus(string $xenditStatus): string
    {
        return match (strtoupper($xenditStatus)) {
            'COMPLETED' => TransactionStatus::STATUS_SETTLEMENT,
            'ACTIVE', 'PENDING' => TransactionStatus::STATUS_PENDING,
            'EXPIRED' => TransactionStatus::STATUS_EXPIRE,
            'INACTIVE', 'CANCELLED' => TransactionStatus::STATUS_CANCEL,
            default => TransactionStatus::STATUS_FAILED,
        };
    }
}
