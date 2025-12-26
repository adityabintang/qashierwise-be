<?php

namespace App\Services;

use App\Contracts\PaymentProviderInterface;
use App\DTOs\QrisRequest;
use App\DTOs\QrisResponse;
use App\Exceptions\UnsupportedProviderException;
use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use App\Models\User;
use App\Services\PaymentProviders\ProviderFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class QrisService
{
    /**
     * Midtrans API configuration (legacy support).
     */
    private string $serverKey;
    private string $clientKey;
    private string $baseUrl;
    private bool $isProduction;

    /**
     * Financial audit service for logging.
     */
    protected ?FinancialAuditService $auditService = null;

    public function __construct(
        private ProviderCredentialService $credentialService,
        private EncryptionService $encryptionService,
        private ProviderFactory $providerFactory
    ) {
        $this->serverKey = config('services.midtrans.server_key') ?? '';
        $this->clientKey = config('services.midtrans.client_key') ?? '';
        $this->isProduction = config('services.midtrans.is_production') ?? false;
        $this->baseUrl = $this->isProduction
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    /**
     * Set the audit service for logging.
     */
    public function setAuditService(FinancialAuditService $auditService): self
    {
        $this->auditService = $auditService;
        return $this;
    }

    /**
     * Generate a QRIS code for a sub-merchant transaction using multi-provider system.
     *
     * @param SubMerchant $merchant The sub-merchant generating the QRIS
     * @param float $amount Transaction amount in IDR
     * @param array $details Additional transaction details (description, customer_name, etc.)
     * @return QrisTransaction The created QRIS transaction
     * @throws InvalidArgumentException If validation fails
     * @throws RuntimeException If provider API call fails
     */
    public function generateQris(SubMerchant $merchant, float $amount, array $details = []): QrisTransaction
    {
        // Validate merchant can accept payments
        if (!$merchant->is_active) {
            throw new InvalidArgumentException('Sub-merchant is not active');
        }

        // Validate amount
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }

        // Get the merchant's user
        $user = $merchant->user;
        if (!$user) {
            throw new InvalidArgumentException('Sub-merchant must be associated with a user');
        }

        // Get active provider credentials
        $activeCredential = $this->credentialService->getActiveProvider($user);
        if (!$activeCredential) {
            throw new RuntimeException('No active payment provider configured. Please configure a provider in settings.');
        }

        // Validate provider connection status
        if ($activeCredential->connection_status !== 'valid') {
            throw new RuntimeException("Provider {$activeCredential->provider} is not properly configured. Please validate your credentials.");
        }

        // Generate unique order ID
        $orderId = QrisTransaction::generateOrderId();

        // Calculate fees
        $platformFee = QrisTransaction::calculatePlatformFee($amount);
        $netAmount = QrisTransaction::calculateNetAmount($amount);

        // Set expiration time
        $expiresAt = now()->addMinutes($details['expiry_minutes'] ?? QrisTransaction::DEFAULT_EXPIRY_MINUTES);

        return DB::transaction(function () use ($merchant, $user, $activeCredential, $orderId, $amount, $platformFee, $netAmount, $expiresAt, $details) {
            // Create transaction record first
            $transaction = QrisTransaction::create([
                'sub_merchant_id' => $merchant->id,
                'order_id' => $orderId,
                'amount' => $amount,
                'platform_fee' => $platformFee,
                'net_amount' => $netAmount,
                'status' => QrisTransaction::STATUS_PENDING,
                'provider' => $activeCredential->provider,
                'expires_at' => $expiresAt,
            ]);

            // Decrypt credentials for API call
            try {
                $decryptedCredentials = $this->encryptionService->decryptCredentials(
                    $user,
                    $activeCredential->credentials_encrypted
                );

                // Get provider implementation
                $provider = $this->providerFactory->make($activeCredential->provider);

                // Create QRIS request DTO
                $qrisRequest = new QrisRequest(
                    orderId: $orderId,
                    amount: $amount,
                    description: $details['description'] ?? null,
                    expiryMinutes: $details['expiry_minutes'] ?? QrisTransaction::DEFAULT_EXPIRY_MINUTES,
                    metadata: $details
                );

                // Generate QRIS using provider
                $qrisResponse = $provider->generateQris($decryptedCredentials, $qrisRequest);

                // Update transaction with provider response
                $transaction->update([
                    'qr_code_url' => $qrisResponse->qrCodeUrl,
                    'provider_transaction_id' => $qrisResponse->providerTransactionId,
                    // Keep midtrans_transaction_id for backward compatibility
                    'midtrans_transaction_id' => $activeCredential->provider === 'midtrans' 
                        ? $qrisResponse->providerTransactionId 
                        : null,
                ]);

                Log::info('QRIS generated via multi-provider', [
                    'order_id' => $orderId,
                    'provider' => $activeCredential->provider,
                    'sub_merchant_id' => $merchant->id,
                    'amount' => $amount,
                ]);

            } catch (UnsupportedProviderException $e) {
                Log::error('Unsupported provider', [
                    'order_id' => $orderId,
                    'provider' => $activeCredential->provider,
                    'error' => $e->getMessage(),
                ]);
                throw new RuntimeException("Provider {$activeCredential->provider} is not supported");
                
            } catch (\Exception $e) {
                Log::error('QRIS generation failed', [
                    'order_id' => $orderId,
                    'provider' => $activeCredential->provider,
                    'error' => $e->getMessage(),
                ]);
                
                // If provider fails, we still have the transaction record
                // Generate a fallback QR code URL
                $transaction->update([
                    'qr_code_url' => $this->generateMockQrCodeUrl($orderId),
                ]);
            }

            // Log to financial audit trail
            if ($this->auditService !== null) {
                $this->auditService->logQrisGeneration($merchant, $transaction);
            }

            return $transaction->fresh();
        });
    }

    /**
     * Create QRIS via Midtrans API.
     *
     * @param QrisTransaction $transaction The transaction record
     * @param SubMerchant $merchant The sub-merchant
     * @param array $details Additional details
     * @return array Response data with qr_code_url and transaction_id
     * @throws RuntimeException If API call fails
     */
    private function createMidtransQris(QrisTransaction $transaction, SubMerchant $merchant, array $details): array
    {
        if (empty($this->serverKey)) {
            // Return mock data for development/testing when no API key is configured
            return [
                'qr_code_url' => $this->generateMockQrCodeUrl($transaction->order_id),
                'transaction_id' => 'mock-' . $transaction->order_id,
            ];
        }

        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $transaction->order_id,
                'gross_amount' => (int) $transaction->amount,
            ],
            'qris' => [
                'acquirer' => 'gopay', // Default acquirer
            ],
            'custom_expiry' => [
                'expiry_duration' => QrisTransaction::DEFAULT_EXPIRY_MINUTES,
                'unit' => 'minute',
            ],
        ];

        // Add item details if provided
        if (!empty($details['description'])) {
            $payload['item_details'] = [
                [
                    'id' => $transaction->order_id,
                    'price' => (int) $transaction->amount,
                    'quantity' => 1,
                    'name' => substr($details['description'], 0, 50),
                ],
            ];
        }

        // Add customer details if provided
        if (!empty($details['customer_name']) || !empty($details['customer_email'])) {
            $payload['customer_details'] = [
                'first_name' => $details['customer_name'] ?? 'Customer',
                'email' => $details['customer_email'] ?? null,
            ];
        }

        $response = Http::withBasicAuth($this->serverKey, '')
            ->timeout(30)
            ->post("{$this->baseUrl}/v2/charge", $payload);

        if (!$response->successful()) {
            $errorMessage = $response->json('status_message') ?? 'Unknown error';
            throw new RuntimeException("Midtrans API error: {$errorMessage}");
        }

        $data = $response->json();

        Log::info('Midtrans QRIS response', [
            'order_id' => $transaction->order_id,
            'response' => $data,
        ]);

        // Extract QR code URL from actions array
        // Midtrans returns actions with name "generate-qr-code" containing the QR image URL
        $qrCodeUrl = null;
        if (!empty($data['actions'])) {
            foreach ($data['actions'] as $action) {
                if (($action['name'] ?? '') === 'generate-qr-code') {
                    $qrCodeUrl = $action['url'] ?? null;
                    break;
                }
            }
            // Fallback to first action URL if no generate-qr-code found
            if (!$qrCodeUrl && !empty($data['actions'][0]['url'])) {
                $qrCodeUrl = $data['actions'][0]['url'];
            }
        }

        // If still no QR URL, try to generate from qr_string
        if (!$qrCodeUrl && !empty($data['qr_string'])) {
            $qrCodeUrl = $this->generateQrCodeFromString($data['qr_string']);
        }

        return [
            'qr_code_url' => $qrCodeUrl,
            'transaction_id' => $data['transaction_id'] ?? null,
        ];
    }

    /**
     * Generate QR code image URL from QRIS string.
     *
     * @param string $qrString The QRIS string data
     * @return string QR code image URL
     */
    private function generateQrCodeFromString(string $qrString): string
    {
        $encodedData = urlencode($qrString);
        return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedData}";
    }

    /**
     * Generate a mock QR code URL for development/testing.
     *
     * @param string $orderId The order ID
     * @return string Mock QR code URL
     */
    private function generateMockQrCodeUrl(string $orderId): string
    {
        // Use a QR code generator service for mock data
        $data = urlencode("QRIS:{$orderId}");
        return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$data}";
    }

    /**
     * Get the QR code image URL for a transaction.
     *
     * @param QrisTransaction $transaction The transaction
     * @return string|null QR code URL or null if not available
     */
    public function getQrCodeUrl(QrisTransaction $transaction): ?string
    {
        if ($transaction->qr_code_url) {
            return $transaction->qr_code_url;
        }

        // Generate fallback QR code URL
        return $this->generateMockQrCodeUrl($transaction->order_id);
    }

    /**
     * Generate a shareable link for a QRIS transaction.
     *
     * @param QrisTransaction $transaction The transaction
     * @return string Shareable link
     */
    public function generateShareableLink(QrisTransaction $transaction): string
    {
        return $transaction->getShareableLink();
    }

    /**
     * Validate if a QRIS transaction can still be used.
     *
     * @param QrisTransaction $transaction The transaction to validate
     * @return bool True if the QRIS can be used
     */
    public function validateQrisExpiry(QrisTransaction $transaction): bool
    {
        return $transaction->canBeUsed();
    }

    /**
     * Check if a QRIS transaction is expired.
     *
     * @param QrisTransaction $transaction The transaction to check
     * @return bool True if expired
     */
    public function isExpired(QrisTransaction $transaction): bool
    {
        return $transaction->isExpired();
    }

    /**
     * Mark expired pending transactions as expired.
     *
     * @return int Number of transactions marked as expired
     */
    public function markExpiredTransactions(): int
    {
        $expiredTransactions = QrisTransaction::expiredPending()->get();
        $count = 0;

        foreach ($expiredTransactions as $transaction) {
            $transaction->markAsExpired();
            $transaction->save();
            $count++;

            Log::info('QRIS transaction marked as expired', [
                'order_id' => $transaction->order_id,
            ]);

            // Log to financial audit trail
            if ($this->auditService !== null && $transaction->subMerchant !== null) {
                $this->auditService->logQrisExpiration($transaction->subMerchant, $transaction);
            }
        }

        return $count;
    }

    /**
     * Find a QRIS transaction by order ID.
     *
     * @param string $orderId The order ID
     * @return QrisTransaction|null
     */
    public function findByOrderId(string $orderId): ?QrisTransaction
    {
        return QrisTransaction::where('order_id', $orderId)->first();
    }

    /**
     * Find a QRIS transaction by Midtrans transaction ID (legacy support).
     *
     * @param string $transactionId The Midtrans transaction ID
     * @return QrisTransaction|null
     */
    public function findByMidtransId(string $transactionId): ?QrisTransaction
    {
        return QrisTransaction::where('midtrans_transaction_id', $transactionId)->first();
    }

    /**
     * Find a QRIS transaction by provider transaction ID.
     *
     * @param string $transactionId The provider transaction ID
     * @param string|null $provider Optional provider filter
     * @return QrisTransaction|null
     */
    public function findByProviderTransactionId(string $transactionId, ?string $provider = null): ?QrisTransaction
    {
        $query = QrisTransaction::where('provider_transaction_id', $transactionId);
        
        if ($provider !== null) {
            $query->where('provider', $provider);
        }
        
        return $query->first();
    }

    /**
     * Get transaction history for a sub-merchant.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @param int $limit Number of transactions to return
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTransactionHistory(SubMerchant $merchant, int $limit = 50)
    {
        return $merchant->transactions()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get transaction history for a sub-merchant filtered by provider.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @param string $provider The provider to filter by
     * @param int $limit Number of transactions to return
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTransactionHistoryByProvider(SubMerchant $merchant, string $provider, int $limit = 50)
    {
        return $merchant->transactions()
            ->byProvider($provider)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get pending transactions for a sub-merchant.
     *
     * @param SubMerchant $merchant The sub-merchant
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingTransactions(SubMerchant $merchant)
    {
        return $merchant->transactions()
            ->pending()
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Cancel a pending QRIS transaction.
     *
     * @param QrisTransaction $transaction The transaction to cancel
     * @return bool True if cancelled successfully
     * @throws InvalidArgumentException If transaction cannot be cancelled
     */
    public function cancelTransaction(QrisTransaction $transaction): bool
    {
        if (!$transaction->isPending()) {
            throw new InvalidArgumentException('Only pending transactions can be cancelled');
        }

        $transaction->markAsCancelled();
        $result = $transaction->save();

        if ($result) {
            Log::info('QRIS transaction cancelled', [
                'order_id' => $transaction->order_id,
            ]);

            // Log to financial audit trail
            if ($this->auditService !== null && $transaction->subMerchant !== null) {
                $this->auditService->logQrisCancellation($transaction->subMerchant, $transaction);
            }
        }

        return $result;
    }
}
