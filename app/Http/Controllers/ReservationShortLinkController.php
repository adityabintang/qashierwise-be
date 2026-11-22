<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves short reservation form links (/r/{code}) generated when a buyer
 * taps the "Reservasi" button in the WhatsApp catalog order flow.
 *
 * The cached payload contains:
 *   - base_url  : the full reservation form URL (with merchantName)
 *   - phone     : buyer's WA phone number (wa_id e.g. 6281234...)
 *   - items     : array of catalog cart items [{product_retailer_id, product_name,
 *                 quantity, item_price, currency}]
 *
 * When resolved, redirects to the form URL with `phone` and `prefill` query params.
 * The form blade reads these and auto-fills the phone field + product selections.
 */
class ReservationShortLinkController extends Controller
{
    public function resolve(Request $request, string $code)
    {
        $payload = Cache::get("rsv_link:{$code}");

        if (! $payload) {
            // Link expired or invalid — redirect to base form without pre-fill.
            // Try to extract merchantName from a fallback, or show error page.
            return view('reservation.link-expired');
        }

        // Keep the link alive (one-use is too strict for reservation — buyer
        // might switch from mobile data to WiFi and reload). Expire after 1 hour
        // from first access so the pre-fill data is still fresh.
        Cache::put("rsv_link:{$code}", $payload, now()->addHour());

        $baseUrl = $payload['base_url'];
        $phone   = $payload['phone'] ?? '';
        $items   = $payload['items'] ?? [];

        // Encode pre-fill data as a compact JSON query param.
        // The form reads ?prefill=base64(json) on load.
        $prefill = base64_encode(json_encode([
            'phone' => $phone,
            'items' => $items,
        ]));

        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $url = $baseUrl.$separator.'prefill='.urlencode($prefill);

        return redirect()->away($url);
    }
}
