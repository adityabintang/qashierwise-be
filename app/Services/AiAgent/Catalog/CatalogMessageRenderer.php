<?php

namespace App\Services\AiAgent\Catalog;

use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Order;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\CatalogOrderFlowService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Log;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\Button;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\ButtonAction;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

/**
 * All "send WhatsApp message" rendering for the catalog/state-machine flow.
 *
 * The orchestrator (CatalogOrderFlowService) decides WHAT to send based on
 * state transitions; this class decides HOW each message is rendered and
 * pushed via the WhatsApp Cloud API.
 *
 * Each renderer guards against the WhatsApp Interactive-Message-only failure
 * mode by falling back to a plain-text version — the customer must always
 * get a reply, even if the button render fails.
 *
 * Button ID constants are referenced via CatalogOrderFlowService so the
 * orchestrator stays the single source of truth for button identity.
 */
class CatalogMessageRenderer
{
    /**
     * Truncate body to WhatsApp's 1024-char interactive message limit, with
     * an ellipsis so the customer knows the message was cut.
     */
    public function truncate(string $text, int $limit = 1020): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 1).'…';
    }

    /**
     * Generic "I didn't understand" reply with a Lihat Menu CTA so the
     * customer is never left at a dead-end. Falls back to text if button
     * sending fails (e.g. account permissions).
     */
    public function sendFallbackWithMenuButton(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        string $body,
    ): void {
        $client = $this->client($account);

        try {
            $action = new ButtonAction([
                new Button(CatalogOrderFlowService::BTN_SHOW_MENU, 'Lihat Menu'),
            ]);
            $client->sendButton(
                $contact->wa_id,
                $body,
                $action,
                null,
                $aiAgent->bot_name ?: null,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send fallback button reply', [
                'contact_wa_id' => $contact->wa_id,
                'error' => $e->getMessage(),
            ]);
            $this->sendText($client, $contact->wa_id, $body);
        }
    }

    /**
     * Cancelled-order reply. Wraps the standard cancel copy with a Lihat
     * Menu button so the customer can one-tap back to ordering.
     */
    public function sendCancelledReply(WhatsAppAccount $account, WhatsAppContact $contact, AiAgent $aiAgent): void
    {
        $client = $this->client($account);
        $body = '❌ Pesanan dibatalkan. Tap *Lihat Menu* untuk memesan lagi kapan saja.';

        try {
            $action = new ButtonAction([
                new Button(CatalogOrderFlowService::BTN_SHOW_MENU, 'Lihat Menu'),
            ]);
            $client->sendButton($contact->wa_id, $body, $action, null, null);
        } catch (\Throwable $e) {
            Log::error('Failed to send cancelled reply button', [
                'contact_wa_id' => $contact->wa_id,
                'error' => $e->getMessage(),
            ]);
            $this->sendText($client, $contact->wa_id,
                '❌ Pesanan dibatalkan. Ketik *menu* kapan saja untuk memesan lagi.');
        }
    }

    /**
     * Greeting reply with Lihat Menu quick-reply button. Saves the customer
     * from typing "menu" and saves us an LLM call. The button routes back to
     * sendCatalog() via WhatsAppWebhookController::handleInteractive.
     */
    public function sendGreetingWithMenuButton(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        string $greetingText,
    ): void {
        $client = $this->client($account);

        try {
            $action = new ButtonAction([
                new Button(CatalogOrderFlowService::BTN_SHOW_MENU, 'Lihat Menu'),
            ]);
            $client->sendButton(
                $contact->wa_id,
                $greetingText,
                $action,
                null,
                $aiAgent->bot_name ?: 'Pesan via WhatsApp',
            );

            Log::info('Greeting with menu button sent', [
                'ai_agent_id' => $aiAgent->id,
                'contact_wa_id' => $contact->wa_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send greeting button, falling back to text', [
                'ai_agent_id' => $aiAgent->id,
                'error' => $e->getMessage(),
            ]);
            $this->sendText($client, $contact->wa_id, $greetingText);
        }
    }

    /**
     * "Konfirmasi cart" prompt — shown after a catalog submit or POS checkout
     * signal. First step of the unified state machine.
     */
    public function sendCartConfirmation(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        array $items,
    ): void {
        $client = $this->client($account);

        $itemsLines = [];
        foreach ($items as $idx => $item) {
            $itemTotal = (float) ($item['item_price'] ?? 0) * (int) ($item['quantity'] ?? 1);
            $itemsLines[] = sprintf(
                '%d. %s x%d — Rp %s',
                $idx + 1,
                $item['product_name'] ?: ($item['product_retailer_id'] ?? 'Produk'),
                $item['quantity'] ?? 1,
                number_format($itemTotal, 0, ',', '.'),
            );
        }

        $subtotal = $this->itemsSubtotal($items);
        $itemsBlock = implode("\n", array_slice($itemsLines, 0, 10));
        if (count($itemsLines) > 10) {
            $itemsBlock .= "\n... dan ".(count($itemsLines) - 10).' item lainnya';
        }

        $body = "🛒 *Ringkasan Pesanan*\n\n"
            .$itemsBlock
            ."\n\nSubtotal: Rp ".number_format($subtotal, 0, ',', '.')
            ."\n\nLanjutkan pesanan ini?";

        try {
            $action = new ButtonAction([
                new Button(CatalogOrderFlowService::BTN_CONFIRM_CART, '✅ Konfirmasi'),
                new Button(CatalogOrderFlowService::BTN_CANCEL_ORDER, '❌ Batal'),
            ]);
            $client->sendButton($contact->wa_id, $this->truncate($body), $action, null, null);
        } catch (\Throwable $e) {
            Log::error('Failed to send cart confirmation', [
                'error' => $e->getMessage(),
                'contact_wa_id' => $contact->wa_id,
            ]);
            $this->sendText($client, $contact->wa_id,
                'Pesanan Anda diterima. Balas *konfirmasi* untuk lanjut atau *batal* untuk membatalkan.');
        }
    }

    /**
     * Fulfillment picker — pickup always offered; delivery & reservasi gated
     * on the merchant's feature flags. WhatsApp caps interactive buttons at
     * 3, which matches our three options.
     */
    public function sendFulfillmentButtons(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        array $items,
    ): void {
        $client = $this->client($account);

        $buttons = [new Button(CatalogOrderFlowService::BTN_PICKUP, '🏪 Pickup')];
        if ($aiAgent->isDeliveryEnabled()) {
            $buttons[] = new Button(CatalogOrderFlowService::BTN_DELIVERY, '🚚 Delivery');
        }
        if ($aiAgent->isReservationEnabled()) {
            $buttons[] = new Button(CatalogOrderFlowService::BTN_RESERVASI, '📅 Reservasi');
        }

        try {
            $client->sendButton(
                $contact->wa_id,
                $this->truncate('Silakan pilih metode lanjutan:'),
                new ButtonAction($buttons),
                null,
                null,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send fulfillment buttons', [
                'error' => $e->getMessage(),
                'contact_wa_id' => $contact->wa_id,
            ]);
            $this->sendText($client, $contact->wa_id,
                'Silakan balas dengan "pickup", "delivery", atau "reservasi".');
        }
    }

    /**
     * Free-text delivery prompt — single-message format with all required
     * fields. Extracted so the drip "Lanjutkan" path can re-show it.
     */
    public function sendDeliveryInfoPrompt(WhatsAppAccount $account, WhatsAppContact $contact): void
    {
        $this->sendText(
            $this->client($account),
            $contact->wa_id,
            "🚚 *Delivery dipilih*\n\n"
            ."Mohon kirim *dalam satu pesan* informasi berikut:\n"
            ."• Nama penerima\n"
            ."• Nomor telepon\n"
            ."• Alamat lengkap (jalan, nomor, RT/RW)\n"
            ."• Patokan / catatan kurir (opsional)\n\n"
            .'Contoh: _Budi, 0812xxxx, Jl. Mawar no 12 RT 03/04, dekat warung Ibu Siti._',
        );
    }

    /**
     * After the customer's delivery info is parsed, show what we extracted
     * and ask Edit / Konfirmasi. Missing fields are flagged so the customer
     * can correct without us guessing.
     */
    public function sendDeliveryInfoSummary(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
    ): void {
        $client = $this->client($account);
        $parsed = $conversation->getDeliveryParsed() ?: [];
        $raw = $conversation->getDeliveryRawInfo() ?: '';

        $name = $parsed['name'] ?? null;
        $phone = $parsed['phone'] ?? null;
        $address = $parsed['address'] ?? null;
        $note = $parsed['note'] ?? null;

        $hasAny = $name || $phone || $address;

        if (! $hasAny) {
            $body = "📍 *Konfirmasi Informasi Delivery*\n\n"
                ."Maaf, kami tidak dapat membaca informasi dari pesan:\n\n"
                .'_'.$raw."_\n\n"
                ."Mohon kirim ulang dengan format:\n"
                .'_Budi, 0812xxxx, Jl. Mawar no 12 RT 03/04, jangan pedas_';
        } else {
            $missing = array_filter([
                ! $name ? 'nama' : null,
                ! $phone ? 'nomor telepon' : null,
                ! $address ? 'alamat' : null,
            ]);

            $lines = ['📍 *Konfirmasi Informasi Delivery*', ''];
            $lines[] = '👤 *Nama:* '.($name ?: '_belum terdeteksi_');
            $lines[] = '📱 *Telepon:* '.($phone ?: '_belum terdeteksi_');
            $lines[] = '📍 *Alamat:* '.($address ?: '_belum terdeteksi_');
            if ($note) {
                $lines[] = '📝 *Catatan:* '.$note;
            }
            $lines[] = '';
            $lines[] = ! empty($missing)
                ? '⚠️ Belum lengkap: '.implode(', ', $missing).'. Tap *Edit* untuk koreksi atau *Konfirmasi* kalau sudah benar.'
                : 'Apakah informasi di atas sudah benar?';
            $body = implode("\n", $lines);
        }

        $action = new ButtonAction([
            new Button(CatalogOrderFlowService::BTN_EDIT_DELIVERY, '✏️ Edit'),
            new Button(CatalogOrderFlowService::BTN_CONFIRM_DELIVERY, '✅ Konfirmasi'),
        ]);

        try {
            $client->sendButton($contact->wa_id, $this->truncate($body), $action, null, null);
        } catch (\Throwable $e) {
            Log::error('Failed to send delivery info summary', [
                'error' => $e->getMessage(),
                'contact_wa_id' => $contact->wa_id,
            ]);
            $this->sendText($client, $contact->wa_id,
                "Mohon balas 'edit' untuk mengubah atau 'konfirmasi' untuk lanjut.");
        }
    }

    /**
     * Final review screen before order create — items + subtotal + tax + ongkir +
     * delivery/pickup hint + Batal/Konfirmasi buttons.
     */
    public function sendOrderSummary(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
    ): void {
        $client = $this->client($account);
        $items = $conversation->getCatalogItems();
        $subtotal = $this->itemsSubtotal($items);
        $tax = round($subtotal * OrderService::DEFAULT_TAX_RATE, 2);
        $ongkir = $conversation->getOngkir();
        $deliveryType = $conversation->getDeliveryType() ?? Order::DELIVERY_TYPE_PICKUP;
        $total = $subtotal + $tax + $ongkir;

        $lines = ['🧾 *Ringkasan Pesanan*', ''];
        foreach ($items as $item) {
            $itemTotal = $item['item_price'] * $item['quantity'];
            $lines[] = sprintf(
                '• %s x%d — Rp %s',
                $item['product_name'] ?: $item['product_retailer_id'],
                $item['quantity'],
                number_format($itemTotal, 0, ',', '.'),
            );
        }

        $lines[] = '';
        $lines[] = 'Subtotal: Rp '.number_format($subtotal, 0, ',', '.');
        $lines[] = 'Pajak (11%): Rp '.number_format($tax, 0, ',', '.');
        if ($ongkir > 0) {
            $lines[] = 'Ongkir: Rp '.number_format($ongkir, 0, ',', '.');
        }
        $lines[] = '*Total: Rp '.number_format($total, 0, ',', '.').'*';

        if ($deliveryType === Order::DELIVERY_TYPE_DELIVERY && $conversation->getDeliveryAddress()) {
            $lines[] = '';
            $lines[] = '📍 Delivery ke:';
            $lines[] = $conversation->getDeliveryAddress();
        } elseif ($deliveryType === Order::DELIVERY_TYPE_PICKUP) {
            $lines[] = '';
            $lines[] = '🏪 Pickup di restoran';
        }

        $action = new ButtonAction([
            new Button(CatalogOrderFlowService::BTN_CANCEL_ORDER, '❌ Batal'),
            new Button(CatalogOrderFlowService::BTN_CONFIRM_ORDER, '✅ Konfirmasi'),
        ]);

        try {
            $client->sendButton($contact->wa_id, $this->truncate(implode("\n", $lines)), $action, null, null);
        } catch (\Throwable $e) {
            Log::error('Failed to send order summary', [
                'error' => $e->getMessage(),
                'contact_wa_id' => $contact->wa_id,
            ]);
            $this->sendText($client, $contact->wa_id,
                "Mohon balas 'konfirmasi' untuk lanjut atau 'batal' untuk membatalkan.");
        }
    }

    /**
     * Drip prompt: nudge a customer who sent off-context input mid-flow.
     * Different copy for AWAITING_PAYMENT (the order already exists) vs.
     * earlier states (the cart is mid-confirmation).
     */
    public function sendDripPrompt(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        AiAgentConversation $conversation,
    ): void {
        $client = $this->client($account);
        $state = $conversation->getFlowState();
        $step = $this->flowStepLabel($state);

        $isPayment = $state === CatalogOrderFlowService::STATE_AWAITING_PAYMENT;
        $body = $isPayment
            ? "Pesanan Anda sudah dibuat dan sedang *menunggu pembayaran*.\n\n"
                .'Mau lanjut ke pembayaran, atau batalkan pesanan?'
            : "Sepertinya pesan Anda di luar konteks pesanan yang sedang berjalan.\n\n"
                ."📌 Pesanan masih tersimpan di tahap: *{$step}*.\n\n"
                .'Mau lanjutkan pesanan ini?';

        try {
            $action = new ButtonAction([
                new Button(CatalogOrderFlowService::BTN_CONTINUE_FLOW, $isPayment ? '💳 Lanjutkan' : '✅ Lanjutkan'),
                new Button(CatalogOrderFlowService::BTN_CANCEL_ORDER, '❌ Batal'),
            ]);
            $client->sendButton($contact->wa_id, $this->truncate($body), $action, null, null);

            Log::info('Drip prompt sent', [
                'ai_agent_id' => $aiAgent->id,
                'contact_wa_id' => $contact->wa_id,
                'flow_state' => $state,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send drip prompt', [
                'contact_wa_id' => $contact->wa_id,
                'error' => $e->getMessage(),
            ]);
            $this->sendText($client, $contact->wa_id,
                'Mohon gunakan tombol pada pesan sebelumnya, atau kirim *batal* untuk membatalkan.');
        }
    }

    /**
     * Re-send the QRIS link for an in-progress payment. Used by the drip
     * "Lanjutkan" path. Auto-cleans expired transactions.
     */
    public function resendPaymentLink(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
    ): void {
        $client = $this->client($account);
        $qris = $conversation->getCurrentQrisTransaction();
        $order = $conversation->getCurrentOrder();

        if (! $qris || ! $order) {
            $conversation->clearFlowState();
            $this->sendText($client, $contact->wa_id,
                'Sesi pembayaran sudah berakhir. Ketik *menu* untuk memesan lagi.');

            return;
        }

        if ($qris->isExpired()) {
            $qris->markAsExpired();
            $qris->save();

            $conversation->clearFlowState();
            $conversation->clearPaymentContext();
            $conversation->clearPendingOrder();
            $conversation->clearCart();

            $orderContext = $conversation->order_context ?? [];
            unset($orderContext['last_qris_transaction_id']);
            $conversation->order_context = $orderContext;
            $conversation->save();

            $this->sendText($client, $contact->wa_id,
                "⏰ Kode pembayaran sudah kadaluarsa.\n\nPesanan otomatis dibatalkan.");

            return;
        }

        $total = 'Rp '.number_format($order->total, 0, ',', '.');
        $link = $qris->getShareableLink();
        $expiry = optional($qris->expires_at)->format('H:i');

        $msg = "💳 *Lanjutkan Pembayaran*\n\n"
            ."📋 No. Pesanan: {$order->order_number}\n"
            ."💰 Total: {$total}\n\n"
            ."Bayar melalui link berikut:\n{$link}";
        if ($expiry) {
            $msg .= "\n\n⏰ Berlaku hingga: {$expiry}";
        }

        $this->sendText($client, $contact->wa_id, $msg);
    }

    /**
     * Compact payment reminder used by the follow-up scheduler. Differs from
     * resendPaymentLink in that it's a one-off ping with "sisa N menit"
     * urgency rather than the full payment-resume context.
     */
    public function sendPaymentReminder(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
    ): bool {
        $qris = $conversation->getCurrentQrisTransaction();
        if (! $qris) {
            return false;
        }

        $client = $this->client($account);

        if ($qris->isExpired()) {
            $this->sendText($client, $contact->wa_id,
                'Link pembayaran kadaluarsa. Tap *Lihat Menu* untuk pesan ulang.');

            return true;
        }

        $minutesLeft = max(0, now()->diffInMinutes($qris->expires_at, false));
        $msg = "💳 Link QRIS masih aktif (sisa {$minutesLeft} menit).\n\n"
            ."{$qris->getShareableLink()}\n\n"
            ."Pesanan dibatalkan otomatis jika belum dibayar.";

        $this->sendText($client, $contact->wa_id, $msg);

        return true;
    }

    /**
     * Reservation hand-off — sends the merchant's reservation URL via text.
     * Catalog flow exits here; reservations are handled by a separate page.
     */
    public function sendReservationHandoff(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        ?AiAgentConversation $conversation = null,
    ): void {
        $client = $this->client($account);

        if (! $aiAgent->isReservationEnabled()) {
            $this->sendText($client, $contact->wa_id,
                'Maaf, fitur reservasi sedang tidak tersedia.');

            return;
        }

        $baseUrl = $aiAgent->getReservationFormUrl();
        if (! $baseUrl) {
            $this->sendText($client, $contact->wa_id,
                '📅 Untuk reservasi, silakan hubungi kami langsung. Tim kami akan membantu.');

            return;
        }

        // Build a short link that pre-fills the form with the buyer's cart items
        // and phone number so they don't have to re-enter what they already chose.
        $catalogItems = $conversation ? $conversation->getCatalogItems() : [];
        $phone        = $contact->wa_id ?? '';
        $url          = $this->buildReservationShortLink($baseUrl, $catalogItems, $phone);

        $this->sendText($client, $contact->wa_id,
            "📅 *Reservasi*\n\nSilakan isi form reservasi di link berikut:\n{$url}\n\n"
            .'Tim kami akan menghubungi Anda untuk konfirmasi.');
    }

    /**
     * Generate a short /r/{8-char} link that caches:
     *   - The reservation form base URL (with merchantName)
     *   - The buyer's cart items from the catalog (retailer_id, name, qty, price)
     *   - The buyer's phone number (wa_id)
     * When opened, the form auto-fills phone + product selection.
     */
    protected function buildReservationShortLink(string $baseUrl, array $catalogItems, string $phone): string
    {
        $payload = [
            'base_url' => $baseUrl,
            'phone'    => $phone,
            'items'    => $catalogItems,
        ];

        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (\Illuminate\Support\Facades\Cache::has("rsv_link:{$code}"));

        \Illuminate\Support\Facades\Cache::put("rsv_link:{$code}", $payload, now()->addHours(5));

        return rtrim(config('app.url'), '/').'/r/'.$code;
    }

    /**
     * Human-readable label for the current flow step. Used inside drip prompts
     * so the customer knows where they left off without seeing internal state
     * constants.
     */
    public function flowStepLabel(?string $state): string
    {
        return match ($state) {
            CatalogOrderFlowService::STATE_CONFIRMING_CART => 'konfirmasi pesanan',
            CatalogOrderFlowService::STATE_AWAITING_FULFILLMENT => 'memilih metode (Pickup / Delivery / Reservasi)',
            CatalogOrderFlowService::STATE_AWAITING_DELIVERY_INFO => 'mengisi informasi pengiriman',
            CatalogOrderFlowService::STATE_CONFIRMING_DELIVERY_INFO => 'konfirmasi informasi pengiriman',
            CatalogOrderFlowService::STATE_CONFIRMING_ORDER_SUMMARY => 'konfirmasi ringkasan pesanan',
            CatalogOrderFlowService::STATE_AWAITING_PAYMENT => 'menunggu pembayaran',
            default => 'pemesanan',
        };
    }

    /**
     * Sum item price × quantity. Mirrors the helper in OrderCreator;
     * duplicated intentionally so this renderer has no dependency on the
     * order-creation path.
     */
    public function itemsSubtotal(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) $item['item_price'] * (int) $item['quantity'];
        }

        return round($total, 2);
    }

    /**
     * Construct a per-merchant WhatsApp Cloud API client. Each WhatsAppAccount
     * carries its own phone_number_id + access_token, so we instantiate a
     * fresh client per send (cheap; the SDK is just a configured Guzzle).
     */
    public function client(WhatsAppAccount $account): WhatsAppCloudApi
    {
        return new WhatsAppCloudApi([
            'from_phone_number_id' => $account->phone_number_id,
            'access_token' => $account->access_token,
        ]);
    }

    /**
     * Plain-text send. Used as a fallback by every interactive sender so a
     * Meta API error never silences the bot completely.
     */
    public function sendText(WhatsAppCloudApi $client, string $to, string $message): void
    {
        try {
            $client->sendTextMessage($to, $message);
        } catch (\Throwable $e) {
            Log::error('Failed to send text message in catalog flow', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
