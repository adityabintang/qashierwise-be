<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\Button;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\ButtonAction;
use Netflie\WhatsAppCloudApi\Message\Media\LinkID;
use Netflie\WhatsAppCloudApi\Message\Media\MediaObjectID;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

/**
 * Orchestrates the post-payment delivery lifecycle:
 *
 *   paid ──confirm──▶ (pickup) customer notified, done
 *                 └─▶ (delivery) driver assigned ▶ out_for_delivery
 *                          ├─ message to DRIVER  (Fonnte / admin number) + proof link
 *                          └─ message to CUSTOMER (official WABA, fallback Fonnte >24h)
 *
 *   driver uploads proof + taps "delivered" ▶ customer asked "received?" (2 buttons)
 *          ├─ Konfirmasi ▶ delivered
 *          └─ Complain   ▶ complaint (bot OFF for the number, escalate to PIC)
 *
 * Channel rules (per PM):
 *   - Messages to DRIVER and MERCHANT use Fonnte (Qashierwise admin number).
 *   - Messages to CUSTOMER use the merchant's official WABA, EXCEPT when the
 *     24h customer-service window has lapsed — then fall back to Fonnte (text).
 */
class DeliveryFulfillmentService
{
    public function __construct(
        protected FonnteService $fonnte,
        protected WhatsAppAccountService $accounts,
        protected InvoiceService $invoices,
    ) {}

    // ---- Customer-button IDs (carry the order id so the webhook can correlate)
    public const BTN_RECEIVED_OK = 'dlv_ok';

    public const BTN_RECEIVED_BAD = 'dlv_bad';

    // ====================================================================
    // Transitions
    // ====================================================================

    /**
     * Merchant confirms a PICKUP order: tell the customer it's confirmed and
     * being prepared. No driver involved.
     */
    public function confirmPickup(Order $order): void
    {
        if (! $order->isPickup()) {
            throw new \InvalidArgumentException('Order is not a pickup order.');
        }

        // Pickup is paid at the counter — confirming it marks the order paid.
        $order->forceFill([
            'status' => Order::STATUS_PAID,
            'fulfillment_status' => Order::FULFILLMENT_CONFIRMED,
            'confirmed_at' => now(),
        ])->save();

        $name = $order->customer_name ?: 'Pelanggan';
        $this->sendCustomerText(
            $order,
            "Halo {$name}, pesanan *#{$order->order_number}* sudah *dikonfirmasi* "
            ."Terimakasih, berikut invoice pembelian nya.\n\n"
        );

        // Invoice PDF (official WABA document).
        $this->sendInvoiceToCustomer($order);
    }

    /**
     * Merchant confirms a DELIVERY order and assigns a courier. Fires two
     * messages at once: one to the driver (Fonnte + proof link), one to the
     * customer (official WABA / Fonnte fallback).
     */
    public function assignCourier(Order $order, string $courierName, string $courierPhone, ?int $driverId = null): void
    {
        if (! $order->isDelivery()) {
            throw new \InvalidArgumentException('Order is not a delivery order.');
        }
        if ($order->status !== Order::STATUS_PAID) {
            throw new \InvalidArgumentException('Order belum dibayar — tidak bisa dikonfirmasi untuk pengantaran.');
        }

        $order->forceFill([
            'delivery_driver_id' => $driverId,
            'courier_name' => $courierName,
            'courier_phone' => $courierPhone,
            'delivery_token' => $order->delivery_token ?: $this->generateToken(),
            'fulfillment_status' => Order::FULFILLMENT_OUT_FOR_DELIVERY,
            'confirmed_at' => $order->confirmed_at ?: now(),
            'out_for_delivery_at' => now(),
        ])->save();

        $order->refresh();

        // 1) Message to the CUSTOMER — driver info + safety warning.
        $this->sendCourierAssignedToCustomer($order);

        // 2) Invoice PDF to the CUSTOMER (official WABA document).
        $this->sendInvoiceToCustomer($order);

        // 3) Message to the DRIVER — task + proof-upload link.
        $this->sendDeliveryTaskToCourier($order);
    }

