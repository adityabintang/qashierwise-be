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
 * Receives and processes QR code payment and payout status updates from Xendit.
 * Uses platform-level webhook token verification (XenPlatform).
 */
class XenditWebhookController extends Controller
{
    public function __construct(
        private QrisService $qrisService,
        private WithdrawalService $withdrawalService,
    ) {}

    /**
     * Handle incoming Xendit QRIS webhook notification.
     */
    public function handleNotification(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('x-callback-token', '');

        // API version 2022-07-31 uses 'reference_id' instead of 'external_id'
        $referenceId = $payload['reference_id'] ?? $payload['external_id'] ?? null;

        Log::info('Xendit webhook received', [
            'reference_id' => $referenceId,
            'status' => $payload['status'] ?? null,
            'has_signature' => ! empty($signature),
            'payload_keys' => array_keys($payload),
        ]);

        if (! $referenceId) {
            Log::warning('Xendit webhook missing reference_id/external_id', [
                'payload_keys' => array_keys($payload),
                'payload' => $payload,
            ]);

            // Return 200 OK to prevent Xendit from retrying
            // This webhook might be for a different event type (e.g., payment_requests)
            return response()->json([
                'status' => 'ignored',
                'message' => 'Webhook ignored: missing reference_id or external_id',
            ], 200);
        }

        try {
            $this->qrisService->handleWebhook($payload, $signature);

            Log::info('Xendit webhook processed successfully', [
                'reference_id' => $referenceId,
            ]);

            return response()->json(['status' => 'ok'], 200);

        } catch (InvalidWebhookException $e) {
            Log::warning('Xendit webhook signature verification failed', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            $errorResponse = ErrorResponse::unauthorized('Invalid webhook signature');

            return response()->json($errorResponse->toArray(), $errorResponse->statusCode);

        } catch (\Exception $e) {
            Log::error('Xendit webhook processing error', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            // Return 200 to prevent Xendit from retrying
            return response()->json(['status' => 'ok'], 200);
        }
    }

    /**
     * Handle incoming Xendit payout webhook notification.
     */
    public function handlePayoutNotification(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('x-callback-token', '');

        // Payouts API v2 wraps in { event, data: {...} }
        $payoutData = $payload['data'] ?? $payload;

        Log::info('Xendit payout webhook received', [
            'event' => $payload['event'] ?? null,
            'payout_id' => $payoutData['id'] ?? null,
            'reference_id' => $payoutData['reference_id'] ?? null,
            'status' => $payoutData['status'] ?? null,
        ]);

        // Verify webhook signature
        $xenPlatformService = app(\App\Services\XenPlatformService::class);
        if (! $xenPlatformService->verifyWebhookSignature($signature)) {
            Log::warning('Xendit payout webhook signature verification failed');

            $errorResponse = ErrorResponse::unauthorized('Invalid webhook signature');

            return response()->json($errorResponse->toArray(), $errorResponse->statusCode);
        }

        try {
            $this->withdrawalService->handlePayoutWebhook($payload);

            Log::info('Xendit payout webhook processed successfully', [
                'payout_id' => $payload['id'] ?? null,
            ]);

            return response()->json(['status' => 'ok'], 200);

        } catch (\Exception $e) {
            Log::error('Xendit payout webhook processing error', [
                'payout_id' => $payload['id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            // Return 200 to prevent Xendit from retrying
            return response()->json(['status' => 'ok'], 200);
        }
    }
}
