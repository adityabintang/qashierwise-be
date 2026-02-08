<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for handling Resend webhook events.
 *
 * Receives and processes email event notifications from Resend
 * including delivered, bounced, complained, opened, and clicked events.
 */
class ResendWebhookController extends Controller
{
    /**
     * Handle incoming Resend webhook notification.
     */
    public function handleNotification(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Resend webhook received', [
            'event' => 'webhook.received',
            'webhook_type' => 'resend',
            'event_type' => $payload['type'] ?? null,
            'email_id' => $payload['data']['email_id'] ?? null,
            'ip_address' => $request->ip(),
        ]);

        // Validate signature
        if (! $this->validateSignature($request)) {
            Log::warning('Resend webhook invalid signature', [
                'event' => 'webhook.validation_failed',
                'reason' => 'invalid_signature',
                'email_id' => $payload['data']['email_id'] ?? null,
            ]);

            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        // Validate required fields
        if (! isset($payload['type']) || ! isset($payload['data'])) {
            Log::warning('Resend webhook missing required fields', [
                'event' => 'webhook.validation_failed',
                'reason' => 'missing_required_fields',
                'payload_keys' => array_keys($payload),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
        }

        // Process the webhook event
        $this->processWebhookEvent($payload);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Validate Resend webhook signature using svix-signature header.
     */
    private function validateSignature(Request $request): bool
    {
        $webhookSecret = config('services.resend.webhook_secret');

        if (empty($webhookSecret)) {
            Log::warning('Resend webhook secret not configured');

            return true; // Allow webhook if secret not configured (for development)
        }

        $signature = $request->header('svix-signature');
        if (! $signature) {
            return false;
        }

        $timestamp = $request->header('svix-timestamp');
        $payload = $request->getContent();

        // Parse signature header (format: v1,sig1 v1,sig2)
        $signatures = [];
        foreach (explode(' ', $signature) as $sig) {
            [$version, $hash] = explode(',', $sig);
            if ($version === 'v1') {
                $signatures[] = $hash;
            }
        }

        if (empty($signatures)) {
            return false;
        }

        // Compute expected signature
        $signedPayload = "{$timestamp}.{$payload}";
        $secret = base64_decode(explode('_', $webhookSecret)[1] ?? $webhookSecret);
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        // Compare with any of the provided signatures
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process different types of Resend webhook events.
     */
    private function processWebhookEvent(array $payload): void
    {
        $eventType = $payload['type'];
        $data = $payload['data'];

        Log::info('Processing Resend webhook event', [
            'event_type' => $eventType,
            'email_id' => $data['email_id'] ?? null,
            'to' => $data['to'] ?? null,
            'subject' => $data['subject'] ?? null,
        ]);

        switch ($eventType) {
            case 'email.sent':
                $this->handleEmailSent($data);
                break;

            case 'email.delivered':
                $this->handleEmailDelivered($data);
                break;

            case 'email.delivery_delayed':
                $this->handleEmailDelayed($data);
                break;

            case 'email.bounced':
                $this->handleEmailBounced($data);
                break;

            case 'email.complained':
                $this->handleEmailComplained($data);
                break;

            case 'email.opened':
                $this->handleEmailOpened($data);
                break;

            case 'email.clicked':
                $this->handleEmailClicked($data);
                break;

            default:
                Log::info('Unhandled Resend webhook event type', [
                    'event_type' => $eventType,
                ]);
        }
    }

    /**
     * Handle email sent event.
     */
    private function handleEmailSent(array $data): void
    {
        Log::info('Email sent', [
            'email_id' => $data['email_id'] ?? null,
            'to' => $data['to'] ?? null,
        ]);

        // Add your logic here
        // Example: Update email tracking record
    }

    /**
     * Handle email delivered event.
     */
    private function handleEmailDelivered(array $data): void
    {
        Log::info('Email delivered', [
            'email_id' => $data['email_id'] ?? null,
            'to' => $data['to'] ?? null,
        ]);

        // Add your logic here
        // Example: Mark email as delivered in database
    }

    /**
     * Handle email delayed event.
     */
    private function handleEmailDelayed(array $data): void
    {
        Log::warning('Email delivery delayed', [
            'email_id' => $data['email_id'] ?? null,
            'to' => $data['to'] ?? null,
        ]);

        // Add your logic here
    }

    /**
     * Handle email bounced event.
     */
    private function handleEmailBounced(array $data): void
    {
        Log::error('Email bounced', [
            'email_id' => $data['email_id'] ?? null,
            'to' => $data['to'] ?? null,
            'bounce_type' => $data['bounce_type'] ?? null,
        ]);

        // Add your logic here
        // Example: Mark email as invalid, disable future emails to this address
    }

    /**
     * Handle email complained (spam report) event.
     */
    private function handleEmailComplained(array $data): void
    {
        Log::warning('Email marked as spam', [
            'email_id' => $data['email_id'] ?? null,
            'to' => $data['to'] ?? null,
        ]);

        // Add your logic here
        // Example: Unsubscribe user from mailing list
    }

    /**
     * Handle email opened event.
     */
    private function handleEmailOpened(array $data): void
    {
        Log::info('Email opened', [
            'email_id' => $data['email_id'] ?? null,
            'to' => $data['to'] ?? null,
        ]);

        // Add your logic here
        // Example: Track email open rate
    }

    /**
     * Handle email clicked event.
     */
    private function handleEmailClicked(array $data): void
    {
        Log::info('Email link clicked', [
            'email_id' => $data['email_id'] ?? null,
            'to' => $data['to'] ?? null,
            'link' => $data['link'] ?? null,
        ]);

        // Add your logic here
        // Example: Track click-through rate
    }
}
