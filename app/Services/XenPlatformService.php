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

    /** Maximum email-alias retries when the base email is already registered. */
    private const MAX_EMAIL_ALIAS_ATTEMPTS = 10;

    /**
     * Create an OWNED sub-account on XenPlatform.
     *
     * When the user's email already exists on Xendit (BUSINESS_DUPLICATE_EMAIL_ERROR,
     * 409) we transparently retry with `name+01@domain` → `name+10@domain` aliases
     * before giving up. Most providers route `+suffix` aliases to the same inbox,
     * so the merchant still receives Xendit notifications.
     *
     * @return array{id: string, status: string} The created account data
     *
     * @throws RuntimeException If API call fails or all alias attempts exhausted
     */
    public function createSubAccount(SubMerchant $merchant): array
    {
        $this->ensureApiKey();

        $businessName = $merchant->business_name ?? $merchant->user->name ?? 'Merchant';
        $originalEmail = $merchant->user->email ?? null;

        // 1 attempt with the raw email, plus N alias retries on duplicate.
        $maxAttempts = $originalEmail ? self::MAX_EMAIL_ALIAS_ATTEMPTS + 1 : 1;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $email = $attempt === 0
                ? $originalEmail
                : $this->aliasEmail($originalEmail, $attempt);

            Log::info('XenPlatform: Creating sub-account', [
                'sub_merchant_id' => $merchant->id,
                'business_name' => $businessName,
                'email' => $email,
                'attempt' => $attempt,
            ]);

            try {
                $response = $this->postCreateAccount($businessName, $email);
            } catch (\Exception $e) {
                Log::error('XenPlatform: Unexpected error creating sub-account', [
                    'sub_merchant_id' => $merchant->id,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
                throw new RuntimeException("Failed to create XenPlatform sub-account: {$e->getMessage()}");
            }

            if ($response->successful()) {
                $data = $response->json();
                Log::info('XenPlatform: Sub-account created', [
                    'sub_merchant_id' => $merchant->id,
                    'xendit_account_id' => $data['id'] ?? null,
                    'email_used' => $email,
                    'attempts_taken' => $attempt + 1,
                ]);

                // XenPlatform sub-accounts do NOT inherit master webhook URLs.
                // Register the QRIS webhook on this sub-account so qr.payment
                // events make it back to our /api/webhooks/xendit endpoint.
                // Errors here are non-fatal — sub-account creation already
                // succeeded, the admin can re-run via xendit:sync-webhooks.
                try {
                    $this->setupSubAccountWebhooks($data['id']);
                } catch (\Throwable $e) {
                    Log::warning('XenPlatform: webhook setup failed (non-fatal)', [
                        'sub_merchant_id'    => $merchant->id,
                        'xendit_account_id'  => $data['id'] ?? null,
                        'error'              => $e->getMessage(),
                    ]);
                }

                return [
                    'id' => $data['id'],
                    'status' => $data['status'] ?? 'ACTIVE',
                ];
            }

            $isDuplicateEmail = $response->status() === 409
                && $response->json('error_code') === 'BUSINESS_DUPLICATE_EMAIL_ERROR';

            // Non-retryable error OR we have no email to alias — surface immediately.
            if (! $isDuplicateEmail || ! $originalEmail) {
                $errorMessage = $response->json('message') ?? $response->json('error_code') ?? 'Failed to create sub-account';

                Log::error('XenPlatform: Failed to create sub-account', [
                    'sub_merchant_id' => $merchant->id,
                    'status_code' => $response->status(),
                    'error' => $errorMessage,
                    'response_body' => $response->body(),
                ]);

                throw new RuntimeException("XenPlatform API error: {$errorMessage}");
            }

            Log::warning('XenPlatform: Email already registered, trying next alias', [
                'sub_merchant_id' => $merchant->id,
                'attempted_email' => $email,
                'next_attempt' => $attempt + 1,
            ]);
        }

        // All alias attempts exhausted.
        Log::error('XenPlatform: All email aliases exhausted', [
            'sub_merchant_id' => $merchant->id,
            'original_email' => $originalEmail,
            'aliases_tried' => self::MAX_EMAIL_ALIAS_ATTEMPTS,
        ]);

        throw new RuntimeException(
            "Email {$originalEmail} dan 10 variasi alias (+01 sampai +10) semuanya sudah terdaftar di Xendit. ".
            'Silakan gunakan akun dengan email berbeda atau hubungi support.'
        );
    }

    /**
     * Build a `localpart+NN@domain` alias from the merchant's email.
     * Pads the index to 2 digits so the aliases sort naturally (+01..+10).
     */
    private function aliasEmail(string $email, int $index): string
    {
        $padded = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            // No @ — give up gracefully; caller will see a duplicate error.
            return $email;
        }

        $local = substr($email, 0, $atPos);
        $domain = substr($email, $atPos + 1);

        return "{$local}+{$padded}@{$domain}";
    }

    /**
     * Register the public callback URL on a sub-account for every webhook type
     * we care about, and persist the callback_token Xendit returns to
     * sub_merchants so verifyWebhookSignature can match incoming signatures.
     *
     * XenPlatform sub-accounts have their OWN per-account callback config —
     * they do not inherit master account webhook URLs. Each sub-account gets
     * a unique callback_token from Xendit (empirically verified: same token
     * across event types on the same sub-account; custom token in request
     * body ignored — Xendit always auto-generates).
     *
     * Idempotent: re-calling returns the SAME callback_token for that account.
     *
     * @return array<string, array{ok: bool, token?: string}>  type => result
     */
    public function setupSubAccountWebhooks(string $xenditAccountId): array
    {
        $url = config('xendit.webhook_url');
        if (! $url || ! str_starts_with($url, 'https://')) {
            throw new RuntimeException(
                'XENDIT_WEBHOOK_URL belum diset atau bukan HTTPS. Sub-account webhook tidak bisa di-register.'
            );
        }

        // Xendit callback_urls API expects specific enum values — verified
        // empirically with HTTP 400 API_VALIDATION_ERROR for the wrong names.
        // Valid enums (probed 2026-05-28):
        //   qr_code, invoice, ewallet, direct_debit, disbursement, payment_method.
        // The critical one for our QRIS flow is `qr_code` (NOT qr_payments).
        $types = ['qr_code', 'invoice', 'ewallet', 'direct_debit'];

        $results = [];
        $tokenForPersist = null;
        foreach ($types as $type) {
            $r = $results[$type] = $this->setCallbackUrl($xenditAccountId, $type, $url);
            // qr_code is the canonical token source for our flow; otherwise
            // take the first available (Xendit returns the same token for all
            // event types on a sub-account).
            if ($type === 'qr_code' && ! empty($r['token'])) {
                $tokenForPersist = $r['token'];
            } elseif (! $tokenForPersist && ! empty($r['token'])) {
                $tokenForPersist = $r['token'];
            }
        }

        if ($tokenForPersist) {
            \App\Models\SubMerchant::where('xendit_account_id', $xenditAccountId)
                ->update(['xendit_callback_token' => $tokenForPersist]);
        }

        Log::info('XenPlatform: sub-account webhooks registered', [
            'xendit_account_id' => $xenditAccountId,
            'url'               => $url,
            'token_saved'       => $tokenForPersist ? 'yes' : 'no',
            'results'           => array_map(fn ($r) => $r['ok'] ?? false, $results),
        ]);

        return $results;
    }

    /**
     * Set the callback URL for one event type on a specific sub-account.
     *
     * Uses POST /callback_urls/{type} with `for-user-id` header (XenPlatform
     * pattern). Returns array with ok + token from the response.
     *
     * @return array{ok: bool, token?: string}
     */
    private function setCallbackUrl(string $xenditAccountId, string $type, string $url): array
    {
        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->withHeaders([
                    'for-user-id'  => $xenditAccountId,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(15)
                ->post("{$this->baseUrl}/callback_urls/{$type}", [
                    'url' => $url,
                ]);

            if ($response->successful()) {
                return ['ok' => true, 'token' => $response->json('callback_token')];
            }

            Log::warning('XenPlatform: setCallbackUrl failed', [
                'xendit_account_id' => $xenditAccountId,
                'type'              => $type,
                'status'            => $response->status(),
                'body'              => $response->body(),
            ]);
            return ['ok' => false];
        } catch (\Throwable $e) {
            Log::warning('XenPlatform: setCallbackUrl threw', [
                'xendit_account_id' => $xenditAccountId,
                'type'              => $type,
                'error'             => $e->getMessage(),
            ]);
            return ['ok' => false];
        }
    }

    /**
     * Single HTTP call to the create-account endpoint. Extracted so the retry
     * loop in createSubAccount() stays focused on retry logic, not payload build.
     */
    private function postCreateAccount(string $businessName, ?string $email): \Illuminate\Http\Client\Response
    {
        $payload = [
            'type' => 'MANAGED',
            'country' => 'ID',
            'public_profile' => ['business_name' => $businessName],
        ];

        if ($email) {
            $payload['email'] = $email;
        }

        return Http::withBasicAuth($this->apiKey, '')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->post("{$this->baseUrl}/v2/accounts", $payload);
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
     *
     * Sub-account webhooks (XenPlatform): signed with the sub-account's
     * own callback_token, NOT the master account token. Caller must pass
     * the sub-account id (from payload.business_id / payload.data.business_id)
     * so we can look up the stored per-account token.
     *
     * Master account direct webhooks: pass $subAccountId = null. Falls back
     * to XENDIT_WEBHOOK_TOKEN (.env) — used for non-XenPlatform integrations
     * like the subscription billing webhook.
     */
    public function verifyWebhookSignature(string $signature, ?string $subAccountId = null): bool
    {
        // Sub-account context: look up its callback_token from sub_merchants.
        // When a token IS found, verify strictly against it — real QRIS payments
        // use this path.
        // When NO token is found (e.g. Xendit dashboard "Test Webhook" button
        // which sends fake business_ids), fall through to master token so
        // connectivity tests pass without requiring a matching sub-account row.
        if ($subAccountId) {
            $token = \App\Models\SubMerchant::where('xendit_account_id', $subAccountId)
                ->value('xendit_callback_token');

            if ($token) {
                $result = hash_equals($token, $signature);
                if (! $result) {
                    Log::warning('XenPlatform: sub-account signature mismatch', [
                        'xendit_account_id' => $subAccountId,
                        'expected_length'   => strlen($token),
                        'received_length'   => strlen($signature),
                    ]);
                }
                return $result;
            }

            // No stored token for this sub-account ID — the business_id in the
            // payload is likely Xendit's hardcoded test value (not a real
            // sub-merchant). Fall through to master token verification.
            Log::info('XenPlatform: sub-account not found, trying master token fallback', [
                'xendit_account_id' => $subAccountId,
            ]);
        }

        // Master context: used for master-account webhooks and as a fallback
        // when the sub-account from the payload doesn't match any stored row
        // (Xendit dashboard test events use fake business_ids + master token).
        if (empty($this->webhookToken)) {
            Log::warning('XenPlatform: Webhook token not configured');
            return false;
        }

        $result = hash_equals($this->webhookToken, $signature);
        if (! $result) {
            Log::warning('XenPlatform: Webhook signature verification failed (master)', [
                'expected_length' => strlen($this->webhookToken),
                'received_length' => strlen($signature),
                'received_prefix' => substr($signature, 0, 8) . '...',
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
