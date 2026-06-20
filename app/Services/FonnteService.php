<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP wrapper for Fonnte send-message API.
 *
 * API contract (empirically verified 2026-05-28):
 *   POST https://api.fonnte.com/send
 *   Header: Authorization: <raw_token>     (no "Bearer" prefix)
 *   Body (multipart/form-data):
 *     target   string  – phone number(s), comma-separated for multiple
 *     message  string  – text to send
 *     typing   bool    – show "typing..." indicator (PM default: true)
 *     delay    string  – random delay between messages, e.g. "5-10" (seconds)
 *
 * Success response:
 *   { "status": true, "detail": "success! message in queue", "id": [...] }
 *
 * Error response:
 *   { "status": false, "reason": "<reason>" }
 */
class FonnteService
{
    public function __construct() {}

    /**
     * Send a single WhatsApp message via Fonnte.
     *
     * @param  string  $target   Recipient phone (any Indonesian format; Fonnte normalizes)
     * @param  string  $message  Text body
     * @return array{success: bool, response: array, error?: string}
     */
    public function send(string $target, string $message): array
    {
        $token = config('fonnte.token');
        if (empty($token)) {
            Log::warning('FonnteService: FONNTE_TOKEN tidak diset, notifikasi di-skip');
            return ['success' => false, 'response' => [], 'error' => 'FONNTE_TOKEN belum diset'];
        }

        $normalized = $this->normalizePhone($target);
        if ($normalized === null) {
            return ['success' => false, 'response' => [], 'error' => 'Invalid phone format: ' . $target];
        }

        try {
            $response = Http::asMultipart()
                ->withHeaders(['Authorization' => $token])
                ->timeout(15)
                ->post(rtrim(config('fonnte.base_url'), '/') . '/send', [
                    ['name' => 'target',  'contents' => $normalized],
                    ['name' => 'message', 'contents' => $message],
                    ['name' => 'typing',  'contents' => config('fonnte.typing') ? 'true' : 'false'],
                    ['name' => 'delay',   'contents' => (string) config('fonnte.delay')],
                ]);

            $json = $response->json() ?? [];
            $ok = (bool) ($json['status'] ?? false);

            if ($ok) {
                Log::info('Fonnte: message queued', [
                    'target'   => $normalized,
                    'id'       => $json['id'] ?? null,
                    'remaining_quota' => data_get($json, 'quota.*.remaining'),
                ]);
            } else {
                Log::warning('Fonnte: send failed', [
                    'target'   => $normalized,
                    'reason'   => $json['reason'] ?? 'unknown',
                    'http'     => $response->status(),
                ]);
            }

            return [
                'success'  => $ok,
                'response' => $json,
                'error'    => $ok ? null : ($json['reason'] ?? 'unknown'),
            ];
        } catch (\Throwable $e) {
            Log::error('Fonnte: HTTP exception', [
                'target' => $normalized,
                'error'  => $e->getMessage(),
            ]);
            return ['success' => false, 'response' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Strip non-digits & leading +. Returns null if result has fewer than 8 digits.
     * Fonnte handles its own country code detection (08xxx → 628xxx via Fonnte's
     * Indonesia default), so we don't force a prefix here.
     */
    private function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if (strlen($digits) < 8) {
            return null;
        }
        return $digits;
    }
}
