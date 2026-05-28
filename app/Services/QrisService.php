<?php

namespace App\Services;

use App\DTOs\QrisRequest;
use App\DTOs\WebhookTransaction;
use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class QrisService
{
    /**
     * Financial audit service for logging.
     */
    protected ?FinancialAuditService $auditService = null;

    public function __construct(
        private XenPlatformService $xenPlatformService,
    ) {}

    /**
     * Set the audit service for logging.
     */
    public function setAuditService(FinancialAuditService $auditService): self
    {
        $this->auditService = $auditService;

        return $this;
    }

    /**
     * Generate a QRIS code for a sub-merchant transaction using XenPlatform.
     *
     * @param  SubMerchant  $merchant  The sub-merchant generating the QRIS
     * @param  float  $amount  Transaction amount in IDR
     * @param  array  $details  Additional transaction details (description, customer_name, etc.)
     * @return QrisTransaction The created QRIS transaction
     *
     * @throws InvalidArgumentException If validation fails
     * @throws RuntimeException If API call fails
     */
    public function generateQris(SubMerchant $merchant, float $amount, array $details = []): QrisTransaction
    {
        // Validate merchant can accept payments
        if (! $merchant->is_active) {
            throw new InvalidArgumentException('Sub-merchant is not active');
        }

        // Validate merchant has XenPlatform account
        if (! $merchant->hasXenditAccount()) {
            throw new RuntimeException('Sub-merchant does not have an active XenPlatform account. Please contact support.');
        }

        // Validate amount
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }

        // Generate unique order ID
        $orderId = QrisTransaction::generateOrderId();

        // Calculate fees
        $platformFee = QrisTransaction::calculatePlatformFee($amount);
        $netAmount = QrisTransaction::calculateNetAmount($amount);

        // Set expiration time
        $expiresAt = now()->addMinutes($details['expiry_minutes'] ?? QrisTransaction::DEFAULT_EXPIRY_MINUTES);

        return DB::transaction(function () use ($merchant, $orderId, $amount, $platformFee, $netAmount, $expiresAt, $details) {
            // Create transaction record first
            $transaction = QrisTransaction::create([
                'sub_merchant_id' => $merchant->id,
                'order_id' => $orderId,
                'amount' => $amount,
                'platform_fee' => $platformFee,
                'net_amount' => $netAmount,
                'status' => QrisTransaction::STATUS_PENDING,
                'provider' => 'xendit',
                'expires_at' => $expiresAt,
            ]);

            // Create QRIS request DTO
            $qrisRequest = new QrisRequest(
                orderId: $orderId,
                amount: $amount,
                description: $details['description'] ?? null,
                expiryMinutes: $details['expiry_minutes'] ?? QrisTransaction::DEFAULT_EXPIRY_MINUTES,
                metadata: $details
            );

            // Generate QRIS via XenPlatform with for-user-id header
            $qrisResponse = $this->xenPlatformService->generateQris(
                $merchant->xendit_account_id,
                $qrisRequest
            );

            // Update transaction with provider response
            $transaction->update([
                'qr_code_url' => $qrisResponse->qrCodeUrl,
                'provider_transaction_id' => $qrisResponse->providerTransactionId,
            ]);

            Log::info('QRIS generated via XenPlatform', [
                'order_id' => $orderId,
                'sub_merchant_id' => $merchant->id,
                'xendit_account_id' => $merchant->xendit_account_id,
                'amount' => $amount,
            ]);

            // Log to financial audit trail
            if ($this->auditService !== null) {
                $this->auditService->logQrisGeneration($merchant, $transaction);
            }

            return $transaction->fresh();
        });
    }

    /**
     * Get the QR code image URL for a transaction.
     */
    public function getQrCodeUrl(QrisTransaction $transaction): ?string
    {
        return $transaction->qr_code_url;
    }

    /**
     * Generate a shareable link for a QRIS transaction.
     */
    public function generateShareableLink(QrisTransaction $transaction): string
    {
        return $transaction->getShareableLink();
    }

    /**
     * Validate if a QRIS transaction can still be used.
     */
    public function validateQrisExpiry(QrisTransaction $transaction): bool
    {
        return $transaction->canBeUsed();
    }

    /**
     * Check if a QRIS transaction is expired.
     */
    public function isExpired(QrisTransaction $transaction): bool
    {
        return $transaction->isExpired();
    }

    /**
     * Mark expired pending transactions as expired.
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

            if ($this->auditService !== null && $transaction->subMerchant !== null) {
                $this->auditService->logQrisExpiration($transaction->subMerchant, $transaction);
            }
        }

        return $count;
    }

    /**
     * Find a QRIS transaction by order ID.
     */
    public function findByOrderId(string $orderId): ?QrisTransaction
    {
        return QrisTransaction::where('order_id', $orderId)->first();
    }

    /**
     * Find a QRIS transaction by provider transaction ID.
     */
    public function findByProviderTransactionId(string $transactionId): ?QrisTransaction
    {
        return QrisTransaction::where('provider_transaction_id', $transactionId)->first();
    }

    /**
     * Get transaction history for a sub-merchant.
     */
    public function getTransactionHistory(SubMerchant $merchant, int $limit = 50)
    {
        return $merchant->transactions()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get pending transactions for a sub-merchant.
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
     */
    public function cancelTransaction(QrisTransaction $transaction): bool
    {
        if (! $transaction->isPending()) {
            throw new InvalidArgumentException('Only pending transactions can be cancelled');
        }

        $transaction->markAsCancelled();
        $result = $transaction->save();

        if ($result) {
            Log::info('QRIS transaction cancelled', [
                'order_id' => $transaction->order_id,
            ]);

            if ($this->auditService !== null && $transaction->subMerchant !== null) {
                $this->auditService->logQrisCancellation($transaction->subMerchant, $transaction);
            }
        }

        return $result;
    }

    /**
     * Handle webhook notification from Xendit.
     * Verifies webhook signature and updates transaction status.
     *
     * @throws \App\Exceptions\InvalidWebhookException If webhook signature is invalid
     * @throws RuntimeException If webhook processing fails
     */
    public function handleWebhook(array $payload, string $signature): void
    {
        Log::info('Processing Xendit webhook', [
            'payload_keys' => array_keys($payload),
        ]);

        // Sub-account context: business_id in the payload identifies which
        // sub-account this webhook is for. Its own callback_token signs the
        // signature header, NOT the master token. Falls back to null (master
        // verification) when business_id is absent.
        $subAccountId = $payload['business_id']
            ?? ($payload['data']['business_id'] ?? null);

        if (! $this->xenPlatformService->verifyWebhookSignature($signature, $subAccountId)) {
            Log::warning('Invalid webhook signature', [
                'sub_account_id' => $subAccountId,
            ]);
            throw new \App\Exceptions\InvalidWebhookException('Invalid webhook signature');
        }

        Log::info('Webhook signature verified');

        try {
            // Parse webhook payload into standard format
            $webhookTransaction = $this->xenPlatformService->parseQrisWebhookPayload($payload);

            Log::info('Webhook payload parsed', [
                'external_id' => $webhookTransaction->externalId,
                'status' => $webhookTransaction->status,
            ]);

            // Update transaction status
            $this->updateTransactionStatus($webhookTransaction);

        } catch (\App\Exceptions\InvalidWebhookException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Failed to process webhook: {$e->getMessage()}");
        }
    }

    /**
     * Update transaction status from webhook data.
     */
    public function updateTransactionStatus(WebhookTransaction $webhookTransaction): void
    {
        Log::info('Updating transaction status from webhook', [
            'external_id' => $webhookTransaction->externalId,
            'status' => $webhookTransaction->status,
        ]);

        // Find transaction by order_id (external_id)
        $transaction = QrisTransaction::where('order_id', $webhookTransaction->externalId)->first();

        if (! $transaction) {
            Log::warning('Transaction not found for webhook', [
                'external_id' => $webhookTransaction->externalId,
            ]);

            // Don't throw exception - just log and return
            // This allows webhook to return 200 OK and prevent retries
            return;
        }

        // Don't update if transaction is already in a final state
        if (in_array($transaction->status, [
            QrisTransaction::STATUS_SETTLEMENT,
            QrisTransaction::STATUS_CANCEL,
        ])) {
            Log::info('Transaction already in final state, skipping update', [
                'order_id' => $transaction->order_id,
                'current_status' => $transaction->status,
            ]);

            return;
        }

        try {
            DB::transaction(function () use ($transaction, $webhookTransaction) {
                $newStatus = $this->mapWebhookStatusToTransactionStatus($webhookTransaction->status);

                $updateData = [
                    'status' => $newStatus,
                ];

                if ($webhookTransaction->referenceId !== null) {
                    $updateData['reference_id'] = $webhookTransaction->referenceId;
                }

                if ($newStatus === QrisTransaction::STATUS_SETTLEMENT) {
                    $updateData['paid_at'] = $webhookTransaction->paidAt ?? now();
                    $updateData['settled_at'] = $webhookTransaction->paidAt ?? now();
                }

                $transaction->update($updateData);

                Log::info('Transaction status updated', [
                    'order_id' => $transaction->order_id,
                    'new_status' => $newStatus,
                ]);

                if ($newStatus === QrisTransaction::STATUS_SETTLEMENT) {
                    // Check if this is a reservation payment
                    $reservation = \App\Models\Reservation::where('qris_transaction_id', $transaction->id)->first();

                    if ($reservation) {
                        // Dispatch reservation payment processing job
                        \App\Jobs\ProcessReservationPayment::dispatch($reservation, 'success', $transaction);

                        Log::info('Reservation payment detected, ProcessReservationPayment job dispatched', [
                            'order_id' => $transaction->order_id,
                            'reservation_id' => $reservation->id,
                            'transaction_id' => $transaction->id,
                        ]);
                    } else {
                        // Regular order payment - dispatch normal job
                        \App\Jobs\ProcessQrisPayment::dispatch($transaction);

                        Log::info('Transaction settled, ProcessQrisPayment job dispatched', [
                            'order_id' => $transaction->order_id,
                            'transaction_id' => $transaction->id,
                        ]);
                    }
                } elseif (in_array($newStatus, [QrisTransaction::STATUS_CANCEL, QrisTransaction::STATUS_EXPIRE])) {
                    // Check if this is a failed/expired reservation payment
                    $reservation = \App\Models\Reservation::where('qris_transaction_id', $transaction->id)->first();

                    if ($reservation) {
                        // Dispatch reservation payment failure job
                        \App\Jobs\ProcessReservationPayment::dispatch($reservation, 'failed', $transaction);

                        Log::info('Reservation payment failed/expired', [
                            'order_id' => $transaction->order_id,
                            'reservation_id' => $reservation->id,
                            'transaction_id' => $transaction->id,
                            'status' => $newStatus,
                        ]);
                    }
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to update transaction status', [
                'order_id' => $transaction->order_id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - just log error
        }
    }

    /**
     * Map webhook status to QrisTransaction status constants.
     */
    private function mapWebhookStatusToTransactionStatus(string $webhookStatus): string
    {
        return match (strtolower($webhookStatus)) {
            'success', 'paid', 'settlement' => QrisTransaction::STATUS_SETTLEMENT,
            'pending', 'active' => QrisTransaction::STATUS_PENDING,
            'failed', 'deny', 'cancel' => QrisTransaction::STATUS_CANCEL,
            'expired', 'expire' => QrisTransaction::STATUS_EXPIRE,
            default => QrisTransaction::STATUS_PENDING,
        };
    }
}
