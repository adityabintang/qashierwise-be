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
 * Controller for handling Doku SNAP API webhook notifications.
 * 
 * Receives and processes payment status updates from Doku
 * including successful payments, expirations, and cancellations.
 */
class DokuWebhookController extends Controller
{
    private EncryptionService $encryptionService;

    public function __construct(EncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    /**
     * Handle incoming Doku webhook notification.
     */
    public function handleNotification(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Doku webhook received', [
            'transaction_id' => $payload['originalReferenceNo'] ?? null,
            'status' => $payload['transactionStatus'] ?? null,
        ]);

        // Validate required fields
        $referenceNo = $payload['originalReferenceNo'] ?? $payload['referenceNo'] ?? null;
        $transactionStatus = $payload['transactionStatus'] ?? $payload['responseCode'] ?? null;

        if (!$referenceNo || !$transactionStatus) {
            Log::warning('Doku webhook missing required fields', $payload);
            return response()->json(['responseCode' => '4000000', 'responseMessage' => 'Missing required fields'], 400);
        }

        // Find the transaction
        $transaction = QrisTransaction::where('order_id', $referenceNo)->first();

        if (!$transaction) {
            Log::warning('Doku webhook transaction not found', ['reference_no' => $referenceNo]);
            return response()->json(['responseCode' => '2000000', 'responseMessage' => 'Success'], 200);
        }

        // Validate signature using merchant's credentials
        if (!$this->validateSignature($request, $transaction)) {
            Log::warning('Doku webhook invalid signature', ['reference_no' => $referenceNo]);
            return response()->json(['responseCode' => '4010000', 'responseMessage' => 'Invalid signature'], 401);
        }

        // Process the notification
        try {
            $this->processPaymentNotification($transaction, $payload, $transactionStatus);
        } catch (\Exception $e) {
            Log::error('Doku webhook processing error', [
                'reference_no' => $referenceNo,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['responseCode' => '2000000', 'responseMessage' => 'Success'], 200);
    }

    /**
     * Validate Doku SNAP API signature.
     */
    private function validateSignature(Request $request, QrisTransaction $transaction): bool
    {
        $signature = $request->header('X-SIGNATURE');
        if (!$signature) {
            return false;
        }

        // Get merchant's credentials
        $credential = PaymentProviderCredential::where('user_id', $transaction->subMerchant->user_id)
            ->where('provider', 'doku')
            ->where('is_active', true)
            ->first();

        if (!$credential) {
            Log::warning('Doku credentials not found for transaction', ['order_id' => $transaction->order_id]);
            return true; // Skip validation if no credentials
        }

        try {
            $decrypted = $this->encryptionService->decryptCredentials(
                $credential->encrypted_credentials,
                $credential->user_id
            );
            $secretKey = $decrypted['secret_key'] ?? '';

            // Build signature string: HTTP_METHOD + ":" + RELATIVE_URL + ":" + ACCESS_TOKEN + ":" + SHA256(REQUEST_BODY) + ":" + TIMESTAMP
            $timestamp = $request->header('X-TIMESTAMP');
            $requestBody = $request->getContent();
            $bodyHash = hash('sha256', $requestBody);
            
            $stringToSign = "POST:/api/webhooks/doku::{$bodyHash}:{$timestamp}";
            $expectedSignature = base64_encode(hash_hmac('sha512', $stringToSign, $secretKey, true));

            return hash_equals($expectedSignature, $signature);
        } catch (\Exception $e) {
            Log::error('Doku signature validation error', ['error' => $e->getMessage()]);
            return true; // Skip validation on error
        }
    }

    /**
     * Process payment notification based on transaction status.
     */
    private function processPaymentNotification(QrisTransaction $transaction, array $payload, string $status): void
    {
        Log::info('Processing Doku notification', [
            'order_id' => $transaction->order_id,
            'status' => $status,
            'current_status' => $transaction->status,
        ]);

        if ($transaction->isSettled() || $transaction->isCancelledOrExpired()) {
            Log::info('Transaction already in final state', ['order_id' => $transaction->order_id]);
            return;
        }

        // Doku status codes: 00 = success, others = failed/pending
        if ($status === '00' || strtoupper($status) === 'SUCCESS') {
            $this->handleSettlement($transaction, $payload);
        } elseif (in_array(strtoupper($status), ['EXPIRED', 'TIMEOUT'])) {
            $this->handleExpiration($transaction);
        } elseif (in_array(strtoupper($status), ['CANCELLED', 'FAILED', 'DENIED'])) {
            $this->handleCancellation($transaction);
        }
    }

    private function handleSettlement(QrisTransaction $transaction, array $payload): void
    {
        $providerTransactionId = $payload['transactionId'] ?? $payload['acquirerTransactionId'] ?? null;
        
        $transaction->markAsSettled($providerTransactionId);
        $transaction->save();

        ProcessQrisPayment::dispatch($transaction, $payload)->onQueue('payments');

        Log::info('Doku settlement processed', ['order_id' => $transaction->order_id]);
    }

    private function handleExpiration(QrisTransaction $transaction): void
    {
        $transaction->markAsExpired();
        $transaction->save();

        // Cascade status update to linked Payment
        $this->updateLinkedPaymentStatus($transaction, 'expired');

        Log::info('Doku transaction expired', ['order_id' => $transaction->order_id]);
    }

    private function handleCancellation(QrisTransaction $transaction): void
    {
        $transaction->markAsCancelled();
        $transaction->save();

        // Cascade status update to linked Payment
        $this->updateLinkedPaymentStatus($transaction, 'failed');

        Log::info('Doku transaction cancelled', ['order_id' => $transaction->order_id]);
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