    /**
     * Driver uploaded proof + pinned the delivery location on the map and tapped
     * "pesanan telah diantarkan" on the public page. Store the proof + location,
     * then ask the customer to confirm receipt.
     *
     * @param  array{lat: float|string|null, lng: float|string|null}|null  $location
     */
    public function submitProof(Order $order, ?string $proofUrl, ?array $location = null): void
    {
        if ($order->fulfillment_status !== Order::FULFILLMENT_OUT_FOR_DELIVERY) {
            // Idempotent: already delivered or not yet out for delivery.
            Log::info('submitProof skipped — unexpected fulfillment_status', [
                'order_id' => $order->id,
                'fulfillment_status' => $order->fulfillment_status,
            ]);

            return;
        }

        $attrs = [];
        if ($proofUrl) {
            $attrs['proof_image_url'] = $proofUrl;
        }
        if ($location && isset($location['lat'], $location['lng']) && $location['lat'] !== null && $location['lng'] !== null) {
            $attrs['customer_lat'] = $location['lat'];
            $attrs['customer_lng'] = $location['lng'];
        }
        if ($attrs) {
            $order->forceFill($attrs)->save();
        }

        $this->askDeliveryReceived($order);
    }

    /**
     * Customer tapped "Konfirmasi" — goods received as expected.
     */
    public function markDelivered(Order $order): void
    {
        if ($order->fulfillment_status === Order::FULFILLMENT_DELIVERED) {
            return; // idempotent
        }

        $order->forceFill([
            'fulfillment_status' => Order::FULFILLMENT_DELIVERED,
            'delivered_at' => now(),
        ])->save();

        $this->sendCustomerText(
            $order,
            "🎉 Terima kasih! Pesanan *#{$order->order_number}* telah selesai.\n\n"
            .'Sampai jumpa di pesanan berikutnya! 🙏'
        );

        // Inform the merchant (Fonnte / admin number).
        $this->notifyMerchant(
            $order,
            "✅ Pesanan *#{$order->order_number}* telah *DITERIMA* pelanggan ({$order->customer_name}).\n"
            .'Status: delivered.'
        );
    }

    /**
     * Customer tapped "Complain" — turn the bot OFF for this number and escalate
     * to a human PIC.
     */
    public function raiseComplaint(Order $order, ?WhatsAppContact $contact = null): void
    {
        if ($order->fulfillment_status === Order::FULFILLMENT_COMPLAINT) {
            return; // idempotent
        }

        $order->forceFill([
            'fulfillment_status' => Order::FULFILLMENT_COMPLAINT,
        ])->save();

        // Bot OFF for this contact (manual takeover). Bypass global scope —
        // this runs in a webhook context (unauthenticated).
        $contact ??= $this->resolveCustomerContact($order);
        if ($contact) {
            $contact->forceFill(['ai_active' => false])->save();
        }

        // Record the complaint for the /dashboard/complain queue.
        if ($order->store?->user_id) {
            Complaint::create([
                'order_id' => $order->id,
                'user_id' => $order->store->user_id,
                'whatsapp_contact_id' => $contact?->id,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'status' => Complaint::STATUS_OPEN,
            ]);
        }

        // Tell the customer a human will follow up. (Sent before the bot stays
        // silent on subsequent messages.)
        $this->sendCustomerText(
            $order,
            "🙏 Mohon maaf atas ketidaknyamanannya pada pesanan *#{$order->order_number}*.\n\n"
            .'Anda telah kami hubungkan ke tim kami, Silahkan sampaikan keluhan anda'
        );

        // Escalate to merchant/PIC (Fonnte / admin number).
        $this->notifyMerchant(
            $order,
            "⚠️ *KOMPLAIN* pada pesanan *#{$order->order_number}*\n"
            ."Pelanggan : {$order->customer_name} ({$order->customer_phone})\n"
            ."Kurir     : {$order->courier_name}\n\n"
            .'Bot WhatsApp untuk nomor pelanggan ini telah DINONAKTIFKAN. '
            .'Mohon segera ditangani secara manual oleh PIC.'
        );
    }

    /**
     * Merchant/PIC resolved a complaint from /dashboard/complain. Notify the
     * customer that it's resolved and re-activate the AI bot for their number
     * (unless they still have another open complaint).
     */
    public function resolveComplaint(Complaint $complaint, string $resolutionNote): void
    {
        if ($complaint->status === Complaint::STATUS_RESOLVED) {
            return; // idempotent
        }

        $complaint->forceFill([
            'status' => Complaint::STATUS_RESOLVED,
            'resolution_note' => $resolutionNote,
            'resolved_at' => now(),
        ])->save();

        $order = $complaint->order;
        $contact = $complaint->whatsappContact
            ?? ($order ? $this->resolveCustomerContact($order) : null);

        // Notify the customer that the complaint is resolved.
        $orderRef = $order ? " *#{$order->order_number}*" : '';
        $message = "✅ Kabar baik! Keluhan Anda terkait pesanan{$orderRef} telah *selesai ditangani*.\n\n"
            ."📝 Penyelesaian: {$resolutionNote}\n\n"
            .'Terima kasih atas kesabaran Anda.';

        if ($order) {
            $this->sendCustomerText($order, $message);
        } elseif ($complaint->customer_phone) {
            // No linked order — best-effort via Fonnte.
            $this->sendFonnte($complaint->customer_phone, $message);
        }

        // Re-activate the AI bot — but only if this contact has no other open
        // complaint still pending.
        if ($contact) {
            $stillOpen = Complaint::where('whatsapp_contact_id', $contact->id)
                ->where('status', Complaint::STATUS_OPEN)
                ->exists();

            if (! $stillOpen) {
                $contact->forceFill(['ai_active' => true])->save();
            }
        }
    }

