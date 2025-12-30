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
 * Duitku payment provider implementation.
 * 
 * Implements QRIS generation using Duitku Invoice Creation API.
 * Documentation: https://docs.duitku.com
 */
class DuitkuProvider implements PaymentProviderInterface
{
    private const BASE_URL_PRODUCTION = 'https://passport.duitku.com';
    private const BASE_URL_SANDBOX = 'https://sandbox.duitku.com';
    
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
        return 'duitku';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredCredentialFields(): array
    {
        return ['merchant_code', 'api_key'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(array $credentials): ValidationResult
    {
        try {
            // Validate required fields
            if (!isset($credentials['merchant_code']) || !isset($credentials['api_key'])) {
                return ValidationResult::failure(
                    'Missing required credentials: merchant_code and api_key',
                    'MISSING_CREDENTIALS'
                );
            }

            // Test API call to inquiry endpoint
            $timestamp = time();
            $signature = md5($credentials['merchant_code'] . $timestamp . $credentials['api_key']);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/webapi/api/merchant/inquiry", [
                'merchantCode' => $credentials['merchant_code'],
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Check if response indicates success
                if (isset($data['statusCode']) && $data['statusCode'] === '00') {
                    return ValidationResult::success([
                        'merchant_code' => $credentials['merchant_code'],
                    ]);
                }
                
                // Even if inquiry returns no data, valid auth means credentials work
                if (isset($data['merchantCode'])) {
                    return ValidationResult::success([
                        'merchant_code' => $credentials['merchant_code'],
                    ]);
                }
            }

            $errorMessage = $response->json('statusMessage') 
                ?? $response->json('message') 
                ?? 'Invalid credentials';
            
            return ValidationResult::failure(
                $errorMessage,
                $response->json('statusCode') ?? 'VALIDATION_FAILED',
                ['status_code' => $response->status()]
            );

        } catch (\Exception $e) {
            Log::error('Duitku credential validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ValidationResult::failure(
                'Network error or invalid response from Duitku API',
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

            // Prepare request data
            $merchantCode = $credentials['merchant_code'];
            $apiKey = $credentials['api_key'];
            $paymentAmount = (int) $request->amount;
            $merchantOrderId = $request->orderId;
            $productDetails = $request->description ?? 'QRIS Payment';
            $email = 'customer@example.com'; // Default email
            $paymentMethod = 'SP'; // QRIS payment method code in Duitku
            $returnUrl = config('app.url') . '/api/webhooks/duitku/return';
            $callbackUrl = config('app.url') . '/api/webhooks/duitku/callback';
            $expiryPeriod = $expiryMinutes;

            // Generate signature
            $signature = md5(
                $merchantCode . 
                $merchantOrderId . 
                $paymentAmount . 
                $apiKey
            );

            // Create invoice
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/webapi/api/merchant/createinvoice", [
                'merchantCode' => $merchantCode,
                'paymentAmount' => $paymentAmount,
                'paymentMethod' => $paymentMethod,
                'merchantOrderId' => $merchantOrderId,
                'productDetails' => $productDetails,
                'email' => $email,
                'customerVaName' => 'Customer',
                'callbackUrl' => $callbackUrl,
                'returnUrl' => $returnUrl,
                'signature' => $signature,
                'expiryPeriod' => $expiryPeriod,
            ]);

            if (!$response->successful()) {
                $errorMessage = $response->json('statusMessage') 
                    ?? $response->json('message') 
                    ?? 'Failed to generate QRIS';
                throw new \Exception($errorMessage . ': ' . $response->body());
            }

            $data = $response->json();

            // Check if invoice creation was successful
            if (!isset($data['statusCode']) || $data['statusCode'] !== '00') {
                throw new \Exception($data['statusMessage'] ?? 'Failed to create invoice');
            }

            return new QrisResponse(
                qrCodeUrl: $data['qrString'] ?? $data['paymentUrl'] ?? '',
                providerTransactionId: $data['reference'] ?? $request->orderId,
                orderId: $request->orderId,
                amount: $request->amount,
                expiresAt: $expiresAt,
                metadata: [
                    'provider' => 'duitku',
                    'merchant_code' => $merchantCode,
                    'payment_url' => $data['paymentUrl'] ?? null,
                    'va_number' => $data['vaNumber'] ?? null,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Duitku QRIS generation failed', [
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
            $merchantCode = $credentials['merchant_code'];
            $apiKey = $credentials['api_key'];

            // Generate signature for status check
            $signature = md5($merchantCode . $transactionId . $apiKey);

            // Check transaction status
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/webapi/api/merchant/transactionStatus", [
                'merchantCode' => $merchantCode,
                'merchantOrderId' => $transactionId,
                'signature' => $signature,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to check transaction status: ' . $response->body());
            }

            $data = $response->json();
            
            // Map Duitku status
            $statusCode = $data['statusCode'] ?? '';
            $status = $this->mapDuitkuStatus($statusCode);

            $settledAt = null;
            if ($status === TransactionStatus::STATUS_SETTLEMENT && isset($data['settlementDate'])) {
                try {
                    $settledAt = new \DateTime($data['settlementDate']);
                } catch (\Exception $e) {
                    // Ignore date parsing errors
                }
            }

            return new TransactionStatus(
                status: $status,
                transactionId: $transactionId,
                amount: isset($data['amount']) ? (float) $data['amount'] : null,
                settledAt: $settledAt,
                metadata: [
                    'provider' => 'duitku',
                    'reference' => $data['reference'] ?? null,
                    'original_status' => $statusCode,
                    'status_message' => $data['statusMessage'] ?? null,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Duitku transaction status check failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Map Duitku status codes to standard status.
     */
    private function mapDuitkuStatus(string $duitkuStatus): string
    {
        return match ($duitkuStatus) {
            '00' => TransactionStatus::STATUS_SETTLEMENT,
            '01' => TransactionStatus::STATUS_PENDING,
            '02' => TransactionStatus::STATUS_EXPIRE,
            '03' => TransactionStatus::STATUS_CANCEL,
            default => TransactionStatus::STATUS_FAILED,
        };
    }

    /**
     * {@inheritdoc}
     */
    public function verifyWebhook(array $payload, string $signature, array $credentials): bool
    {
        try {
            // Duitku uses MD5 signature for webhook verification
            $merchantCode = $credentials['merchant_code'] ?? '';
            $apiKey = $credentials['api_key'] ?? '';
            
            // Construct signature string based on Duitku's documentation
            // Format: merchantCode + amount + merchantOrderId + apiKey
            $amount = $payload['amount'] ?? '';
            $merchantOrderId = $payload['merchantOrderId'] ?? '';
            
            $computedSignature = md5($merchantCode . $amount . $merchantOrderId . $apiKey);
            
            return hash_equals($computedSignature, $signature);
        } catch (\Exception $e) {
            Log::error('Duitku webhook verification error', [
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
        $status = $this->mapDuitkuStatus($payload['resultCode'] ?? $payload['statusCode'] ?? '01');
        
        $paidAt = null;
        if ($status === TransactionStatus::STATUS_SETTLEMENT && isset($payload['settlementDate'])) {
            $paidAt = $payload['settlementDate'];
        }

        return new WebhookTransaction(
            externalId: $payload['merchantOrderId'] ?? '',
            status: $status,
            amount: isset($payload['amount']) ? (float) $payload['amount'] : 0.0,
            paidAt: $paidAt,
            provider: 'duitku',
            referenceId: $payload['reference'] ?? null,
            metadata: [
                'result_code' => $payload['resultCode'] ?? null,
                'status_message' => $payload['statusMessage'] ?? null,
            ]
        );
    }
}
