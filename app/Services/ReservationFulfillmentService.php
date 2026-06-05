<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
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

        // Calendar link — buyer can save the date without OAuth.
        try {
            $calUrl = $this->calendar->buildAddToCalendarUrl($reservation);
            $msg .= "\n📅 *Tambahkan ke Google Calendar Anda:*\n{$calUrl}\n";
        } catch (\Throwable $e) {
            Log::warning('ReservationFulfillmentService: calendar link build failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }

        // If the merchant has Google Calendar connected, mention the email invite.
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

        $msg .= "\n📅 *Tandai tanggal di kalender Anda:*\n";

        try {
            $calUrl = $this->calendar->buildAddToCalendarUrl($reservation);
            $msg .= "{$calUrl}\n";
        } catch (\Throwable $e) {
            Log::warning('ReservationFulfillmentService: calendar link build failed (complete)', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
            $msg .= "(link kalender tidak tersedia)\n";
        }

        $msg .= "\nSampai jumpa dan kami tunggu kedatangan Anda! 🙏";

        $this->sendCustomerText($reservation, $msg);
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
