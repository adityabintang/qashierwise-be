<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\QrisTransaction;
use App\Models\Reservation;
use App\Services\AiAgentService;
use App\Services\BalanceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job for processing successful QRIS payments asynchronously.
 *
 * This job handles:
 * - Balance updates with platform fee calculation
 * - Platform fee record creation
 * - Transaction logging and audit trail
 * - Cascading status updates to Payment and Order records
 */
class ProcessQrisPayment implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60];

    /**
     * The QRIS transaction to process.
     */
    protected QrisTransaction $transaction;

    /**
     * The webhook payload from the payment provider.
     */
    protected array $payload;

    /**
     * Create a new job instance.
     *
     * @param  QrisTransaction  $transaction  The settled transaction
     * @param  array  $payload  The webhook payload
     */
    public function __construct(QrisTransaction $transaction, array $payload = [])
    {
        $this->transaction = $transaction;
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(BalanceService $balanceService, AiAgentService $aiAgentService): void
    {
        Log::info('ProcessQrisPayment job started', [
            'order_id' => $this->transaction->order_id,
            'amount' => $this->transaction->amount,
        ]);

        try {
            // Refresh transaction to get latest state
            $this->transaction->refresh();

            // Verify transaction is still in settlement status
            if (! $this->transaction->isSettled()) {
                Log::warning('Transaction no longer in settlement status, skipping', [
                    'order_id' => $this->transaction->order_id,
                    'status' => $this->transaction->status,
                ]);

                return;
            }

            $reservation = Reservation::where('qris_transaction_id', $this->transaction->id)
                ->where('status', Reservation::STATUS_PENDING_PAYMENT)
                ->first();

            if ($reservation) {
                ProcessReservationPayment::dispatch($reservation, 'success', $this->transaction);

                Log::info('Reservation payment queued from QRIS processing', [
                    'reservation_id' => $reservation->id,
                    'order_id' => $this->transaction->order_id,
                ]);
            }

            // Check if platform fee already exists (prevent duplicate processing)
            if ($this->transaction->platformFee()->exists()) {
                Log::info('Platform fee already exists, skipping duplicate processing', [
                    'order_id' => $this->transaction->order_id,
                ]);

                return;
            }

            // Process the payment within a database transaction
            DB::transaction(function () use ($balanceService) {
                $this->processPayment($balanceService);
                $this->updateLinkedPaymentAndOrder();
            });

            // Send WhatsApp notification to customer (outside DB transaction)
            $this->sendPaymentNotification($aiAgentService);

            Log::info('ProcessQrisPayment job completed successfully', [
                'order_id' => $this->transaction->order_id,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessQrisPayment job failed', [
                'order_id' => $this->transaction->order_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Process the payment: update balance and create fee record.
     */
    private function processPayment(BalanceService $balanceService): void
    {
        $merchant = $this->transaction->subMerchant;

        if (! $merchant) {
            throw new \RuntimeException('Transaction has no associated sub-merchant');
        }

        if (! $merchant->balance) {
            throw new \RuntimeException('Sub-merchant has no balance record');
        }

        // Get the net amount (already calculated when transaction was created)
        $netAmount = (float) $this->transaction->net_amount;
        $grossAmount = (float) $this->transaction->amount;
        $platformFeeAmount = (float) $this->transaction->platform_fee;

        Log::info('Processing payment balance update', [
            'order_id' => $this->transaction->order_id,
            'sub_merchant_id' => $merchant->id,
            'gross_amount' => $grossAmount,
            'platform_fee' => $platformFeeAmount,
            'net_amount' => $netAmount,
        ]);

        // Update merchant balance
        $balanceService->updateBalance(
            $merchant,
            $netAmount,
            'payment',
            $this->transaction->order_id
        );

        // Create platform fee record for audit trail
        PlatformFee::createForTransaction($this->transaction);

        // Log the complete transaction for audit purposes
        $this->logTransactionAudit($merchant, $grossAmount, $platformFeeAmount, $netAmount);
    }

    /**
     * Log transaction details for audit trail.
     *
     * @param  mixed  $merchant
     */
    private function logTransactionAudit($merchant, float $grossAmount, float $platformFeeAmount, float $netAmount): void
    {
        Log::info('QRIS Payment Audit Trail', [
            'event' => 'payment_processed',
            'order_id' => $this->transaction->order_id,
            'provider_transaction_id' => $this->transaction->provider_transaction_id,
            'sub_merchant_id' => $merchant->id,
            'user_id' => $merchant->user_id,
            'gross_amount' => $grossAmount,
            'platform_fee_percentage' => PlatformFee::DEFAULT_FEE_PERCENTAGE,
            'platform_fee_amount' => $platformFeeAmount,
            'net_amount' => $netAmount,
            'settled_at' => $this->transaction->settled_at?->toIso8601String(),
            'new_available_balance' => $merchant->balance->fresh()->available_balance,
            'webhook_payload' => [
                'reference_id' => $this->payload['reference_id'] ?? $this->payload['id'] ?? null,
                'status' => $this->payload['status'] ?? null,
                'type' => $this->payload['type'] ?? null,
            ],
        ]);
    }

    /**
     * Update linked Payment and Order records when QRIS is settled.
     */
    private function updateLinkedPaymentAndOrder(): void
    {
        // Find Payment record linked to this QRIS transaction
        $payment = Payment::where('qris_transaction_id', $this->transaction->id)->first();

        if ($payment) {
            Log::info('Updating linked Payment record', [
                'qris_order_id' => $this->transaction->order_id,
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
            ]);

            // Mark payment as paid (this also updates the linked Order)
            $payment->markAsPaid();

            Log::info('Payment and Order status updated', [
                'payment_id' => $payment->id,
                'payment_status' => $payment->status,
                'order_status' => $payment->order?->status,
            ]);
        } else {
            // Check if there's a linked order directly on the QRIS transaction
            if ($this->transaction->linked_order_id) {
                $order = $this->transaction->linkedOrder;
                if ($order) {
                    Log::info('Updating linked Order directly', [
                        'qris_order_id' => $this->transaction->order_id,
                        'order_id' => $order->id,
                    ]);

                    $order->update(['status' => 'paid']);
                }
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessQrisPayment job failed permanently', [
            'order_id' => $this->transaction->order_id,
            'sub_merchant_id' => $this->transaction->sub_merchant_id,
            'error' => $exception->getMessage(),
        ]);

        // Here you could:
        // - Send notification to admin
        // - Create a failed payment record for manual review
        // - Trigger an alert in monitoring system
    }

    /**
     * Send payment notification to customer via WhatsApp if applicable.
     */
    private function sendPaymentNotification(AiAgentService $aiAgentService): void
    {
        Log::info('Attempting to send WhatsApp payment notification', [
            'order_id' => $this->transaction->order_id,
            'qris_transaction_id' => $this->transaction->id,
        ]);

        try {
            $aiAgentService->sendPaymentConfirmation($this->transaction);

            Log::info('WhatsApp payment notification sent successfully', [
                'order_id' => $this->transaction->order_id,
            ]);
        } catch (\Exception $e) {
            // Log but don't fail the job - notification is optional
            Log::warning('Failed to send payment notification, continuing', [
                'order_id' => $this->transaction->order_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
