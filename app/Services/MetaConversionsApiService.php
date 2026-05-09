<?php

namespace App\Services;

use App\Models\CapiEvent;
use App\Models\MetaPixelSetting;
use App\Models\Order;
use App\Models\WhatsAppContact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaConversionsApiService
{
    /**
     * Send a CAPI event to Meta Graph API.
     *
     * @param  array  $userData  Raw user data: ['phone' => '628xx', 'fbc' => 'ctwa_clid', 'email' => '...']
     * @param  array  $customData  Event-specific data: ['value' => 100000, 'currency' => 'IDR']
     * @param  string|null  $eventId  Deduplication ID (idempotency key)
     * @return array ['success' => bool, 'response' => array, 'capi_event_id' => int|null]
     */
    public function sendEvent(
        int $userId,
        string $eventName,
        array $userData,
        array $customData = [],
        ?string $eventId = null,
        string $actionSource = 'other',
        string $messagingChannel = 'whatsapp',
        ?int $contactId = null
    ): array {
        $settings = $this->getPixelSettings($userId);

        if (! $settings || ! $settings->is_active) {
            Log::info('CAPI: No active pixel settings for user, skipping event', [
                'user_id' => $userId,
                'event_name' => $eventName,
            ]);

            return ['success' => false, 'response' => [], 'capi_event_id' => null];
        }

        $eventId = $eventId ?? (string) Str::uuid();
        $eventTime = time();
        $ctwaClid = $userData['fbc'] ?? null;
        $eventSource = $ctwaClid ? 'ctwa' : 'organic';

        $builtUserData = $this->buildUserData($userData, $eventTime);

        $eventPayload = [
            'event_name' => $eventName,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'action_source' => $actionSource,
            'messaging_channel' => $messagingChannel,
            'user_data' => $builtUserData,
        ];

        if (! empty($customData)) {
            $eventPayload['custom_data'] = $customData;
        }

        $body = ['data' => [$eventPayload]];

        if ($settings->test_event_code) {
            $body['test_event_code'] = $settings->test_event_code;
        }

        // Log the pending event before sending
        $capiEvent = CapiEvent::create([
            'user_id' => $userId,
            'event_name' => $eventName,
            'event_id' => $eventId,
            'event_source' => $eventSource,
            'ctwa_clid' => $ctwaClid,
            'contact_id' => $contactId,
            'payload' => $body,
            'status' => 'pending',
        ]);

        try {
            $apiVersion = config('services.meta_capi.api_version', 'v22.0');
            $graphApiUrl = config('services.meta_capi.graph_api_url', 'https://graph.facebook.com');
            $url = "{$graphApiUrl}/{$apiVersion}/{$settings->pixel_id}/events";

            $response = Http::withToken($settings->access_token)
                ->timeout(10)
                ->post($url, $body);

            $responseData = $response->json() ?? [];

            if ($response->successful()) {
                $capiEvent->update([
                    'status' => 'sent',
                    'meta_response' => $responseData,
                    'sent_at' => now(),
                ]);

                Log::info('CAPI: Event sent successfully', [
                    'user_id' => $userId,
                    'event_name' => $eventName,
                    'event_id' => $eventId,
                    'events_received' => $responseData['events_received'] ?? null,
                ]);

                return ['success' => true, 'response' => $responseData, 'capi_event_id' => $capiEvent->id];
            }

            $capiEvent->update([
                'status' => 'failed',
                'meta_response' => $responseData,
            ]);

            Log::warning('CAPI: Event failed', [
                'user_id' => $userId,
                'event_name' => $eventName,
                'status' => $response->status(),
                'response' => $responseData,
            ]);

            return ['success' => false, 'response' => $responseData, 'capi_event_id' => $capiEvent->id];

        } catch (\Exception $e) {
            $capiEvent->update([
                'status' => 'failed',
                'meta_response' => ['error' => $e->getMessage()],
            ]);

            Log::error('CAPI: Exception while sending event', [
                'user_id' => $userId,
                'event_name' => $eventName,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'response' => ['error' => $e->getMessage()], 'capi_event_id' => $capiEvent->id];
        }
    }

    /**
     * Fire a Lead event when a new CTWA contact sends their first message.
     */
    public function sendLeadEvent(WhatsAppContact $contact): void
    {
        $ctwaClid = $contact->ctwa_clid;

        $this->sendEvent(
            userId: $contact->user_id,
            eventName: 'Lead',
            userData: [
                'phone' => $contact->wa_id,
                'fbc' => $ctwaClid,
            ],
            customData: [
                'lead_event_source' => $ctwaClid ? 'ctwa' : 'organic',
            ],
            eventId: 'lead_contact_'.$contact->id,
            contactId: $contact->id
        );
    }

    /**
     * Fire a Purchase event when an order is paid.
     * Looks up the contact to include CTWA attribution if within the window.
     */
    public function sendPurchaseEvent(Order $order, WhatsAppContact $contact): void
    {
        $ctwaClid = null;

        // Only use ctwa_clid if within the attribution window
        if (
            $contact->ctwa_clid &&
            $contact->attribution_expires_at &&
            $contact->attribution_expires_at->isFuture()
        ) {
            $ctwaClid = $contact->ctwa_clid;
        }

        $this->sendEvent(
            userId: $order->user_id ?? $contact->user_id,
            eventName: 'Purchase',
            userData: [
                'phone' => $contact->wa_id,
                'fbc' => $ctwaClid,
            ],
            customData: [
                'value' => (float) $order->total,
                'currency' => 'IDR',
                'order_id' => $order->id,
                'content_name' => 'Order #'.$order->id,
            ],
            eventId: 'purchase_order_'.$order->id,
            contactId: $contact->id
        );
    }

    private function getPixelSettings(int $userId): ?MetaPixelSetting
    {
        return MetaPixelSetting::where('user_id', $userId)->first();
    }

    /**
     * Hash a string value using SHA256 (required by Meta for PII fields).
     */
    private function hashData(string $value): string
    {
        return hash('sha256', strtolower(trim($value)));
    }

    /**
     * Build the fbc cookie value from a ctwa_clid.
     * Format: fb.{version}.{creation_time}.{ctwa_clid}
     */
    private function buildFbc(string $ctwaClid): string
    {
        return 'fb.1.'.time().'.'.$ctwaClid;
    }

    /**
     * Build and hash the user_data object for the CAPI payload.
     * All PII must be SHA256-hashed before sending to Meta.
     */
    private function buildUserData(array $raw, int $eventTime): array
    {
        $userData = [];

        if (! empty($raw['phone'])) {
            // Normalize to E.164 format (digits only with country code)
            $phone = preg_replace('/[^0-9]/', '', $raw['phone']);
            $userData['ph'] = [$this->hashData($phone)];
        }

        if (! empty($raw['email'])) {
            $userData['em'] = [$this->hashData($raw['email'])];
        }

        if (! empty($raw['fbc'])) {
            // Build fbc from ctwa_clid: fb.1.{event_time}.{ctwa_clid}
            $userData['fbc'] = 'fb.1.'.$eventTime.'.'.$raw['fbc'];
        }

        if (! empty($raw['first_name'])) {
            $userData['fn'] = [$this->hashData($raw['first_name'])];
        }

        if (! empty($raw['last_name'])) {
            $userData['ln'] = [$this->hashData($raw['last_name'])];
        }

        return $userData;
    }
}
