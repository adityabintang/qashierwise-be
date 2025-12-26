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
 * Controller for handling Duitku webhook notifications.
 * 
 * Receives and processes payment status updates from Duitku
 * including successful payments, expirations, and cancellations.
 */
class DuitkuWebhookController extends Controller
{
    private EncryptionService $encryptionService;

    public function __construct(EncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    /**
     * Handle incoming Duitku webhook notification (callback).
     */
    public function handleNotification(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Duitku webhook received', [
            'merchant_order_id' => $payload['merchantOrderId'] ?? null,
            'result_code' => $payload['resultCode'] ?? null,
            'reference' => $payload['reference'] ?? null,
        ]);

        // Validate required fields
        $merchantOrderId = $payload['merchantOrderId'] ?? null;
        $resultCode = $payload['resultCode'] ?? null;
        $signature = $payload['signature'] ?? null;

        if (!$merchantOrderId || $resultCode === null) {
            Log::warning('Duitku webhook missing required fields', $payload);
            return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
        }

        // Find the transaction
        $transaction = QrisTransaction::where('order_id', $merchantOrderId)->first();

        if (!$transaction) {
            Log::warning('Duitku webhook transaction not found', ['merchant_order_id' => $merchantOrderId]);
            return response()->json(['status' => 'ok'], 200);
        }

        // Validate signature
        if (!$this->validateSignature($payload, $transaction, $signature)) {
            Log::warning('Duitku webhook invalid signature', ['merchant_order_id' => $merchantOrderId]);
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        // Process the notification
        try {
            $this->processPaymentNotification($transaction, $payload, $resultCode);
        } catch (\Exception $e) {
            Log::error('Duitku webhook processing error', [
                'merchant_order_id' => $merchantOrderId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Validate Duitku callback signature.
     * Signature = MD5(merchantCode + amount + merchantOrderId + apiKey)
     */
    private function validateSignature(array $payload, QrisTransaction $transaction, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        // Get merchant's credentials
        $credential = PaymentProviderCredential::where('user_id', $transaction->subMerchant->user_id)
            ->where('provider', 'duitku')
            ->where('is_active', true)
            ->first();

        if (!$credential) {
            Log::warning('Duitku credentials not found for transaction', ['order_id' => $transaction->order_id]);
            return true; // Skip validation if no credentials
        }

        try {
            $decrypted = $this->encryptionService->decryptCredentials(
                $credential->encrypted_credentials,
                $credential->user_id
            );
            
            $merchantCode = $decrypted['merchant_code'] ?? '';
            $apiKey = $decrypted['api_key'] ?? '';
            $amount = $payload['amount'] ?? $transaction->amount;
            $merchantOrderId = $payload['merchantOrderId'] ?? '';

            // Duitku signature format: MD5(merchantCode + amount + merchantOrderId + apiKey)
            $expectedSignature = md5($merchantCode . $amount . $merchantOrderId . $apiKey);

            return hash_equals($expectedSignature, $signature);
        } catch (\Exception $e) {
            Log::error('Duitku signature validation error', ['error' => $e->getMessage()]);
            return true; // Skip validation on error
        }
    }

    /**
     * Process payment notification based on result code.
     * Duitku result codes:
     * - 00: Success
     * - 01: Pending
     * - 02: Cancelled/Failed
     */
    private function processPaymentNotification(QrisTransaction $transaction, array $payload, string $resultCode): void
    {
        Log::info('Processing Duitku notification', [
            'order_id' => $transaction->order_id,
            'result_code' => $resultCode,
            'current_status' => $transaction->status,
        ]);

        if ($transaction->isSettled() || $transaction->isCancelledOrExpired()) {
            Log::info('Transaction already in final state', ['order_id' => $transaction->order_id]);
            return;
        }

        switch ($resultCode) {
            case '00':
                // Payment successful
                $this->handleSettlement($transaction, $payload);
                break;

            case '01':
                // Payment pending - no action needed
                Log::info('Duitku payment pending', ['order_id' => $transaction->order_id]);
                break;

            case '02':
            default:
                // Payment failed/cancelled
                $this->handleCancellation($transaction);
                break;
        }
    }

    private function handleSettlement(QrisTransaction $transaction, array $payload): void
    {
        $providerTransactionId = $payload['reference'] ?? null;
        
        $transaction->markAsSettled($providerTransactionId);
        $transaction->save();

        ProcessQrisPayment::dispatch($transaction, $payload)->onQueue('payments');

        Log::info('Duitku settlement processed', ['order_id' => $transaction->order_id]);
    }

    private function handleCancellation(QrisTransaction $transaction): void
    {
        $transaction->markAsCancelled();
        $transaction->save();

        // Cascade status update to linked Payment
        $this->updateLinkedPaymentStatus($transaction, 'failed');

        Log::info('Duitku transaction cancelled/failed', ['order_id' => $transaction->order_id]);
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
