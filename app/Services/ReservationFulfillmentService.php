<?php

namespace App\Services;

use App\Models\BuyerCalendarToken;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Netflie\WhatsAppCloudApi\Message\Media\LinkID;
use Netflie\WhatsAppCloudApi\Message\Media\MediaObjectID;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

/**
 * Orchestrates the post-payment + post-confirm messaging for reservations.
 *
 * Flow:
 *   QRIS paid ──▶ onPaymentConfirmed()
 *       └─ customer receives: confirmation text + invoice PDF
 *       └─ merchant receives: new-reservation notification (MerchantNotificationService)
 *
 *   Merchant clicks "Selesai" ──▶ onMerchantComplete()
 *       └─ customer receives: "reservation confirmed by merchant" + Google Calendar link
 *
 * Channel rules (same as DeliveryFulfillmentService):
 *   - Customer messages → official WABA within 24h, else Fonnte fallback.
 *   - For reservations the customer typically has NOT messaged the merchant,
 *     so Fonnte is the likely path. The WABA try/catch handles this transparently.
 */
class ReservationFulfillmentService
{
    public function __construct(
        protected FonnteService $fonnte,
        protected WhatsAppAccountService $accounts,
        protected InvoiceService $invoices,
        protected GoogleCalendarService $calendar,
    ) {}

    // =====================================================================
    // Transitions
    // =====================================================================

