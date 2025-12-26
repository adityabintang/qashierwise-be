<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessQrisPayment;
use App\Models\Payment;
use App\Models\QrisTransaction;
use App\Models\PaymentProviderCredential;
use App\Services\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for handling Xendit webhook notifications.
 * 
 * Receives and processes QR code payment status updates from Xendit
 * including successful payments, expirations, and failures.
 */
class XenditWebhookController extends Controller
{
    private EncryptionService $encryptionService;

    public function __construct(EncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    /**
     * Handle incoming Xendit webhook notification.
     */
    public function handleNotification(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Xendit webhook received', [
            'event' => $payload['event'] ?? null,
            'external_id' => $payload['data']['reference_id'] ?? $payload['external_id'] ?? null,
            'status' => $payload['data']['status'] ?? $payload['status'] ?? null,
        ]);

        // Extract reference ID (order_id) from payload
        $referenceId = $payload['data']['reference_id'] ?? $payload['external_id'] ?? null;
        $status = $payload['data']['status'] ?? $payload['status'] ?? null;
        $event = $payload['event'] ?? null;

        if (!$referenceId) {
            Log::warning('Xendit webhook missing reference_id', $payload);
            return response()->json(['status' => 'error', 'message' => 'Missing reference_id'], 400);
        }

        // Find the transaction
        $transaction = QrisTransaction::where('order_id', $referenceId)->first();

        if (!$transaction) {
            Log::warning('Xendit webhook transaction not found', ['reference_id' => $referenceId]);
            return response()->json(['status' => 'ok'], 200);
        }

        // Validate callback token
        if (!$this->validateCallbackToken($request, $transaction)) {
            Log::warning('Xendit webhook invalid callback token', ['reference_id' => $referenceId]);
            return response()->json(['status' => 'error', 'message' => 'Invalid callback token'], 401);
        }

        // Process the notification
        try {
            $this->processPaymentNotification($transaction, $payload, $status, $event);
        } catch (\Exception $e) {
            Log::error('Xendit webhook processing error', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Validate Xendit callback token.
     */
    private function validateCallbackToken(Request $request, QrisTransaction $transaction): bool
    {
        $callbackToken = $request->header('x-callback-token');
        if (!$callbackToken) {
            return false;
        }

        // Get merchant's credentials
        $credential = PaymentProviderCredential::where('user_id', $transaction->subMerchant->user_id)
            ->where('provider', 'xendit')
            ->where('is_active', true)
            ->first();

        if (!$credential) {
            Log::warning('Xendit credentials not found for transaction', ['order_id' => $transaction->order_id]);
            return true; // Skip validation if no credentials
        }

        try {
            $decrypted = $this->encryptionService->decryptCredentials(
                $credential->encrypted_credentials,
                $credential->user_id
            );
            $expectedToken = $decrypted['callback_token'] ?? '';

            return hash_equals($expectedToken, $callbackToken);
        } catch (\Exception $e) {
            Log::error('Xendit callback token validation error', ['error' => $e->getMessage()]);
            return true; // Skip validation on error
        }
    }

    /**
     * Process payment notification based on status and event.
     */
    private function processPaymentNotification(QrisTransaction $transaction, array $payload, ?string $status, ?string $event): void
    {
        Log::info('Processing Xendit notification', [
            'order_id' => $transaction->order_id,
            'status' => $status,
            'event' => $event,
            'current_status' => $transaction->status,
        ]);

        if ($transaction->isSettled() || $transaction->isCancelledOrExpired()) {
            Log::info('Transaction already in final state', ['order_id' => $transaction->order_id]);
            return;
        }

        // Handle based on event type or status
        $normalizedStatus = strtoupper($status ?? '');
        $normalizedEvent = strtolower($event ?? '');

        // QR code payment completed
        if ($normalizedStatus === 'COMPLETED' || $normalizedStatus === 'SUCCEEDED' || 
            str_contains($normalizedEvent, 'completed') || str_contains($normalizedEvent, 'paid')) {
            $this->handleSettlement($transaction, $payload);
        } 
        // QR code expired
        elseif ($normalizedStatus === 'EXPIRED' || str_contains($normalizedEvent, 'expired')) {
            $this->handleExpiration($transaction);
        }
        // QR code failed/voided
        elseif (in_array($normalizedStatus, ['FAILED', 'VOIDED', 'CANCELLED'])) {
            $this->handleCancellation($transaction);
        }
    }

    private function handleSettlement(QrisTransaction $transaction, array $payload): void
    {
        $providerTransactionId = $payload['data']['id'] ?? $payload['id'] ?? null;
        
        $transaction->markAsSettled($providerTransactionId);
        $transaction->save();

        ProcessQrisPayment::dispatch($transaction, $payload)->onQueue('payments');

        Log::info('Xendit settlement processed', ['order_id' => $transaction->order_id]);
    }

    private function handleExpiration(QrisTransaction $transaction): void
    {
        $transaction->markAsExpired();
        $transaction->save();

        // Cascade status update to linked Payment
        $this->updateLinkedPaymentStatus($transaction, 'expired');

        Log::info('Xendit transaction expired', ['order_id' => $transaction->order_id]);
    }

    private function handleCancellation(QrisTransaction $transaction): void
    {
        $transaction->markAsCancelled();
        $transaction->save();

        // Cascade status update to linked Payment
        $this->updateLinkedPaymentStatus($transaction, 'failed');

        Log::info('Xendit transaction cancelled', ['order_id' => $transaction->order_id]);
    }

    /**
     * Update linked Payment record status when QRIS transaction status changes.
     *
     * @param QrisTransaction $transaction
     * @param string $status The status to set ('failed' or 'expired')
     */
    private function updateLinkedPaymentStatus(QrisTransaction $transaction, string $status): void
    {
        $payment = Payment::where('qris_transaction_id', $transaction->id)->first();

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
}
