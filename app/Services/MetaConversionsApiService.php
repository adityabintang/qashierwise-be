<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaConversionsApiService
{
    private const GRAPH_API_VERSION = 'v21.0';

    protected ?string $pixelId;

    protected ?string $accessToken;

    protected ?string $testEventCode;

    public function __construct()
    {
        $this->pixelId = config('services.meta.pixel_id');
        $this->accessToken = config('services.meta.capi_token');
        $this->testEventCode = config('services.meta.test_event_code');
    }

    /**
     * Send an event to Meta Conversions API.
     *
     * @param  string  $eventName  Standard event name (e.g. PageView, CompleteRegistration, Lead, Purchase)
     * @param  Request|null  $request  Current HTTP request, used to extract IP/UA/fbp/fbc
     * @param  array  $userData  Additional user data (em, ph, fn, ln, external_id, etc.) — raw, will be hashed
     * @param  array  $customData  Custom event data (value, currency, content_ids, etc.)
     * @param  string|null  $eventId  Unique event id for deduplication with Pixel
     * @param  string|null  $eventSourceUrl  URL where the event happened
     */
    public function sendEvent(
        string $eventName,
        ?Request $request = null,
        array $userData = [],
        array $customData = [],
        ?string $eventId = null,
        ?string $eventSourceUrl = null,
    ): bool {
        if (! $this->isConfigured()) {
            return false;
        }

        $payload = [
            'data' => [
                $this->buildEventPayload(
                    $eventName,
                    $request,
                    $userData,
                    $customData,
                    $eventId,
                    $eventSourceUrl,
                ),
            ],
        ];

        if (! empty($this->testEventCode)) {
            $payload['test_event_code'] = $this->testEventCode;
        }

        try {
            $response = Http::asJson()
                ->timeout(5)
                ->post($this->endpoint(), $payload + [
                    'access_token' => $this->accessToken,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('Meta CAPI request failed', [
                'event' => $eventName,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Meta CAPI exception', [
                'event' => $eventName,
                'message' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Generate a unique event id for deduplication between Pixel and CAPI.
     */
    public function generateEventId(): string
    {
        return (string) Str::uuid();
    }

    public function isConfigured(): bool
    {
        return ! empty($this->pixelId) && ! empty($this->accessToken);
    }

    protected function endpoint(): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/events',
            self::GRAPH_API_VERSION,
            $this->pixelId,
        );
    }

    protected function buildEventPayload(
        string $eventName,
        ?Request $request,
        array $userData,
        array $customData,
        ?string $eventId,
        ?string $eventSourceUrl,
    ): array {
        $event = [
            'event_name' => $eventName,
            'event_time' => time(),
            'action_source' => 'website',
            'user_data' => $this->buildUserData($request, $userData),
        ];

        if ($eventId !== null) {
            $event['event_id'] = $eventId;
        }

        $sourceUrl = $eventSourceUrl ?? ($request?->fullUrl());
        if (! empty($sourceUrl)) {
            $event['event_source_url'] = $sourceUrl;
        }

        if (! empty($customData)) {
            $event['custom_data'] = $customData;
        }

        return $event;
    }

    /**
     * Build user_data block. PII fields are normalized + SHA-256 hashed per
     * Meta's matching specification; client_ip_address, client_user_agent,
     * fbp, and fbc are sent as plain text (Meta requires them un-hashed).
     */
    protected function buildUserData(?Request $request, array $userData): array
    {
        $hashable = [
            'em' => fn ($v) => strtolower(trim((string) $v)),
            'ph' => fn ($v) => preg_replace('/\D+/', '', (string) $v),
            'fn' => fn ($v) => strtolower(trim((string) $v)),
            'ln' => fn ($v) => strtolower(trim((string) $v)),
            'ct' => fn ($v) => strtolower(preg_replace('/\s+/', '', (string) $v)),
            'st' => fn ($v) => strtolower(preg_replace('/\s+/', '', (string) $v)),
            'country' => fn ($v) => strtolower(trim((string) $v)),
            'zp' => fn ($v) => preg_replace('/\s+/', '', (string) $v),
            'external_id' => fn ($v) => (string) $v,
        ];

        $data = [];

        foreach ($hashable as $key => $normalizer) {
            if (empty($userData[$key])) {
                continue;
            }

            $values = is_array($userData[$key]) ? $userData[$key] : [$userData[$key]];
            $hashed = [];
            foreach ($values as $value) {
                $normalized = $normalizer($value);
                if ($normalized === '' || $normalized === null) {
                    continue;
                }
                $hashed[] = hash('sha256', $normalized);
            }

            if (! empty($hashed)) {
                $data[$key] = count($hashed) === 1 ? $hashed[0] : $hashed;
            }
        }

        if ($request !== null) {
            $ip = $request->ip();
            if (! empty($ip)) {
                $data['client_ip_address'] = $ip;
            }

            $ua = $request->userAgent();
            if (! empty($ua)) {
                $data['client_user_agent'] = $ua;
            }

            $fbp = $request->cookie('_fbp');
            if (! empty($fbp)) {
                $data['fbp'] = $fbp;
            }

            $fbc = $request->cookie('_fbc') ?? $this->buildFbcFromQuery($request);
            if (! empty($fbc)) {
                $data['fbc'] = $fbc;
            }
        }

        if (! empty($userData['fbp']) && empty($data['fbp'])) {
            $data['fbp'] = $userData['fbp'];
        }
        if (! empty($userData['fbc']) && empty($data['fbc'])) {
            $data['fbc'] = $userData['fbc'];
        }

        return $data;
    }

    /**
     * If user lands with ?fbclid=... and the _fbc cookie hasn't been set yet,
     * synthesize the fbc value Meta expects so the first event isn't lost.
     */
    protected function buildFbcFromQuery(Request $request): ?string
    {
        $fbclid = $request->query('fbclid');
        if (empty($fbclid)) {
            return null;
        }

        return sprintf('fb.1.%d.%s', (int) (microtime(true) * 1000), $fbclid);
    }
}
