<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessQrisPayment;
use App\Models\QrisTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for handling Midtrans webhook notifications.
 * 
 * Receives and processes payment status updates from Midtrans
 * including settlement, expiration, and cancellation events.
 */
class MidtransWebhookController extends Controller
{
    /**
     * Midtrans server key for signature validation.
     */
    private string $serverKey;

    public function __construct()
    {
        $this->serverKey = config('services.midtrans.server_key') ?? '';
    }

    /**
     * Handle incoming Midtrans webhook notification.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleNotification(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Midtrans webhook received', [
            'order_id' => $payload['order_id'] ?? null,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'payment_type' => $payload['payment_type'] ?? null,
        ]);

        // Validate required fields
        if (!isset($payload['order_id']) || !isset($payload['transaction_status'])) {
            Log::warning('Midtrans webhook missing required fields', $payload);
            return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
        }

        // Validate signature
        if (!$this->validateSignature($payload)) {
            Log::warning('Midtrans webhook invalid signature', [
                'order_id' => $payload['order_id'],
            ]);
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        // Find the transaction
        $transaction = QrisTransaction::where('order_id', $payload['order_id'])->first();

        if (!$transaction) {
            Log::warning('Midtrans webhook transaction not found', [
                'order_id' => $payload['order_id'],
            ]);
            // Return 200 to prevent Midtrans from retrying for unknown transactions
            return response()->json(['status' => 'ok', 'message' => 'Transaction not found'], 200);
        }

        // Process the notification based on transaction status
        try {
            $this->processPaymentNotification($transaction, $payload);
        } catch (\Exception $e) {
            Log::error('Midtrans webhook processing error', [
                'order_id' => $payload['order_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Return 200 to prevent retries, we log the error for investigation
            return response()->json(['status' => 'ok', 'message' => 'Event received'], 200);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Validate Midtrans webhook signature.
     *
     * Midtrans signature is SHA512 hash of: order_id + status_code + gross_amount + server_key
     *
     * @param array $payload Webhook payload
     * @return bool True if signature is valid
     */
    private function validateSignature(array $payload): bool
    {
        // If no server key configured, skip validation (development mode)
        if (empty($this->serverKey)) {
            Log::warning('Midtrans server key not configured, skipping signature validation');
            return true;
        }

        $signatureKey = $payload['signature_key'] ?? null;
        if (!$signatureKey) {
            Log::warning('Midtrans webhook missing signature_key');
            return false;
        }

        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';

        // Build the signature string
        $signatureString = $orderId . $statusCode . $grossAmount . $this->serverKey;
        $expectedSignature = hash('sha512', $signatureString);

        $isValid = hash_equals($expectedSignature, $signatureKey);

        if (!$isValid) {
            Log::warning('Midtrans signature mismatch', [
                'order_id' => $orderId,
                'expected' => substr($expectedSignature, 0, 20) . '...',
                'received' => substr($signatureKey, 0, 20) . '...',
            ]);
        }

        return $isValid;
    }

    /**
     * Process payment notification based on transaction status.
     *
     * @param QrisTransaction $transaction The QRIS transaction
     * @param array $payload Webhook payload
     */
    private function processPaymentNotification(QrisTransaction $transaction, array $payload): void
    {
        $transactionStatus = $payload['transaction_status'];
        $fraudStatus = $payload['fraud_status'] ?? 'accept';
        $midtransTransactionId = $payload['transaction_id'] ?? null;

        Log::info('Processing Midtrans notification', [
            'order_id' => $transaction->order_id,
            'transaction_status' => $transactionStatus,
            'fraud_status' => $fraudStatus,
            'current_status' => $transaction->status,
        ]);

        // Skip if transaction is already in a final state
        if ($transaction->isSettled() || $transaction->isCancelledOrExpired()) {
            Log::info('Transaction already in final state, skipping', [
                'order_id' => $transaction->order_id,
                'status' => $transaction->status,
            ]);
            return;
        }

        switch ($transactionStatus) {
            case 'capture':
            case 'settlement':
                // Payment successful - only process if fraud status is accept
                if ($fraudStatus === 'accept') {
                    $this->handleSettlement($transaction, $midtransTransactionId, $payload);
                } else {
                    Log::warning('Payment flagged for fraud', [
                        'order_id' => $transaction->order_id,
                        'fraud_status' => $fraudStatus,
                    ]);
                }
                break;

            case 'pending':
                // Payment pending - no action needed, transaction already pending
                Log::info('Payment pending', ['order_id' => $transaction->order_id]);
                break;

            case 'deny':
            case 'cancel':
                // Payment denied or cancelled
                $this->handleCancellation($transaction, $payload);
                break;

            case 'expire':
                // Payment expired
                $this->handleExpiration($transaction, $payload);
                break;

            case 'refund':
            case 'partial_refund':
                // Refund processed - log for now, implement refund handling if needed
                Log::info('Refund notification received', [
                    'order_id' => $transaction->order_id,
                    'status' => $transactionStatus,
                ]);
                break;

            default:
                Log::warning('Unknown transaction status', [
                    'order_id' => $transaction->order_id,
                    'status' => $transactionStatus,
                ]);
        }
    }

    /**
     * Handle successful payment settlement.
     *
     * @param QrisTransaction $transaction
     * @param string|null $midtransTransactionId
     * @param array $payload
     */
    private function handleSettlement(QrisTransaction $transaction, ?string $midtransTransactionId, array $payload): void
    {
        Log::info('Processing settlement', [
            'order_id' => $transaction->order_id,
            'midtrans_transaction_id' => $midtransTransactionId,
        ]);

        // Mark transaction as settled
        $transaction->markAsSettled($midtransTransactionId);
        $transaction->save();

        // Dispatch job to process payment asynchronously
        ProcessQrisPayment::dispatch($transaction, $payload)
            ->onQueue('payments');

        Log::info('Settlement processed, payment job dispatched', [
            'order_id' => $transaction->order_id,
        ]);
    }

    /**
     * Handle payment cancellation.
     *
     * @param QrisTransaction $transaction
     * @param array $payload
     */
    private function handleCancellation(QrisTransaction $transaction, array $payload): void
    {
        Log::info('Processing cancellation', [
            'order_id' => $transaction->order_id,
        ]);

        $transaction->markAsCancelled();
        $transaction->save();

        Log::info('Transaction cancelled', [
            'order_id' => $transaction->order_id,
        ]);
    }

    /**
     * Handle payment expiration.
     *
     * @param QrisTransaction $transaction
     * @param array $payload
     */
    private function handleExpiration(QrisTransaction $transaction, array $payload): void
    {
        Log::info('Processing expiration', [
            'order_id' => $transaction->order_id,
        ]);

        $transaction->markAsExpired();
        $transaction->save();

        Log::info('Transaction expired', [
            'order_id' => $transaction->order_id,
        ]);
    }
}
