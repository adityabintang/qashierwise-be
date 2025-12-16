<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PolarService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Controller for handling Polar.sh webhook events.
 * 
 * Receives and processes subscription lifecycle events from Polar.sh
 * including subscription creation, updates, and cancellations.
 */
class PolarWebhookController extends Controller
{
    public function __construct(
        private PolarService $polarService,
        private SubscriptionService $subscriptionService,
    ) {}

    /**
     * Handle incoming Polar.sh webhook events.
     * 
     * Validates the webhook signature and routes events to appropriate handlers.
     * 
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        // Get the raw payload for signature validation
        $payload = $request->getContent();
        $signature = $request->header('webhook-signature', '');

        // Validate webhook signature
        if (!$this->polarService->validateWebhookSignature($payload, $signature)) {
            Log::warning('Invalid Polar webhook signature', [
                'signature' => $signature,
            ]);

            return response('Invalid signature', 401);
        }

        // Parse the event data
        $event = json_decode($payload, true);

        if ($event === null) {
            Log::warning('Invalid Polar webhook payload: not valid JSON');
            return response('Invalid payload', 400);
        }

        $eventType = $event['type'] ?? null;

        if ($eventType === null) {
            Log::warning('Polar webhook missing event type');
            return response('Missing event type', 400);
        }

        Log::info('Received Polar webhook', [
            'type' => $eventType,
            'id' => $event['id'] ?? null,
        ]);

        // Process the webhook event
        try {
            $this->subscriptionService->processWebhookEvent($event);
        } catch (\Exception $e) {
            Log::error('Failed to process Polar webhook', [
                'type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            // Return 200 to prevent Polar from retrying
            // We log the error for investigation
            return response('Event received', 200);
        }

        return response('OK', 200);
    }
}
