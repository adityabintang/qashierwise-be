<?php

namespace App\Services;

use App\DTOs\QrisRequest;
use App\DTOs\QrisResponse;
use App\DTOs\WebhookTransaction;
use App\Models\SubMerchant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * XenPlatform service for managing sub-accounts and transactions.
 *
 * All API calls use the master account API key with the `for-user-id`
 * header to transact on behalf of OWNED sub-accounts.
 */
class XenPlatformService
{
    private string $apiKey;

    private string $webhookToken;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('xendit.api_key') ?? '';
        $this->webhookToken = config('xendit.webhook_token') ?? '';
        $this->baseUrl = config('xendit.base_url', 'https://api.xendit.co');
    }

    /**
     * Create an OWNED sub-account on XenPlatform.
     *
     * @return array{id: string, status: string} The created account data
     *
     * @throws RuntimeException If API call fails
     */
    public function createSubAccount(SubMerchant $merchant): array
    {
        $this->ensureApiKey();

        $businessName = $merchant->business_name ?? $merchant->user->name ?? 'Merchant';
        $email = $merchant->user->email ?? null;

        Log::info('XenPlatform: Creating sub-account', [
            'sub_merchant_id' => $merchant->id,
            'business_name' => $businessName,
            'email' => $email,
        ]);

        try {
            $payload = [
                'type' => 'MANAGED',
                'country' => 'ID',
                'public_profile' => [
                    'business_name' => $businessName,
                ],
            ];

            if ($email) {
                $payload['email'] = $email;
            }

            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->baseUrl}/v2/accounts", $payload);

            // If the email is already registered (orphaned account from a failed prior registration),
            // retry without email. MANAGED sub-accounts don't require email — the platform controls them.
            if (! $response->successful()
                && $response->status() === 409
                && $response->json('error_code') === 'BUSINESS_DUPLICATE_EMAIL_ERROR'
                && isset($payload['email'])
            ) {
                Log::warning('XenPlatform: Email already registered, retrying account creation without email', [
                    'sub_merchant_id' => $merchant->id,
                ]);
                unset($payload['email']);
                $response = Http::withBasicAuth($this->apiKey, '')
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(30)
                    ->post("{$this->baseUrl}/v2/accounts", $payload);
            }

            if (! $response->successful()) {
                $errorMessage = $response->json('message') ?? $response->json('error_code') ?? 'Failed to create sub-account';

                Log::error('XenPlatform: Failed to create sub-account', [
                    'sub_merchant_id' => $merchant->id,
                    'status_code' => $response->status(),
                    'error' => $errorMessage,
                    'response_body' => $response->body(),
                ]);

                throw new RuntimeException("XenPlatform API error: {$errorMessage}");
            }

            $data = $response->json();

            Log::info('XenPlatform: Sub-account created', [
                'sub_merchant_id' => $merchant->id,
                'xendit_account_id' => $data['id'] ?? null,
            ]);

            return [
                'id' => $data['id'],
                'status' => $data['status'] ?? 'ACTIVE',
            ];
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('XenPlatform: Unexpected error creating sub-account', [
                'sub_merchant_id' => $merchant->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Failed to create XenPlatform sub-account: {$e->getMessage()}");
        }
    }

    /**
     * Verify that a XenPlatform sub-account exists.
     *
     * @return true  account exists
     * @return false account not found (404)
     * @return null  could not determine (network error / unexpected response)
     */
    public function verifySubAccount(string $xenditAccountId): ?bool
    {
        $this->ensureApiKey();

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(5)
                ->get("{$this->baseUrl}/v2/accounts/{$xenditAccountId}");

            if ($response->status() === 404) {
                return false;
            }

            if ($response->successful()) {
                return true;
            }

            Log::warning('XenPlatform: Unexpected response verifying sub-account', [
                'xendit_account_id' => $xenditAccountId,
                'status_code' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('XenPlatform: Failed to verify sub-account existence', [
                'xendit_account_id' => $xenditAccountId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get balance for a sub-account.
     *
     * @return array{balance: float}
     */
    public function getBalance(string $xenditAccountId): array
    {
        $this->ensureApiKey();

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'for-user-id' => $xenditAccountId,
                ])
                ->timeout(10)
                ->get("{$this->baseUrl}/balance");

            if (! $response->successful()) {
                throw new RuntimeException('Failed to get balance: '.($response->json('message') ?? 'Unknown error'));
            }

            return [
                'balance' => (float) ($response->json('balance') ?? 0),
            ];
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get XenPlatform balance: {$e->getMessage()}");
        }
    }

    /**
     * Generate a QRIS QR code for a sub-account.
     */
    public function generateQris(string $xenditAccountId, QrisRequest $request): QrisResponse
    {
        $this->ensureApiKey();

        $expiryMinutes = $request->expiryMinutes ?? 30;
        $expiresAt = now()->addMinutes($expiryMinutes);

        Log::info('XenPlatform: Generating QRIS', [
            'xendit_account_id' => $xenditAccountId,
            'order_id' => $request->orderId,
            'amount' => $request->amount,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'api-version' => '2022-07-31',
                    'for-user-id' => $xenditAccountId,
                ])
                ->timeout(30)
                ->post("{$this->baseUrl}/qr_codes", [
                    'reference_id' => $request->orderId,
                    'type' => 'DYNAMIC',
                    'currency' => 'IDR',
                    'amount' => (int) $request->amount,
                    'expires_at' => $expiresAt->toIso8601String(),
                ]);

            if (! $response->successful()) {
                $errorMessage = $response->json('message') ?? $response->json('error_code') ?? 'Failed to generate QRIS';

                Log::error('XenPlatform: QRIS generation failed', [
                    'xendit_account_id' => $xenditAccountId,
                    'order_id' => $request->orderId,
                    'status_code' => $response->status(),
                    'error' => $errorMessage,
                ]);

                throw new RuntimeException("QRIS generation failed: {$errorMessage}");
            }

            $data = $response->json();

            // DEBUG: Log full response to verify for-user-id is working
            Log::debug('XenPlatform: QRIS full response', [
                'for_user_id_sent' => $xenditAccountId,
                'response_body' => $data,
                'response_headers' => $response->headers(),
            ]);

            // Convert qr_string to QR code image URL
            $qrString = $data['qr_string'] ?? '';
            $qrCodeUrl = $this->generateQrCodeImageUrl($qrString);

            Log::info('XenPlatform: QRIS generated', [
                'xendit_account_id' => $xenditAccountId,
                'order_id' => $request->orderId,
                'qr_id' => $data['id'] ?? null,
            ]);

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
                    'qr_string' => $qrString,
                ]
            );
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('XenPlatform: Unexpected error generating QRIS', [
                'xendit_account_id' => $xenditAccountId,
                'order_id' => $request->orderId,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Failed to generate QRIS: {$e->getMessage()}");
        }
    }

    /**
     * Create a payout (withdrawal) from a sub-account to a bank account.
     *
     * @param  array{bank_code: string, bank_account_number: string, bank_account_name: string}  $bankDetails
     * @return array{id: string, status: string, reference_id: string}
     */
    public function createPayout(string $xenditAccountId, float $amount, array $bankDetails, string $referenceId): array
    {
        $this->ensureApiKey();

        Log::info('XenPlatform: Creating payout', [
            'xendit_account_id' => $xenditAccountId,
            'amount' => $amount,
            'bank_code' => $bankDetails['bank_code'],
            'reference_id' => $referenceId,
        ]);

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'for-user-id' => $xenditAccountId,
                    'Idempotency-key' => $referenceId,
                ])
                ->timeout(30)
                ->post("{$this->baseUrl}/v2/payouts", [
                    'reference_id' => $referenceId,
                    'channel_code' => $bankDetails['bank_code'],
                    'channel_properties' => [
                        'account_number' => $bankDetails['bank_account_number'],
                        'account_holder_name' => $bankDetails['bank_account_name'],
                    ],
                    'amount' => (int) $amount,
                    'currency' => 'IDR',
                    'description' => "Withdrawal {$referenceId}",
                ]);

            if (! $response->successful()) {
                $errorMessage = $response->json('message') ?? $response->json('error_code') ?? 'Failed to create payout';

                Log::error('XenPlatform: Payout creation failed', [
                    'xendit_account_id' => $xenditAccountId,
                    'reference_id' => $referenceId,
                    'status_code' => $response->status(),
                    'error' => $errorMessage,
                ]);

                throw new RuntimeException("Payout failed: {$errorMessage}");
            }

            $data = $response->json();

            Log::info('XenPlatform: Payout created', [
                'xendit_account_id' => $xenditAccountId,
                'payout_id' => $data['id'] ?? null,
                'status' => $data['status'] ?? null,
            ]);

            return [
                'id' => $data['id'],
                'status' => $data['status'] ?? 'ACCEPTED',
                'reference_id' => $referenceId,
            ];
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('XenPlatform: Unexpected error creating payout', [
                'xendit_account_id' => $xenditAccountId,
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Failed to create payout: {$e->getMessage()}");
        }
    }

    /**
     * Get payout status.
     *
     * @return array{id: string, status: string}
     */
    public function getPayoutStatus(string $payoutId, string $xenditAccountId): array
    {
        $this->ensureApiKey();

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'for-user-id' => $xenditAccountId,
                ])
                ->timeout(10)
                ->get("{$this->baseUrl}/v2/payouts/{$payoutId}");

            if (! $response->successful()) {
                throw new RuntimeException('Failed to get payout status: '.($response->json('message') ?? 'Unknown error'));
            }

            $data = $response->json();

            return [
                'id' => $data['id'],
                'status' => $data['status'] ?? 'UNKNOWN',
            ];
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get payout status: {$e->getMessage()}");
        }
    }

    /**
     * Check QR code transaction status.
     *
     * @return array{status: string, amount: float|null}
     */
    public function checkQrisStatus(string $qrCodeId, string $xenditAccountId): array
    {
        $this->ensureApiKey();

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'for-user-id' => $xenditAccountId,
                ])
                ->timeout(10)
                ->get("{$this->baseUrl}/qr_codes/{$qrCodeId}");

            if (! $response->successful()) {
                throw new RuntimeException('Failed to check QRIS status: '.($response->json('message') ?? 'Unknown error'));
            }

            $data = $response->json();

            return [
                'status' => $this->mapXenditStatus($data['status'] ?? 'ACTIVE'),
                'amount' => isset($data['amount']) ? (float) $data['amount'] : null,
                'original_status' => $data['status'] ?? null,
            ];
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to check QRIS status: {$e->getMessage()}");
        }
    }

    /**
     * Verify an incoming webhook signature.
     */
    public function verifyWebhookSignature(string $signature): bool
    {
        if (empty($this->webhookToken)) {
            Log::warning('XenPlatform: Webhook token not configured');

            return false;
        }

        $result = hash_equals($this->webhookToken, $signature);

        if (! $result) {
            Log::warning('XenPlatform: Webhook signature verification failed', [
                'expected_length' => strlen($this->webhookToken),
                'received_length' => strlen($signature),
                'received_prefix' => substr($signature, 0, 8).'...',
            ]);
        }

        return $result;
    }

    /**
     * Parse a QRIS webhook payload into a standard WebhookTransaction.
     * Handles both wrapped { event, data } and direct formats.
     */
    public function parseQrisWebhookPayload(array $payload): WebhookTransaction
    {
        $status = $this->mapXenditStatus($payload['status'] ?? 'ACTIVE');

        // Extract payment timestamp - different webhook formats use different field names
        $paidAt = null;
        if ($status === 'settlement') {
            $paidAt = $payload['updated'] ?? $payload['created'] ?? $payload['paid_at'] ?? now()->toIso8601String();
        }

        // API version 2022-07-31 uses 'reference_id' instead of 'external_id'
        $externalId = $payload['reference_id'] ?? $payload['external_id'] ?? $payload['id'] ?? '';

        // Get QR ID for reference
        $qrId = $payload['qr_id'] ?? $payload['id'] ?? null;

        return new WebhookTransaction(
            externalId: $externalId,
            status: $status,
            amount: isset($payload['amount']) ? (float) $payload['amount'] : 0.0,
            paidAt: $paidAt,
            provider: 'xendit',
            referenceId: $qrId,
            metadata: [
                'type' => $payload['type'] ?? null,
                'currency' => $payload['currency'] ?? null,
                'original_status' => $payload['status'] ?? null,
                'qr_string' => $payload['qr_string'] ?? null,
                'channel_code' => $payload['channel_code'] ?? null,
            ]
        );
    }

    /**
     * Map Xendit status to internal status.
     * Handles statuses from both QR Codes API and Payment Requests API.
     */
    private function mapXenditStatus(string $xenditStatus): string
    {
        return match (strtoupper($xenditStatus)) {
            'COMPLETED', 'PAID', 'SUCCEEDED' => 'settlement',
            'ACTIVE', 'PENDING' => 'pending',
            'INACTIVE', 'EXPIRED' => 'expire',
            'FAILED', 'CANCELLED', 'CANCELED' => 'failed',
            default => 'pending',
        };
    }

    /**
     * Generate QR code image URL from QRIS string data.
     */
    private function generateQrCodeImageUrl(string $qrString): string
    {
        if (empty($qrString)) {
            return '';
        }

        $encodedData = urlencode($qrString);

        return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedData}";
    }

    /**
     * Ensure the API key is configured.
     */
    private function ensureApiKey(): void
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('Xendit API key is not configured. Set XENDIT_API_KEY in .env');
        }
    }
}