    /**
     * Called by ProcessReservationPayment after QRIS payment is confirmed.
     *
     * Sends the customer:
     *   1. A WhatsApp confirmation text (WABA / Fonnte fallback).
     *   2. The invoice PDF as a WhatsApp document (WABA only, non-fatal).
     */
    public function onPaymentConfirmed(Reservation $reservation): void
    {
        $reservation->loadMissing(['store', 'table', 'user']);

        $name  = $reservation->customer_name ?: 'Pelanggan';
        $store = $reservation->store?->name ?? 'toko kami';
        $date  = $reservation->reservation_date instanceof \Carbon\Carbon
            ? $reservation->reservation_date->locale('id')->isoFormat('D MMMM YYYY')
            : \Carbon\Carbon::parse((string) $reservation->reservation_date)->locale('id')->isoFormat('D MMMM YYYY');
        $time  = $reservation->reservation_time ?? '';

        $msg  = "✅ *Pembayaran Berhasil & Reservasi Dikonfirmasi!*\n\n";
        $msg .= "Halo {$name}, reservasi Anda di *{$store}* telah dikonfirmasi.\n\n";
        $msg .= "📋 *Detail Reservasi:*\n";
        $msg .= "Kode   : *{$reservation->order_id}*\n";
        $msg .= "Tanggal: {$date}\n";
        if ($time) {
            $msg .= "Waktu  : {$time} WIB\n";
        }
        $msg .= "Tamu   : {$reservation->guest_count} orang\n";
        if ($reservation->table) {
            $msg .= "Meja   : {$reservation->table->number}\n";
        }

        $msg .= "\n💰 *Pembayaran:*\n";
        $msg .= 'Total  : Rp '.number_format((float) $reservation->total_amount, 0, ',', '.')."\n";
        $msg .= 'Dibayar: Rp '.number_format((float) $reservation->paid_amount, 0, ',', '.')."\n";

        if ((float) $reservation->remaining_amount > 0) {
            $msg .= 'Sisa   : Rp '.number_format((float) $reservation->remaining_amount, 0, ',', '.')."\n";
            $msg .= "\n⚠️ Pelunasan sisa tagihan dilakukan di tempat pada hari H.\n";
        }

        // Calendar: check if the buyer has already connected their Google Calendar.
        $buyerEmail = strtolower(trim((string) $reservation->email));
        $buyerToken = $buyerEmail ? BuyerCalendarToken::findByEmail($buyerEmail) : null;

        if ($buyerToken) {
            // Buyer already connected → create the event on their calendar silently.
            try {
                $eventId = $this->calendar->createEventForBuyer($reservation);
                if ($eventId) {
                    $msg .= "\n📅 *Reservasi ini telah ditambahkan ke Google Calendar Anda secara otomatis.*\n";
                    Log::info('ReservationFulfillmentService: buyer calendar event created', [
                        'reservation_id' => $reservation->id,
                        'event_id'       => $eventId,
                        'buyer_email'    => $buyerEmail,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('ReservationFulfillmentService: buyer calendar auto-create failed', [
                    'reservation_id' => $reservation->id,
                    'error'          => $e->getMessage(),
                ]);
                // Fall through to send the manual link as fallback.
                $msg .= $this->buildCalendarFallbackSection($reservation);
            }
        } else {
            // Buyer has NOT connected → send connect link + manual template link as backup.
            $msg .= $this->buildCalendarConnectSection($reservation);
        }

        // If merchant has Google Calendar connected, buyer also gets an email invite
        // (via sendUpdates=all in GoogleCalendarService::createEvent()).
        if ($reservation->user?->google_calendar_refresh_token) {
            $msg .= "\n📧 Undangan kalender juga telah dikirim ke email Anda.\n";
        }

        $msg .= "\nBerikut kami kirimkan invoice reservasi Anda.\nTerima kasih! 🙏";

        $this->sendCustomerText($reservation, $msg);
        $this->sendInvoiceToCustomer($reservation);
    }

    /**
     * Called by ReservationController::complete() when the merchant marks the
     * reservation as "Selesai" (completed) from the dashboard.
     *
     * Sends the customer:
     *   1. A WhatsApp message confirming the merchant has acknowledged.
     *   2. A Google Calendar link so the buyer can save the date.
     */
    public function onMerchantComplete(Reservation $reservation): void
    {
        $reservation->loadMissing(['store', 'table', 'user']);

        $name  = $reservation->customer_name ?: 'Pelanggan';
        $store = $reservation->store?->name ?? 'toko kami';
        $date  = $reservation->reservation_date instanceof \Carbon\Carbon
            ? $reservation->reservation_date->locale('id')->isoFormat('D MMMM YYYY')
            : \Carbon\Carbon::parse((string) $reservation->reservation_date)->locale('id')->isoFormat('D MMMM YYYY');
        $time  = $reservation->reservation_time ?? '';

        $msg  = "🎉 *Reservasi Anda Telah Dikonfirmasi!*\n\n";
        $msg .= "Halo {$name}, *{$store}* sudah mengkonfirmasi reservasi Anda.\n\n";
        $msg .= "📋 *Detail Reservasi:*\n";
        $msg .= "Kode   : *{$reservation->order_id}*\n";
        $msg .= "Tanggal: {$date}\n";
        if ($time) {
            $msg .= "Waktu  : {$time} WIB\n";
        }
        $msg .= "Tamu   : {$reservation->guest_count} orang\n";
        if ($reservation->table) {
            $msg .= "Meja   : {$reservation->table->number}\n";
        }

        // Calendar: auto-create if buyer is connected, else send connect link.
        $buyerEmail = strtolower(trim((string) $reservation->email));
        $buyerToken = $buyerEmail ? BuyerCalendarToken::findByEmail($buyerEmail) : null;

        if ($buyerToken) {
            try {
                $this->calendar->createEventForBuyer($reservation);
                $msg .= "\n📅 Reservasi ini telah ditambahkan ke Google Calendar Anda.\n";
            } catch (\Throwable $e) {
                Log::warning('ReservationFulfillmentService: buyer calendar auto-create failed (complete)', [
                    'reservation_id' => $reservation->id,
                    'error'          => $e->getMessage(),
                ]);
                $msg .= $this->buildCalendarFallbackSection($reservation);
            }
        } else {
            $msg .= $this->buildCalendarConnectSection($reservation);
        }

        $msg .= "\nSampai jumpa dan kami tunggu kedatangan Anda! 🙏";

        $this->sendCustomerText($reservation, $msg);
    }

    // =====================================================================
    // Calendar section builders
    // =====================================================================

    /**
     * Section for buyers who have NOT yet connected Google Calendar.
     * Only the short connect link — no manual "add to calendar" fallback.
     */
    protected function buildCalendarConnectSection(Reservation $reservation): string
    {
        $connectUrl = $this->buildBuyerConnectUrl($reservation);

        $section  = "\n📅 *Hubungkan Google Calendar Anda*\n";
        $section .= "Tap sekali untuk terhubung — semua reservasi mendatang otomatis masuk kalender Anda:\n";
        $section .= "{$connectUrl}\n";

        return $section;
    }

    /**
     * Fallback when auto-create failed despite a stored token.
     * No manual link — just a short re-connect prompt.
     */
    protected function buildCalendarFallbackSection(Reservation $reservation): string
    {
        $connectUrl = $this->buildBuyerConnectUrl($reservation);

        return "\n📅 Hubungkan ulang Google Calendar Anda:\n{$connectUrl}\n";
    }

    /**
     * Build a short alias connect URL stored in cache.
     * Returns a clean URL like https://app.../c/Ab3xY9kZ instead of the raw
     * encrypted token (which would be 200+ characters in a WhatsApp message).
     */
    protected function buildBuyerConnectUrl(Reservation $reservation): string
    {
        $email         = strtolower(trim((string) $reservation->email));
        $reservationId = $reservation->id;
        $fullToken     = Crypt::encryptString("{$email}|{$reservationId}|".time());

        // Generate a short random code and cache the full token under it.
        // TTL: 24 hours (generous — buyer may open the link hours later).
        $code = $this->generateShortCode();
        \Illuminate\Support\Facades\Cache::put(
            "cal_connect:{$code}",
            $fullToken,
            now()->addHours(24)
        );

        return rtrim(config('app.url'), '/').'/c/'.$code;
    }

    /**
     * Generate a unique 8-character alphanumeric short code.
     */
    protected function generateShortCode(): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (\Illuminate\Support\Facades\Cache::has("cal_connect:{$code}"));

        return $code;
    }

    // =====================================================================
    // Outbound helpers
    // =====================================================================

    /**
     * Send the invoice PDF as a WhatsApp document via the merchant's WABA.
     * Non-fatal: logs and returns silently on any failure.
     */
    protected function sendInvoiceToCustomer(Reservation $reservation): void
    {
        // Invoice is generated from the linked POS Order.
        $order = $reservation->posOrder;

        if (! $order) {
            Log::warning('ReservationFulfillmentService: no POS order linked, skipping invoice', [
                'reservation_id' => $reservation->id,
            ]);

            return;
        }

        try {
            $client = $this->customerClient($reservation);
            if (! $client) {
                Log::warning('ReservationFulfillmentService: no WABA account, skipping invoice', [
                    'reservation_id' => $reservation->id,
                ]);

                return;
            }

            $inv = $this->invoices->generate($order);
            if (! $inv) {
                return;
            }

            $to      = $this->intlPhone($reservation->phone);
            $caption = "🧾 Invoice Reservasi #{$reservation->order_id}";

            try {
                $mediaId = $client->uploadMedia($inv['tmp_path'])->decodedBody()['id'] ?? null;
                if ($mediaId) {
                    $client->sendDocument($to, new MediaObjectID($mediaId), $inv['filename'], $caption);
                } else {
                    $client->sendDocument($to, new LinkID($inv['url']), $inv['filename'], $caption);
                }
            } catch (\Throwable $e) {
                Log::warning('ReservationFulfillmentService: invoice media upload failed, falling back to link', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
                $client->sendDocument($to, new LinkID($inv['url']), $inv['filename'], $caption);
            } finally {
                @unlink($inv['tmp_path']);
            }

            Log::info('ReservationFulfillmentService: invoice sent to customer', [
                'reservation_id' => $reservation->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ReservationFulfillmentService: invoice send failed (non-fatal)', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a plain-text WhatsApp to the customer.
     * Uses official WABA within the 24h window, Fonnte as fallback.
     */
    protected function sendCustomerText(Reservation $reservation, string $message): void
    {
        $client  = $this->customerClient($reservation);
        $contact = $this->resolveCustomerContact($reservation);

        if ($client && (! $contact || $this->customerWithin24h($contact))) {
            try {
                $client->sendTextMessage($this->intlPhone($reservation->phone), $message);

                return;
            } catch (\Throwable $e) {
                Log::warning('ReservationFulfillmentService: WABA send failed, falling back to Fonnte', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fonnte fallback (no WABA account, or outside 24h window).
        try {
            $this->fonnte->send($reservation->phone, $message);
        } catch (\Throwable $e) {
            Log::warning('ReservationFulfillmentService: Fonnte fallback also failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // =====================================================================
    // Channel resolution helpers (mirrors DeliveryFulfillmentService)
    // =====================================================================

    protected function customerClient(Reservation $reservation): ?WhatsAppCloudApi
    {
        $userId = $reservation->user_id;
        if (! $userId) {
            return null;
        }

        $account = $this->accounts->getActiveAccount($userId);
        if (! $account) {
            return null;
        }

        return new WhatsAppCloudApi([
            'from_phone_number_id' => $account->phone_number_id,
            'access_token'         => $account->access_token,
        ]);
    }

    protected function resolveCustomerContact(Reservation $reservation): ?WhatsAppContact
    {
        $userId = $reservation->user_id;
        if (! $userId || empty($reservation->phone)) {
            return null;
        }

        $intl = $this->intlPhone($reservation->phone);
        $raw  = $this->digits($reservation->phone);

        return WhatsAppContact::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->where('wa_id', $intl)->orWhere('wa_id', $raw))
            ->latest('id')
            ->first();
    }

    protected function customerWithin24h(WhatsAppContact $contact): bool
    {
        $lastInbound = WhatsAppMessage::withoutGlobalScopes()
            ->where('contact_id', $contact->id)
            ->where('direction', 'incoming')
            ->latest('sent_at')
            ->value('sent_at');

        if (! $lastInbound) {
            return false;
        }

        return $lastInbound->gt(now()->subDay());
    }

    protected function intlPhone(string $phone): string
    {
        $d = $this->digits($phone);
        if (str_starts_with($d, '0')) {
            return '62'.substr($d, 1);
        }
        if (str_starts_with($d, '62')) {
            return $d;
        }
        if (str_starts_with($d, '8')) {
            return '62'.$d;
        }

        return $d;
    }

    protected function digits(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
