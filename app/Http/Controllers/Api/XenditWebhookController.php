<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ErrorResponse;
use App\Exceptions\InvalidWebhookException;
use App\Exceptions\NoActiveProviderException;
use App\Http\Controllers\Controller;
use App\Services\QrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for handling Xendit webhook notifications.
 *
 * Receives and processes QR code payment status updates from Xendit
 * including successful payments, expirations, and failures.
 *
 * Webhook verification is handled by QrisService using the provider's
 * verifyWebhook implementation.
 */
class XenditWebhookController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private QrisService $qrisService
    ) {}

    /**
     * Handle incoming Xendit webhook notification.
     *
     * Xendit sends webhook notifications for QR code payment events:
     * - QR code payment completed
     * - QR code expired
     * - QR code status changes
     *
     * The webhook signature is verified using the x-callback-token header.
     *
     * Note: API version 2022-07-31 uses 'reference_id' instead of 'external_id'.
     * We support both for backward compatibility.
     *
     * @param  Request  $request  The webhook request
     * @return JsonResponse Response to Xendit
     */
    public function handleNotification(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Get webhook signature from header
        $signature = $request->header('x-callback-token', '');

        // API version 2022-07-31 uses 'reference_id' instead of 'external_id'
        $referenceId = $payload['reference_id'] ?? $payload['external_id'] ?? null;

        Log::info('Xendit webhook received', [
            'reference_id' => $referenceId,
            'status' => $payload['status'] ?? null,
            'has_signature' => ! empty($signature),
        ]);

        // Validate required fields - support both reference_id and external_id
        if (! $referenceId) {
            Log::warning('Xendit webhook missing reference_id/external_id', [
                'payload_keys' => array_keys($payload),
            ]);

            $errorResponse = ErrorResponse::validationError(
                'Missing required field: reference_id or external_id',
                ['required_fields' => ['reference_id']]
            );

            return response()->json($errorResponse->toArray(), $errorResponse->statusCode);
        }

        try {
            // Process webhook through QrisService
            // This will:
            // 1. Verify webhook signature
            // 2. Parse webhook payload
            // 3. Update transaction status
            $this->qrisService->handleWebhook('xendit', $payload, $signature);

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

        } catch (NoActiveProviderException $e) {
            Log::error('Xendit webhook provider not configured', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            // Return 200 to prevent Xendit from retrying
            // This is a configuration issue, not a webhook issue
            return response()->json(['status' => 'ok'], 200);

        } catch (\RuntimeException $e) {
            Log::error('Xendit webhook processing error', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            // Return 200 to prevent Xendit from retrying
            // We've logged the error for investigation
            return response()->json(['status' => 'ok'], 200);

        } catch (\Exception $e) {
            Log::error('Xendit webhook unexpected error', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 to prevent Xendit from retrying
            return response()->json(['status' => 'ok'], 200);
        }
    }
}