    // ====================================================================
    // Outbound message builders
    // ====================================================================

    /**
     * Generate the order invoice PDF and send it to the customer as a WhatsApp
     * document (official WABA only — documents can't go through Fonnte here).
     * Non-fatal: a failure is logged and never blocks order confirmation.
     */
    protected function sendInvoiceToCustomer(Order $order): void
    {
        try {
            $client = $this->customerClient($order);
            if (! $client) {
                Log::warning('Invoice skipped — no official WABA account for merchant', [
                    'order_id' => $order->id,
                ]);

                return;
            }

            $inv = $this->invoices->generate($order);
            if (! $inv) {
                return; // already logged
            }

            $to = $this->intlPhone($order->customer_phone);
            $caption = "🧾 Invoice pesanan #{$order->order_number}";

            try {
                // Prefer uploading the file to WhatsApp and sending by media-id —
                // more reliable than a link (no public-URL fetch dependency).
                $mediaId = $client->uploadMedia($inv['tmp_path'])->decodedBody()['id'] ?? null;

                if ($mediaId) {
                    $client->sendDocument($to, new MediaObjectID($mediaId), $inv['filename'], $caption);
                } else {
                    $client->sendDocument($to, new LinkID($inv['url']), $inv['filename'], $caption);
                }
            } catch (\Throwable $e) {
                // Upload failed — fall back to the public link.
                Log::warning('Invoice media upload failed, falling back to link', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                $client->sendDocument($to, new LinkID($inv['url']), $inv['filename'], $caption);
            } finally {
                @unlink($inv['tmp_path']);
            }

            Log::info('Invoice document sent to customer', [
                'order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send invoice document (non-fatal)', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendCourierAssignedToCustomer(Order $order): void
    {
        $name = $order->customer_name ?: 'Pelanggan';

        $msg = "✅ Halo {$name}, pesanan *#{$order->order_number}* sudah dikonfirmasi "
            ."dan sedang *diantar* ke alamat Anda!\n\n"
            ."🛵 Kurir   : {$order->courier_name}\n"
            ."📱 WA Kurir: {$order->courier_phone}\n\n"
            ."⚠️ *PENTING:* Mohon untuk *tidak* melakukan transaksi apa pun di luar "
            ."pembelian ini dengan kurir. Segala kejadian di luar tugas pembelian dan "
            .'pengantaran berada di luar kuasa dan tanggung jawab toko/merchant.';

        $this->sendCustomerText($order, $msg);
    }

    protected function sendDeliveryTaskToCourier(Order $order): void
    {
        if (empty($order->courier_phone)) {
            return;
        }

        $merchant = $this->merchantName($order);
        $link = $this->proofLink($order);
        // Plain address text exactly as the buyer typed it — no Google Maps link
        // here, since the address wasn't picked from a map and a generated link
        // is error-prone. The driver pins the real location on the proof page.
        $address = trim((string) $order->alamat) ?: '(alamat tidak tersedia)';

        $msg = "Halo {$order->courier_name}, ada pesanan yang perlu diantar dari *{$merchant}*:\n\n"
            ."🧾 Pesanan : #{$order->order_number}\n"
            ."👤 Pembeli : {$order->customer_name}\n"
            ."📱 No. HP  : {$order->customer_phone}\n"
            ."📍 Alamat  : {$address}\n";

        if (! empty($order->catatan)) {
            $msg .= "📝 Catatan : {$order->catatan}\n";
        }

        $msg .= "\nSetelah barang diterima pembeli, *upload bukti*, tandai lokasi "
            ."pengantaran di peta, lalu konfirmasi di halaman berikut:\n{$link}";

        $this->sendFonnte($order->courier_phone, $msg);
    }

    /**
     * Ask the customer to confirm receipt, with two quick-reply buttons. Within
     * the 24h window this is an interactive button message; outside it, a Fonnte
     * text message with keyword instructions (handled by the webhook).
     */
    protected function askDeliveryReceived(Order $order): void
    {
        $body = "📦 Pesanan *#{$order->order_number}* telah diantar.\n\n"
            .'Apakah barang sudah sesuai dan diterima dengan baik?';

        $contact = $this->resolveCustomerContact($order);
        $client = $this->customerClient($order);

        if ($client && $contact && $this->customerWithin24h($contact)) {
            try {
                $action = new ButtonAction([
                    new Button(self::BTN_RECEIVED_OK.':'.$order->id, '✅ Konfirmasi'),
                    new Button(self::BTN_RECEIVED_BAD.':'.$order->id, '⚠️ Complain'),
                ]);
                $client->sendButton($this->intlPhone($order->customer_phone), $body, $action, null, null);

                return;
            } catch (\Throwable $e) {
                Log::warning('Delivery received-question button failed, falling back to Fonnte', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback (no client / outside 24h / button failed): Fonnte text with
        // keyword reply instructions. Webhook maps the keyword to this order.
        $this->sendFonnte(
            $order->customer_phone,
            $body."\n\nBalas *TERIMA* jika sudah sesuai, atau *KOMPLAIN* jika ada masalah."
        );
    }

    // ====================================================================
    // Channel helpers
    // ====================================================================

    /**
     * Send a plain text to the customer: official WABA within 24h, else Fonnte.
     */
    protected function sendCustomerText(Order $order, string $message): void
    {
        $contact = $this->resolveCustomerContact($order);
        $client = $this->customerClient($order);

        if ($client && (! $contact || $this->customerWithin24h($contact))) {
            try {
                $client->sendTextMessage($this->intlPhone($order->customer_phone), $message);

                return;
            } catch (\Throwable $e) {
                Log::warning('Customer official WABA send failed, falling back to Fonnte', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->sendFonnte($order->customer_phone, $message);
    }

    /**
     * Notify the merchant via Fonnte (Qashierwise admin number).
     */
    protected function notifyMerchant(Order $order, string $message): void
    {
        $phone = $this->merchantPhone($order);
        if (! $phone) {
            Log::warning('DeliveryFulfillmentService: merchant phone not found', [
                'order_id' => $order->id,
            ]);

            return;
        }

        $this->sendFonnte($phone, $message);
    }

    protected function sendFonnte(string $phone, string $message): void
    {
        try {
            $this->fonnte->send($phone, $message);
        } catch (\Throwable $e) {
            Log::warning('DeliveryFulfillmentService: Fonnte send failed (non-fatal)', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ====================================================================
    // Resolution helpers
    // ====================================================================

    /**
     * Build an official WABA client for the merchant that owns this order, or
     * null if the merchant has no connected account.
     */
    protected function customerClient(Order $order): ?WhatsAppCloudApi
    {
        $userId = optional($order->store)->user_id;
        if (! $userId) {
            return null;
        }

        $account = $this->accounts->getActiveAccount($userId);
        if (! $account) {
            return null;
        }

        return new WhatsAppCloudApi([
            'from_phone_number_id' => $account->phone_number_id,
            'access_token' => $account->access_token,
        ]);
    }

    /**
     * Find the WhatsAppContact for this order's customer (bypassing the auth
     * global scope, since this runs in jobs/webhooks).
     */
    protected function resolveCustomerContact(Order $order): ?WhatsAppContact
    {
        $userId = optional($order->store)->user_id;
        if (! $userId || empty($order->customer_phone)) {
            return null;
        }

        // Contacts store wa_id in international form (e.g. 6282...). The buyer's
        // typed phone may be local (08...), so match on the normalized form and
        // also try the raw digits as a fallback.
        $intl = $this->intlPhone($order->customer_phone);
        $raw = $this->digits($order->customer_phone);

        return WhatsAppContact::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->where('wa_id', $intl)->orWhere('wa_id', $raw))
            ->latest('id')
            ->first();
    }

    /**
     * True when the customer messaged us within the last 24h (WhatsApp customer
     * service window). When unknown, we optimistically allow official sends and
     * rely on the try/catch fallback.
     */
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

    protected function merchantPhone(Order $order): ?string
    {
        $userId = optional($order->store)->user_id;
        if (! $userId) {
            return null;
        }

        return \App\Models\WhatsAppAccount::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->orderByDesc('is_active')
            ->value('display_phone_number');
    }

    protected function merchantName(Order $order): string
    {
        return optional(optional($order->store)->user)->name ?? 'Toko';
    }

    protected function proofLink(Order $order): string
    {
        return rtrim(config('app.url'), '/').'/delivery/'.$order->delivery_token;
    }

    protected function digits(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Normalize an Indonesian phone to international MSISDN (62…) for the
     * official WhatsApp Cloud API, which rejects local 0-prefixed numbers
     * (error 131026 "Message undeliverable").
     */
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

    protected function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (Order::where('delivery_token', $token)->exists());

        return $token;
    }
}
