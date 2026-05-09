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
     * Meta error codes and their human-readable diagnostics.
     * Reference: https://developers.facebook.com/docs/marketing-api/conversions-api
     */
    private const ERROR_CODES = [
        190 => 'Invalid OAuth access token — regenerate system user token with ads_management permission',
        100 => 'Invalid parameter — check required fields: event_name, event_time, action_source, user_data',
        2804001 => 'Missing user_data — include at least one customer information parameter (ph, em, etc.)',
        2804002 => 'Invalid action_source — valid: website, app, phone_call, chat, email, in_store, other',
        2804003 => 'Timestamp too old — event_time must be within 7 days of current time',
        2804004 => 'Invalid event_time — must be Unix seconds (not milliseconds)',
        368 => 'Temporarily blocked — rate limiting in effect; apply exponential backoff',
        803 => 'Permission denied — system user requires ads_management permission',
    ];

    /**
     * Send a CAPI event to Meta Graph API.
     *
     * action_source 'chat' is correct for WhatsApp Business messages per Meta spec.
     * Valid values: website, app, phone_call, chat, email, in_store, other
     *
     * @param  array  $userData  Raw PII: ['phone' => '628xx', 'fbc' => 'ctwa_clid', 'name' => '...', 'email' => '...']
     * @param  array  $customData  Event-specific payload: ['value' => 100000, 'currency' => 'IDR']
     * @param  string|null  $eventId  Deduplication key — must match browser Pixel eventID when running both
     * @return array ['success' => bool, 'response' => array, 'capi_event_id' => int|null]
     */
    public function sendEvent(
        int $userId,
        string $eventName,
        array $userData,
        array $customData = [],
        ?string $eventId = null,
        string $actionSource = 'chat',
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
                    'fbtrace_id' => $responseData['fbtrace_id'] ?? null,
                ]);

                return ['success' => true, 'response' => $responseData, 'capi_event_id' => $capiEvent->id];
            }

            $this->logMetaError($userId, $eventName, $response->status(), $responseData);

            $capiEvent->update([
                'status' => 'failed',
                'meta_response' => $responseData,
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
     * Lead event — fired when a new contact arrives via a CTWA ad click.
     * The ctwa_clid is used as fbc for attribution.
     *
     * EMQ note: we pass name for fn/ln + wa_id as external_id to improve match quality.
     */
    public function sendLeadEvent(WhatsAppContact $contact): void
    {
        $this->sendEvent(
            userId: $contact->user_id,
            eventName: 'Lead',
            userData: $this->contactToUserData($contact),
            customData: [
                'lead_event_source' => 'ctwa',
                'content_name' => $contact->ctwa_headline ?? 'WhatsApp Ad',
            ],
            eventId: 'lead_contact_'.$contact->id,
            contactId: $contact->id
        );
    }

    /**
     * Contact event — fired when a new organic (non-ad) contact messages for the first time.
     * action_source 'chat' per Meta spec for WhatsApp.
     */
    public function sendContactEvent(WhatsAppContact $contact): void
    {
        $this->sendEvent(
            userId: $contact->user_id,
            eventName: 'Contact',
            userData: $this->contactToUserData($contact),
            customData: [],
            eventId: 'contact_new_'.$contact->id,
            contactId: $contact->id
        );
    }

    /**
     * Purchase event — fired when an order is confirmed paid.
     * Includes ctwa_clid attribution if the contact is still within the 7-day window.
     */
    public function sendPurchaseEvent(Order $order, WhatsAppContact $contact): void
    {
        $userData = $this->contactToUserData($contact);

        // Only attach ctwa_clid attribution if within the window
        if (
            $contact->ctwa_clid &&
            $contact->attribution_expires_at &&
            $contact->attribution_expires_at->isFuture()
        ) {
            $userData['fbc'] = $contact->ctwa_clid;
        } else {
            unset($userData['fbc']);
        }

        $this->sendEvent(
            userId: $order->user_id ?? $contact->user_id,
            eventName: 'Purchase',
            userData: $userData,
            customData: [
                'value' => (float) $order->total,
                'currency' => 'IDR',
                'order_id' => (string) $order->id,
                'content_name' => 'Order #'.$order->id,
                'content_type' => 'product',
                'num_items' => $order->items?->count() ?? 1,
            ],
            eventId: 'purchase_order_'.$order->id,
            contactId: $contact->id
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a raw user_data array from a WhatsAppContact.
     * Includes wa_id as external_id and attempts to parse fn/ln from name.
     */
    private function contactToUserData(WhatsAppContact $contact): array
    {
        $userData = [
            'phone' => $contact->wa_id,
            'external_id' => $contact->wa_id, // wa_id is stable cross-session
        ];

        if ($contact->ctwa_clid) {
            $userData['fbc'] = $contact->ctwa_clid;
        }

        // Parse name into first/last for EMQ improvement
        if ($contact->name && $contact->name !== $contact->wa_id) {
            $parts = explode(' ', trim($contact->name), 2);
            $userData['first_name'] = $parts[0];
            if (isset($parts[1])) {
                $userData['last_name'] = $parts[1];
            }
        }

        return $userData;
    }

    private function getPixelSettings(int $userId): ?MetaPixelSetting
    {
        return MetaPixelSetting::where('user_id', $userId)->first();
    }

    /**
     * SHA256 hash with normalize-before-hash (required by Meta for all PII fields).
     * fbc and fbp are NOT hashed — they are sent as raw cookie strings.
     */
    private function hashData(string $value): string
    {
        return hash('sha256', strtolower(trim($value)));
    }

    /**
     * Build and hash the user_data object for the CAPI payload.
     *
     * Rules:
     * - ph: digits only with country code, then SHA256
     * - em: lowercase trim, then SHA256
     * - fn/ln: lowercase trim, then SHA256
     * - external_id: hash recommended
     * - fbc/fbp: send raw — NOT hashed
     */
    private function buildUserData(array $raw, int $eventTime): array
    {
        $userData = [];

        if (! empty($raw['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $raw['phone']);
            $userData['ph'] = [$this->hashData($phone)];
        }

        if (! empty($raw['email'])) {
            $userData['em'] = [$this->hashData($raw['email'])];
        }

        if (! empty($raw['first_name'])) {
            $userData['fn'] = [$this->hashData($raw['first_name'])];
        }

        if (! empty($raw['last_name'])) {
            $userData['ln'] = [$this->hashData($raw['last_name'])];
        }

        if (! empty($raw['external_id'])) {
            // Hash recommended for external_id (Meta spec allows unhashed but recommends hashing)
            $userData['external_id'] = [$this->hashData((string) $raw['external_id'])];
        }

        if (! empty($raw['fbc'])) {
            // fbc is sent RAW (not hashed) — format: fb.1.{event_time}.{ctwa_clid}
            $userData['fbc'] = 'fb.1.'.$eventTime.'.'.$raw['fbc'];
        }

        if (! empty($raw['fbp'])) {
            // fbp is sent RAW (not hashed) — set by Meta Pixel browser-side
            $userData['fbp'] = $raw['fbp'];
        }

        return $userData;
    }

    /**
     * Log Meta API error with human-readable diagnosis from known error codes.
     */
    private function logMetaError(int $userId, string $eventName, int $httpStatus, array $responseData): void
    {
        $errorCode = $responseData['error']['code'] ?? null;
        $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
        $diagnosis = $errorCode ? (self::ERROR_CODES[$errorCode] ?? null) : null;

        Log::warning('CAPI: Event failed', array_filter([
            'user_id' => $userId,
            'event_name' => $eventName,
            'http_status' => $httpStatus,
            'meta_error_code' => $errorCode,
            'meta_error_message' => $errorMessage,
            'diagnosis' => $diagnosis,
            'fbtrace_id' => $responseData['error']['fbtrace_id'] ?? null,
        ]));
    }
}
