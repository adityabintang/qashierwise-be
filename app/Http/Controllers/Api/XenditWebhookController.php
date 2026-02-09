<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ErrorResponse;
use App\Exceptions\InvalidWebhookException;
use App\Http\Controllers\Controller;
use App\Services\QrisService;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for handling Xendit webhook notifications.
 *
 * Receives and processes QR code payment, Payment Requests V2, Disbursement, and Payout status updates.
 * Uses platform-level webhook token verification (XenPlatform).
 *
 * Xendit webhook formats:
 * - QR Codes: { event: "qr.payment", data: { reference_id, status, type, currency, ... } }
 * - Payment Requests V2: { event: "payment.succeeded", data: { reference_id, status, payment_method, ... } }
 * - Disbursement (legacy): { id, external_id, amount, bank_code, status, ... }
 * - Payouts v2/v3: { event: "payout.succeeded"|"v3_payout.succeeded"|"payout-link.succeeded", data: { id, reference_id, status, ... } }
 * - Account events: { event: "account.created"|"account.updated", data: { id, type, status, ... } }
 */
class XenditWebhookController extends Controller
{
    public function __construct(
        private QrisService $qrisService,
        private WithdrawalService $withdrawalService,
    ) {}

    /**
     * Handle incoming Xendit webhook notification.
     * Routes different event types to appropriate handlers.
     */
    public function handleNotification(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('x-callback-token', '');

        // Check if this is a wrapped webhook (has 'event' and 'data' keys)
        $hasEvent = isset($payload['event']);
        $hasData = isset($payload['data']) && is_array($payload['data']);

        if ($hasEvent && $hasData) {
            $eventType = $payload['event'];
            $innerData = $payload['data'];

            // Route based on event type
            return $this->handleWrappedWebhook($eventType, $innerData, $signature);
        }

        // Detect webhook type by payload structure (unwrapped/direct format)
        $hasPaymentMethod = isset($payload['payment_method']);
        $hasType = isset($payload['type']) && $payload['type'] === 'DYNAMIC';
        $hasCurrency = isset($payload['currency']);

        // Disbursement API (legacy) - has external_id, bank_code, disbursement_description
        $isDisbursement = isset($payload['external_id']) && isset($payload['bank_code']) && isset($payload['disbursement_description']);

        if ($isDisbursement) {
            // Disbursement webhook (legacy API)
            return $this->handleDisbursementWebhook($payload, $signature);
        } elseif ($hasPaymentMethod && !$hasType) {
            // Payment Requests V2 webhook (unwrapped)
            return $this->handlePaymentRequestWebhook($payload, $signature);
        } elseif ($hasCurrency && $hasType) {
            // QR Codes webhook (direct/unwrapped format)
            return $this->handleQrisWebhook($payload, $signature);
        }

        // Unknown webhook type - log and return 200 to prevent retries
        Log::warning('Xendit webhook: Unknown webhook type', [
            'payload_keys' => array_keys($payload),
            'has_event' => $hasEvent,
            'has_data' => $hasData,
        ]);

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Handle wrapped webhook payloads with { event, data } structure.
     */
    protected function handleWrappedWebhook(string $eventType, array $data, string $signature): JsonResponse
    {
        Log::info('Xendit wrapped webhook received', [
            'event' => $eventType,
            'reference_id' => $data['reference_id'] ?? null,
        ]);

        // QR payment events
        if (str_starts_with($eventType, 'qr.') || $eventType === 'qr.payment') {
            return $this->handleQrisWebhook($data, $signature);
        }

        // Payment request events
        if (str_starts_with($eventType, 'payment.') || $eventType === 'payment.succeeded') {
            return $this->handlePaymentRequestWebhook($data, $signature);
        }

        // Account events - just log and acknowledge
        if (str_starts_with($eventType, 'account.')) {
            return $this->handleAccountWebhook($eventType, $data);
        }

        // Unknown wrapped event type
        Log::info('Xendit webhook: Unhandled event type (acknowledged)', [
            'event' => $eventType,
            'data_keys' => array_keys($data),
        ]);

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Handle QRIS (QR Codes) webhook notification.
     * Handles both wrapped { event, data } and direct formats.
     */
    protected function handleQrisWebhook(array $payload, string $signature): JsonResponse
    {
        $referenceId = $payload['reference_id'] ?? $payload['external_id'] ?? null;

        Log::info('Xendit QRIS webhook processing', [
            'reference_id' => $referenceId,
            'status' => $payload['status'] ?? null,
        ]);

        if (! $referenceId) {
            Log::warning('Xendit QRIS webhook missing reference_id');
            return response()->json(['status' => 'ok'], 200);
        }

        try {
            $this->qrisService->handleWebhook($payload, $signature);

            Log::info('Xendit QRIS webhook processed successfully', [
                'reference_id' => $referenceId,
            ]);

            return response()->json(['status' => 'ok'], 200);

        } catch (InvalidWebhookException $e) {
            Log::warning('Xendit QRIS webhook signature verification failed', [
                'reference_id' => $referenceId,
            ]);

            $errorResponse = ErrorResponse::unauthorized('Invalid webhook signature');

            return response()->json($errorResponse->toArray(), $errorResponse->statusCode);

        } catch (\Exception $e) {
            Log::error('Xendit QRIS webhook processing error', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'ok'], 200);
        }
    }

    /**
     * Handle Payment Requests V2 webhook notification.
     * Currently logs and acknowledges - can be extended for future payment request handling.
     */
    protected function handlePaymentRequestWebhook(array $payload, string $signature): JsonResponse
    {
        $referenceId = $payload['reference_id'] ?? $payload['payment_request_id'] ?? $payload['id'] ?? null;

        Log::info('Xendit Payment Request webhook received', [
            'reference_id' => $referenceId,
            'status' => $payload['status'] ?? null,
            'payment_method' => $payload['payment_method']['type'] ?? $payload['payment_method'] ?? null,
            'amount' => $payload['amount'] ?? null,
        ]);

        // TODO: Implement Payment Request V2 handling if needed
        // For now, just acknowledge to prevent retries

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Handle Disbursement webhook notification (legacy Disbursement API).
     * This is for the older Xendit Disbursement API (not Payouts v2/v3).
     */
    protected function handleDisbursementWebhook(array $payload, string $signature): JsonResponse
    {
        $externalId = $payload['external_id'] ?? null;
        $status = $payload['status'] ?? null;

        Log::info('Xendit Disbursement webhook received', [
            'external_id' => $externalId,
            'disbursement_id' => $payload['id'] ?? null,
            'status' => $status,
            'amount' => $payload['amount'] ?? null,
            'bank_code' => $payload['bank_code'] ?? null,
        ]);

        // Verify webhook signature
        $xenPlatformService = app(\App\Services\XenPlatformService::class);
        if (! $xenPlatformService->verifyWebhookSignature($signature)) {
            Log::warning('Xendit Disbursement webhook signature verification failed', [
                'external_id' => $externalId,
            ]);

            $errorResponse = ErrorResponse::unauthorized('Invalid webhook signature');

            return response()->json($errorResponse->toArray(), $errorResponse->statusCode);
        }

        try {
            // Convert disbursement payload to payout format for processing
            $payoutPayload = [
                'event' => 'disbursement.'.$status,
                'data' => [
                    'id' => $payload['id'] ?? null,
                    'reference_id' => $externalId,
                    'status' => $status,
                    'amount' => $payload['amount'] ?? null,
                    'bank_code' => $payload['bank_code'] ?? null,
                ],
            ];

            $this->withdrawalService->handlePayoutWebhook($payoutPayload);

            Log::info('Xendit Disbursement webhook processed successfully', [
                'external_id' => $externalId,
            ]);

            return response()->json(['status' => 'ok'], 200);

        } catch (\Exception $e) {
            Log::error('Xendit Disbursement webhook processing error', [
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'ok'], 200);
        }
    }

    /**
     * Handle Account webhook notification (account.created, account.updated, etc).
     * Just logs and acknowledges - for monitoring purposes.
     */
    protected function handleAccountWebhook(string $eventType, array $data): JsonResponse
    {
        Log::info('Xendit Account webhook received', [
            'event' => $eventType,
            'account_id' => $data['id'] ?? null,
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? null,
            'email' => $data['email'] ?? null,
        ]);

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Handle incoming Xendit payout webhook notification.
     * Payouts API v2/v3 sends: { event: "payout.succeeded"|"v3_payout.succeeded"|"payout-link.succeeded", data: { id, reference_id, status, ... } }
     */
    public function handlePayoutNotification(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('x-callback-token', '');

        // Payouts API wraps in { event, data: {...} }
        $payoutData = $payload['data'] ?? $payload;
        $eventType = $payload['event'] ?? null;

        Log::info('Xendit payout webhook received', [
            'event' => $eventType,
            'payout_id' => $payoutData['id'] ?? null,
            'reference_id' => $payoutData['reference_id'] ?? null,
            'status' => $payoutData['status'] ?? null,
        ]);

        // Verify webhook signature
        $xenPlatformService = app(\App\Services\XenPlatformService::class);
        if (! $xenPlatformService->verifyWebhookSignature($signature)) {
            Log::warning('Xendit payout webhook signature verification failed', [
                'event' => $eventType,
            ]);

            $errorResponse = ErrorResponse::unauthorized('Invalid webhook signature');

            return response()->json($errorResponse->toArray(), $errorResponse->statusCode);
        }

        try {
            $this->withdrawalService->handlePayoutWebhook($payload);

            Log::info('Xendit payout webhook processed successfully', [
                'payout_id' => $payoutData['id'] ?? null,
            ]);

            return response()->json(['status' => 'ok'], 200);

        } catch (\Exception $e) {
            Log::error('Xendit payout webhook processing error', [
                'payout_id' => $payoutData['id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            // Return 200 to prevent Xendit from retrying
            return response()->json(['status' => 'ok'], 200);
        }
    }
}
