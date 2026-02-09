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
 * Receives and processes QR code payment, Payment Requests V2, and payout status updates.
 * Uses platform-level webhook token verification (XenPlatform).
 *
 * Xendit webhook formats:
 * - QR Codes: { event: "qr.payment", data: { reference_id, status, type, currency, ... } }
 * - Payment Requests V2: { reference_id, status, payment_method, ... }
 * - Payouts v2/v3: { event: "payout.queued"|"payout.completed", data: { id, reference_id, status, ... } }
 */
class XenditWebhookController extends Controller
{
    public function __construct(
        private QrisService $qrisService,
        private WithdrawalService $withdrawalService,
    ) {}

    /**
     * Handle incoming Xendit webhook notification.
     * Routes QR codes and Payment Requests V2 webhooks to appropriate handlers.
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
            if (str_starts_with($eventType, 'qr.') || $eventType === 'qr.payment') {
                return $this->handleQrisWebhook($innerData, $signature);
            }

            // Unknown wrapped event type
            Log::warning('Xendit webhook: Unknown wrapped event type', [
                'event' => $eventType,
                'data_keys' => array_keys($innerData),
            ]);
            return response()->json(['status' => 'ok'], 200);
        }

        // Detect webhook type by payload structure (unwrapped/direct format)
        // Payment Requests V2 has: { reference_id, status, payment_method, ... }
        // QR Codes direct has: { reference_id, status, type: 'DYNAMIC', currency, ... }
        $hasPaymentMethod = isset($payload['payment_method']);
        $hasType = isset($payload['type']) && $payload['type'] === 'DYNAMIC';
        $hasCurrency = isset($payload['currency']);

        if ($hasPaymentMethod && !$hasType) {
            // Payment Requests V2 webhook
            return $this->handlePaymentRequestWebhook($payload, $signature);
        } elseif ($hasCurrency && $hasType) {
            // QR Codes webhook (direct/unwrapped format)
            return $this->handleQrisWebhook($payload, $signature);
        }

        // Unknown webhook type - log and return 200 to prevent retries
        Log::warning('Xendit webhook: Unknown webhook type', [
            'payload_keys' => array_keys($payload),
            'payload' => $payload,
            'has_event' => $hasEvent,
            'has_data' => $hasData,
            'has_payment_method' => $hasPaymentMethod,
            'has_type' => $hasType,
            'has_currency' => $hasCurrency,
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

        Log::info('Xendit QRIS webhook received', [
            'reference_id' => $referenceId,
            'status' => $payload['status'] ?? null,
            'type' => $payload['type'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'has_signature' => ! empty($signature),
        ]);

        if (! $referenceId) {
            Log::warning('Xendit QRIS webhook missing reference_id', [
                'payload' => $payload,
            ]);
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
                'error' => $e->getMessage(),
            ]);

            $errorResponse = ErrorResponse::unauthorized('Invalid webhook signature');

            return response()->json($errorResponse->toArray(), $errorResponse->statusCode);

        } catch (\Exception $e) {
            Log::error('Xendit QRIS webhook processing error', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
        $referenceId = $payload['reference_id'] ?? $payload['id'] ?? null;

        Log::info('Xendit Payment Request V2 webhook received', [
            'reference_id' => $referenceId,
            'status' => $payload['status'] ?? null,
            'payment_method' => $payload['payment_method'] ?? null,
            'amount' => $payload['amount'] ?? null,
        ]);

        // TODO: Implement Payment Request V2 handling if needed
        // For now, just acknowledge to prevent retries

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Handle incoming Xendit payout webhook notification.
     * Payouts API v2/v3 sends: { event: "payout.queued"|"payout.completed", data: { id, reference_id, status, ... } }
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
            'has_signature' => ! empty($signature),
        ]);

        // Verify webhook signature
        $xenPlatformService = app(\App\Services\XenPlatformService::class);
        if (! $xenPlatformService->verifyWebhookSignature($signature)) {
            Log::warning('Xendit payout webhook signature verification failed', [
                'signature_provided' => ! empty($signature),
                'signature_length' => strlen($signature),
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
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 to prevent Xendit from retrying
            return response()->json(['status' => 'ok'], 200);
        }
    }
}
