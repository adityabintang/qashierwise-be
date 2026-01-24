<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessQrisPayment;
use App\Models\QrisTransaction;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $startTime = microtime(true);
        $payload = $request->all();

        Log::info('Midtrans webhook received', [
            'event' => 'webhook.received',
            'webhook_type' => 'payment',
            'order_id' => $payload['order_id'] ?? null,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'payment_type' => $payload['payment_type'] ?? null,
            'ip_address' => $request->ip(),
        ]);

        // Validate required fields
        if (!isset($payload['order_id']) || !isset($payload['transaction_status'])) {
            Log::warning('Midtrans webhook missing required fields', [
                'event' => 'webhook.validation_failed',
                'reason' => 'missing_required_fields',
                'payload_keys' => array_keys($payload),
            ]);
            return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
        }

        // Validate signature
        if (!$this->validateSignature($payload)) {
            Log::warning('Midtrans webhook invalid signature', [
                'event' => 'webhook.validation_failed',
                'reason' => 'invalid_signature',
                'order_id' => $payload['order_id'],
            ]);
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        // Find the transaction
        $transaction = QrisTransaction::where('order_id', $payload['order_id'])->first();

        if (!$transaction) {
            Log::warning('Midtrans webhook transaction not found', [
                'event' => 'webhook.transaction_not_found',
                'order_id' => $payload['order_id'],
            ]);
            // Return 200 to prevent Midtrans from retrying for unknown transactions
            return response()->json(['status' => 'ok', 'message' => 'Transaction not found'], 200);
        }

        // Process the notification based on transaction status
        try {
            $this->processPaymentNotification($transaction, $payload);
            
            $duration = microtime(true) - $startTime;
            Log::info('Midtrans webhook processed successfully', [
                'event' => 'webhook.processed',
                'webhook_type' => 'payment',
                'order_id' => $payload['order_id'],
                'transaction_status' => $payload['transaction_status'],
                'duration_ms' => round($duration * 1000, 2),
            ]);
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            Log::error('Midtrans webhook processing error', [
                'event' => 'webhook.processing_error',
                'webhook_type' => 'payment',
                'order_id' => $payload['order_id'],
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
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
        // This job will:
        // 1. Update merchant balance
        // 2. Create platform fee record
        // 3. Update Payment & Order status
        // 4. Send WhatsApp notification to customer automatically
        ProcessQrisPayment::dispatch($transaction, $payload)
            ->onQueue('payments');

        Log::info('Settlement processed, payment job dispatched (will send WhatsApp notification)', [
            'order_id' => $transaction->order_id,
            'queue' => 'payments',
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

        // Cascade status update to linked Payment
        $this->updateLinkedPaymentStatus($transaction, 'failed');

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

        // Cascade status update to linked Payment
        $this->updateLinkedPaymentStatus($transaction, 'expired');

        Log::info('Transaction expired', [
            'order_id' => $transaction->order_id,
        ]);
    }

    /**
     * Update linked Payment record status when QRIS transaction status changes.
     *
     * @param QrisTransaction $transaction
     * @param string $status The status to set ('failed' or 'expired')
     */
    private function updateLinkedPaymentStatus(QrisTransaction $transaction, string $status): void
    {
        $payment = \App\Models\Payment::where('qris_transaction_id', $transaction->id)->first();

        if ($payment) {
            if ($status === 'expired') {
                $payment->markAsExpired();
            } else {
                $payment->markAsFailed();
            }

            Log::info('Linked Payment status updated', [
                'qris_order_id' => $transaction->order_id,
                'payment_id' => $payment->id,
                'new_status' => $status,
            ]);
        }
    }

    /**
     * Handle incoming Midtrans subscription webhook notification.
     *
     * This method processes subscription-related webhooks including:
     * - Payment notifications (first payment)
     * - Recurring notifications (subsequent payments)
     * - Pay account notifications (subscription status changes)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleSubscriptionWebhook(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $payload = $request->all();

        Log::info('Midtrans subscription webhook received', [
            'event' => 'webhook.received',
            'webhook_type' => 'subscription',
            'order_id' => $payload['order_id'] ?? null,
            'subscription_id' => $payload['subscription_id'] ?? null,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'status_code' => $payload['status_code'] ?? null,
            'ip_address' => $request->ip(),
        ]);

        // Validate required fields
        if (!isset($payload['order_id']) || !isset($payload['status_code'])) {
            Log::warning('Midtrans subscription webhook missing required fields', [
                'event' => 'webhook.validation_failed',
                'webhook_type' => 'subscription',
                'reason' => 'missing_required_fields',
                'payload_keys' => array_keys($payload),
            ]);
            return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
        }

        // Validate signature
        if (!$this->validateSignature($payload)) {
            Log::warning('Midtrans subscription webhook invalid signature', [
                'event' => 'webhook.validation_failed',
                'webhook_type' => 'subscription',
                'reason' => 'invalid_signature',
                'order_id' => $payload['order_id'],
            ]);
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        // Process the webhook based on event type
        try {
            $this->processSubscriptionWebhook($payload);
            
            $duration = microtime(true) - $startTime;
            Log::info('Midtrans subscription webhook processed successfully', [
                'event' => 'webhook.processed',
                'webhook_type' => 'subscription',
                'order_id' => $payload['order_id'],
                'subscription_id' => $payload['subscription_id'] ?? null,
                'transaction_status' => $payload['transaction_status'] ?? null,
                'duration_ms' => round($duration * 1000, 2),
            ]);
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            Log::error('Midtrans subscription webhook processing error', [
                'event' => 'webhook.processing_error',
                'webhook_type' => 'subscription',
                'order_id' => $payload['order_id'],
                'subscription_id' => $payload['subscription_id'] ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            // Return 200 to prevent retries, we log the error for investigation
            return response()->json(['status' => 'ok', 'message' => 'Event received'], 200);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Process subscription webhook payload.
     *
     * Routes the webhook to the appropriate handler based on the event type.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    private function processSubscriptionWebhook(array $payload): void
    {
        $subscriptionId = $payload['subscription_id'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $orderId = $payload['order_id'] ?? '';

        Log::info('Routing subscription webhook', [
            'order_id' => $orderId,
            'subscription_id' => $subscriptionId,
            'transaction_status' => $transactionStatus,
        ]);

        // Determine event type based on payload structure
        // Payment notification: has transaction_status but may not have subscription_id initially
        // Recurring notification: has subscription_id and transaction_status
        // Pay account notification: has subscription_id and account-related fields

        if ($subscriptionId !== null && $transactionStatus !== null) {
            // This is a recurring notification (subsequent payments)
            $this->handleRecurringNotification($payload);
        } elseif ($transactionStatus !== null) {
            // This is a payment notification (first payment)
            // Check if order_id contains subscription pattern
            if (str_contains($orderId, 'sub_')) {
                $this->handlePaymentNotification($payload);
            } else {
                // Not a subscription payment, log and skip
                Log::info('Non-subscription payment notification received', [
                    'order_id' => $orderId,
                ]);
            }
        } elseif ($subscriptionId !== null) {
            // This is a pay account notification (subscription status change)
            $this->handlePayAccountNotification($payload);
        } else {
            Log::warning('Unable to determine subscription webhook event type', [
                'order_id' => $orderId,
                'payload_keys' => array_keys($payload),
            ]);
        }
    }

    /**
     * Handle payment notification (first payment).
     *
     * Processes the initial subscription payment notification from Midtrans.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    private function handlePaymentNotification(array $payload): void
    {
        $orderId = $payload['order_id'] ?? '';
        $transactionStatus = $payload['transaction_status'] ?? '';
        $fraudStatus = $payload['fraud_status'] ?? 'accept';

        Log::info('Processing payment notification', [
            'event' => 'webhook.payment_notification',
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'fraud_status' => $fraudStatus,
            'payment_type' => $payload['payment_type'] ?? null,
            'gross_amount' => $payload['gross_amount'] ?? null,
        ]);

        // Check idempotency
        if ($this->isWebhookProcessed($orderId, 'payment')) {
            Log::info('Payment notification already processed', ['order_id' => $orderId]);
            return;
        }

        // Only process if fraud status is accept
        if ($fraudStatus !== 'accept') {
            Log::warning('Payment flagged for fraud', [
                'order_id' => $orderId,
                'fraud_status' => $fraudStatus,
            ]);
            $this->markWebhookProcessed($orderId, 'payment');
            return;
        }

        // Process based on transaction status
        switch ($transactionStatus) {
            case 'capture':
            case 'settlement':
                $this->processSubscriptionPaymentSuccess($payload);
                break;

            case 'pending':
                Log::info('Subscription payment pending', ['order_id' => $orderId]);
                break;

            case 'deny':
            case 'cancel':
            case 'expire':
                $this->processSubscriptionPaymentFailed($payload);
                break;

            default:
                Log::warning('Unknown payment transaction status', [
                    'order_id' => $orderId,
                    'status' => $transactionStatus,
                ]);
        }

        // Mark as processed
        $this->markWebhookProcessed($orderId, 'payment');
    }

    /**
     * Process successful subscription payment.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    private function processSubscriptionPaymentSuccess(array $payload): void
    {
        $orderId = $payload['order_id'] ?? '';
        $transactionId = $payload['transaction_id'] ?? null;

        Log::info('Processing subscription payment success', [
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
        ]);

        // Delegate to SubscriptionService
        app(SubscriptionService::class)->processMidtransWebhook($payload);

        Log::info('Subscription payment success processed', [
            'order_id' => $orderId,
        ]);
    }

    /**
     * Process failed subscription payment.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    private function processSubscriptionPaymentFailed(array $payload): void
    {
        $orderId = $payload['order_id'] ?? '';
        $transactionStatus = $payload['transaction_status'] ?? '';

        Log::warning('Processing subscription payment failure', [
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
        ]);

        // Delegate to SubscriptionService
        app(SubscriptionService::class)->processMidtransWebhook($payload);

        Log::info('Subscription payment failure processed', [
            'order_id' => $orderId,
        ]);
    }

    /**
     * Handle recurring notification (subsequent payments).
     *
     * Processes recurring subscription payment notifications from Midtrans.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    private function handleRecurringNotification(array $payload): void
    {
        $subscriptionId = $payload['subscription_id'] ?? '';
        $orderId = $payload['order_id'] ?? '';
        $transactionStatus = $payload['transaction_status'] ?? '';

        Log::info('Processing recurring notification', [
            'event' => 'webhook.recurring_notification',
            'subscription_id' => $subscriptionId,
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'gross_amount' => $payload['gross_amount'] ?? null,
            'transaction_time' => $payload['transaction_time'] ?? null,
        ]);

        // Check idempotency
        if ($this->isWebhookProcessed($orderId, 'recurring')) {
            Log::info('Recurring notification already processed', [
                'subscription_id' => $subscriptionId,
                'order_id' => $orderId,
            ]);
            return;
        }

        // Process based on transaction status
        switch ($transactionStatus) {
            case 'capture':
            case 'settlement':
                $this->processRecurringPaymentSuccess($payload);
                break;

            case 'pending':
                Log::info('Recurring payment pending', [
                    'subscription_id' => $subscriptionId,
                    'order_id' => $orderId,
                ]);
                break;

            case 'deny':
            case 'cancel':
            case 'expire':
                $this->processRecurringPaymentFailed($payload);
                break;

            default:
                Log::warning('Unknown recurring transaction status', [
                    'subscription_id' => $subscriptionId,
                    'order_id' => $orderId,
                    'status' => $transactionStatus,
                ]);
        }

        // Mark as processed
        $this->markWebhookProcessed($orderId, 'recurring');
    }

    /**
     * Process successful recurring payment.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    private function processRecurringPaymentSuccess(array $payload): void
    {
        $subscriptionId = $payload['subscription_id'] ?? '';
        $orderId = $payload['order_id'] ?? '';
        $transactionId = $payload['transaction_id'] ?? null;

        Log::info('Processing recurring payment success', [
            'subscription_id' => $subscriptionId,
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
        ]);

        // Update subscription period and status
        app(SubscriptionService::class)->processMidtransWebhook($payload);

        Log::info('Recurring payment success processed', [
            'subscription_id' => $subscriptionId,
            'order_id' => $orderId,
        ]);
    }

    /**
     * Process failed recurring payment.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    private function processRecurringPaymentFailed(array $payload): void
    {
        $subscriptionId = $payload['subscription_id'] ?? '';
        $orderId = $payload['order_id'] ?? '';
        $transactionStatus = $payload['transaction_status'] ?? '';

        Log::warning('Processing recurring payment failure', [
            'subscription_id' => $subscriptionId,
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
        ]);

        // Update subscription status
        app(SubscriptionService::class)->processMidtransWebhook($payload);

        Log::info('Recurring payment failure processed', [
            'subscription_id' => $subscriptionId,
            'order_id' => $orderId,
        ]);
    }

    /**
     * Handle pay account notification (subscription status changes).
     *
     * Processes subscription status change notifications from Midtrans.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    private function handlePayAccountNotification(array $payload): void
    {
        $subscriptionId = $payload['subscription_id'] ?? '';
        $accountStatus = $payload['account_status'] ?? null;
        $orderId = $payload['order_id'] ?? '';

        Log::info('Processing pay account notification', [
            'event' => 'webhook.pay_account_notification',
            'subscription_id' => $subscriptionId,
            'account_status' => $accountStatus,
            'order_id' => $orderId,
            'status_code' => $payload['status_code'] ?? null,
        ]);

        // Check idempotency
        if ($this->isWebhookProcessed($orderId, 'pay_account')) {
            Log::info('Pay account notification already processed', [
                'subscription_id' => $subscriptionId,
                'order_id' => $orderId,
            ]);
            return;
        }

        // Process based on account status
        if ($accountStatus !== null) {
            switch ($accountStatus) {
                case 'enabled':
                case 'active':
                    $this->processSubscriptionEnabled($payload);
                    break;

                case 'disabled':
                case 'inactive':
                    $this->processSubscriptionDisabled($payload);
                    break;

                default:
                    Log::warning('Unknown account status', [
                        'subscription_id' => $subscriptionId,
                        'account_status' => $accountStatus,
                    ]);
            }
        } else {
            // If no account_status, delegate to SubscriptionService for general processing
            app(SubscriptionService::class)->processMidtransWebhook($payload);
        }

        // Mark as processed
        $this->markWebhookProcessed($orderId, 'pay_account');
    }

    /**
     * Process subscription enabled notification.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    private function processSubscriptionEnabled(array $payload): void
    {
        $subscriptionId = $payload['subscription_id'] ?? '';

        Log::info('Processing subscription enabled', [
            'subscription_id' => $subscriptionId,
        ]);

        // Update subscription status to active
        $payload['status'] = 'active';
        app(SubscriptionService::class)->processMidtransWebhook($payload);

        Log::info('Subscription enabled processed', [
            'subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * Process subscription disabled notification.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    private function processSubscriptionDisabled(array $payload): void
    {
        $subscriptionId = $payload['subscription_id'] ?? '';

        Log::info('Processing subscription disabled', [
            'subscription_id' => $subscriptionId,
        ]);

        // Update subscription status to cancelled
        $payload['status'] = 'cancelled';
        app(SubscriptionService::class)->processMidtransWebhook($payload);

        Log::info('Subscription disabled processed', [
            'subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * Check if a webhook has already been processed (idempotency check).
     *
     * @param string $orderId The order ID
     * @param string $eventType The event type (payment, recurring, pay_account)
     * @return bool True if already processed
     */
    private function isWebhookProcessed(string $orderId, string $eventType): bool
    {
        $cacheKey = "midtrans_webhook_processed:{$eventType}:{$orderId}";
        return Cache::has($cacheKey);
    }

    /**
     * Mark a webhook as processed (idempotency tracking).
     *
     * @param string $orderId The order ID
     * @param string $eventType The event type (payment, recurring, pay_account)
     * @return void
     */
    private function markWebhookProcessed(string $orderId, string $eventType): void
    {
        $cacheKey = "midtrans_webhook_processed:{$eventType}:{$orderId}";
        // Store for 7 days to prevent duplicate processing
        Cache::put($cacheKey, true, now()->addDays(7));

        Log::debug('Webhook marked as processed', [
            'order_id' => $orderId,
            'event_type' => $eventType,
            'cache_key' => $cacheKey,
        ]);
    }
}
