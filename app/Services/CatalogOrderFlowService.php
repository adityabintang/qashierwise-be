<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\Button;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\ButtonAction;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

/**
 * Catalog-driven order flow.
 *
 * Replaces the AI-LLM "write a menu" flow with WhatsApp's native catalog UX:
 *   1. user shows menu intent -> sendCatalog()
 *   2. user submits cart from catalog -> webhook 'order' -> handleCatalogOrderReceived()
 *   3. user picks fulfillment button (pickup / delivery / reservasi)
 *   4. pickup     -> order summary -> confirm -> create order, pickup instruction
 *   5. delivery   -> free-text address -> summary -> edit/confirm loop -> order summary -> confirm -> QRIS
 *   6. reservasi  -> hand off to existing reservation flow URL
 */
class CatalogOrderFlowService
{
    public function __construct(
        protected QrisService $qrisService,
    ) {}

    // ---- Button IDs ---------------------------------------------------------

    public const BTN_PICKUP = 'fulfill_pickup';

    public const BTN_DELIVERY = 'fulfill_delivery';

    public const BTN_RESERVASI = 'fulfill_reservasi';

    public const BTN_EDIT_DELIVERY = 'delivery_edit';

    public const BTN_CONFIRM_DELIVERY = 'delivery_confirm';

    public const BTN_CONFIRM_ORDER = 'order_confirm';

    public const BTN_CANCEL_ORDER = 'order_cancel';

    // ---- Flow states --------------------------------------------------------

    public const STATE_AWAITING_FULFILLMENT = 'awaiting_fulfillment_choice';

    public const STATE_AWAITING_DELIVERY_INFO = 'awaiting_delivery_info';

    public const STATE_CONFIRMING_DELIVERY_INFO = 'confirming_delivery_info';

    public const STATE_CONFIRMING_ORDER_SUMMARY = 'confirming_order_summary';

    // ---- Entry points -------------------------------------------------------

    /**
     * Send the merchant's Meta product catalog to the customer.
     * Called when the AI detects VIEW_MENU / ORDER intent and a catalog is set.
     */
    public function sendCatalog(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent
    ): void {
        $client = $this->client($account);

        $body = 'Halo! Silakan pilih menu favorit Anda dari katalog kami. '
              .'Tambahkan ke keranjang lalu kirim pesanan saat selesai.';
        $footer = $aiAgent->bot_name ?? 'Pesan via WhatsApp';

        try {
            $client->sendCatalog($contact->wa_id, $body, $footer);

            Log::info('Catalog sent to customer', [
                'ai_agent_id' => $aiAgent->id,
                'contact_wa_id' => $contact->wa_id,
                'catalog_id' => $aiAgent->catalog_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send catalog message', [
                'ai_agent_id' => $aiAgent->id,
                'contact_wa_id' => $contact->wa_id,
                'error' => $e->getMessage(),
            ]);

            $this->sendText($client, $contact->wa_id,
                'Maaf, katalog menu tidak dapat ditampilkan saat ini. Silakan coba lagi nanti.');
        }
    }

    /**
     * Handle the `order` webhook event sent by WhatsApp after the customer
     * submits their cart from the catalog UI.
     *
     * @param  array  $orderPayload  message['order'] payload
     */
    public function handleCatalogOrderReceived(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        array $orderPayload
    ): void {
        $items = $this->extractItems($orderPayload);

        if (empty($items)) {
            $this->sendText(
                $this->client($account),
                $contact->wa_id,
                'Maaf, keranjang Anda kosong. Silakan pilih menu dari katalog terlebih dahulu.'
            );

            return;
        }

        $conversation->setCatalogItems($items);
        $conversation->setFlowState(self::STATE_AWAITING_FULFILLMENT);

        Log::info('Catalog order received, prompting fulfillment choice', [
            'ai_agent_id' => $aiAgent->id,
            'contact_wa_id' => $contact->wa_id,
            'item_count' => count($items),
            'subtotal' => $this->itemsSubtotal($items),
        ]);

        $this->sendFulfillmentButtons($account, $contact, $aiAgent, $items);
    }

    /**
     * Route a button reply based on the conversation's current flow_state.
     * Returns true if the button was consumed by the catalog flow.
     */
    public function handleButtonReply(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $buttonId
    ): bool {
        $state = $conversation->getFlowState();

        Log::info('CatalogOrderFlow: handling button reply', [
            'flow_state' => $state,
            'button_id' => $buttonId,
            'conversation_id' => $conversation->id,
        ]);

        if ($state === self::STATE_AWAITING_FULFILLMENT) {
            return $this->handleFulfillmentChoice($account, $contact, $conversation, $aiAgent, $buttonId);
        }

        if ($state === self::STATE_CONFIRMING_DELIVERY_INFO) {
            return $this->handleDeliveryInfoConfirmation($account, $contact, $conversation, $aiAgent, $buttonId);
        }

        if ($state === self::STATE_CONFIRMING_ORDER_SUMMARY) {
            return $this->handleOrderSummaryConfirmation($account, $contact, $conversation, $aiAgent, $buttonId);
        }

        return false;
    }

    /**
     * Handle free-text input while user is supplying delivery info.
     * Returns true if the text was consumed by the catalog flow.
     */
    public function handleDeliveryInfoText(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $text
    ): bool {
        if ($conversation->getFlowState() !== self::STATE_AWAITING_DELIVERY_INFO) {
            return false;
        }

        $conversation->setDeliveryRawInfo(trim($text));
        $conversation->setFlowState(self::STATE_CONFIRMING_DELIVERY_INFO);

        $this->sendDeliveryInfoSummary($account, $contact, $conversation);

        return true;
    }

    // ---- Internal button branches -------------------------------------------

    protected function handleFulfillmentChoice(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $buttonId
    ): bool {
        $client = $this->client($account);

        if ($buttonId === self::BTN_PICKUP) {
            $conversation->setDeliveryType(Order::DELIVERY_TYPE_PICKUP);
            $conversation->setOngkir(0);
            $conversation->setFlowState(self::STATE_CONFIRMING_ORDER_SUMMARY);
            $this->sendOrderSummary($account, $contact, $conversation, $aiAgent);

            return true;
        }

        if ($buttonId === self::BTN_DELIVERY) {
            if (! $aiAgent->isDeliveryEnabled()) {
                $this->sendText($client, $contact->wa_id,
                    'Maaf, layanan delivery sedang tidak tersedia. Silakan pilih Pickup atau Reservasi.');

                return true;
            }

            $conversation->setDeliveryType(Order::DELIVERY_TYPE_DELIVERY);
            $conversation->setOngkir((float) $aiAgent->default_ongkir);
            $conversation->setFlowState(self::STATE_AWAITING_DELIVERY_INFO);

            $this->sendText(
                $client,
                $contact->wa_id,
                "🚚 *Delivery dipilih*\n\n"
                ."Mohon kirim *dalam satu pesan* informasi berikut:\n"
                ."• Nama penerima\n"
                ."• Nomor telepon\n"
                ."• Alamat lengkap (jalan, nomor, RT/RW)\n"
                ."• Patokan / catatan kurir (opsional)\n\n"
                ."Contoh: _Budi, 0812xxxx, Jl. Mawar no 12 RT 03/04, dekat warung Ibu Siti._"
            );

            return true;
        }

        if ($buttonId === self::BTN_RESERVASI) {
            $this->triggerReservation($account, $contact, $conversation, $aiAgent);

            return true;
        }

        return false;
    }

    protected function handleDeliveryInfoConfirmation(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $buttonId
    ): bool {
        $client = $this->client($account);

        if ($buttonId === self::BTN_EDIT_DELIVERY) {
            $conversation->setFlowState(self::STATE_AWAITING_DELIVERY_INFO);

            $this->sendText(
                $client,
                $contact->wa_id,
                'Silakan kirim ulang informasi delivery Anda dalam satu pesan.'
            );

            return true;
        }

        if ($buttonId === self::BTN_CONFIRM_DELIVERY) {
            $conversation->setDeliveryAddress($conversation->getDeliveryRawInfo() ?? '');
            $conversation->setFlowState(self::STATE_CONFIRMING_ORDER_SUMMARY);
            $this->sendOrderSummary($account, $contact, $conversation, $aiAgent);

            return true;
        }

        return false;
    }

    protected function handleOrderSummaryConfirmation(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $buttonId
    ): bool {
        $client = $this->client($account);

        if ($buttonId === self::BTN_CANCEL_ORDER) {
            $conversation->clearCatalogItems();
            $conversation->clearDeliveryContext();
            $conversation->clearFlowState();

            $this->sendText(
                $client,
                $contact->wa_id,
                '❌ Pesanan dibatalkan. Ketik *menu* kapan saja untuk memesan lagi.'
            );

            return true;
        }

        if ($buttonId === self::BTN_CONFIRM_ORDER) {
            $this->createOrderAndRespond($account, $contact, $conversation, $aiAgent);

            return true;
        }

        return false;
    }

    // ---- Outbound messages --------------------------------------------------

    protected function sendFulfillmentButtons(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        array $items
    ): void {
        $client = $this->client($account);

        $itemsLines = [];
        foreach ($items as $idx => $item) {
            $line = sprintf(
                '%d. %s x%d',
                $idx + 1,
                $item['product_name'] ?: $item['product_retailer_id'],
                $item['quantity']
            );
            $itemsLines[] = $line;
        }

        $subtotal = $this->itemsSubtotal($items);
        $itemsBlock = implode("\n", array_slice($itemsLines, 0, 8));
        if (count($itemsLines) > 8) {
            $itemsBlock .= "\n... dan ".(count($itemsLines) - 8).' item lainnya';
        }

        $body = "🛒 *Pesanan Diterima*\n\n"
              .$itemsBlock
              ."\n\nSubtotal: Rp ".number_format($subtotal, 0, ',', '.')
              ."\n\nPilih metode lanjutan:";

        // sendButton supports max 3 buttons; we always show pickup. Delivery and
        // reservasi follow the agent's feature flags.
        $buttons = [new Button(self::BTN_PICKUP, '🏪 Pickup')];

        if ($aiAgent->isDeliveryEnabled()) {
            $buttons[] = new Button(self::BTN_DELIVERY, '🚚 Delivery');
        }
        if ($aiAgent->isReservationEnabled()) {
            $buttons[] = new Button(self::BTN_RESERVASI, '📅 Reservasi');
        }

        $action = new ButtonAction($buttons);

        try {
            $client->sendButton($contact->wa_id, $this->truncate($body, 1020), $action, null, null);
        } catch (\Throwable $e) {
            Log::error('Failed to send fulfillment buttons', [
                'error' => $e->getMessage(),
                'contact_wa_id' => $contact->wa_id,
            ]);
            $this->sendText($client, $contact->wa_id,
                'Pesanan Anda diterima. Silakan balas dengan "pickup", "delivery", atau "reservasi".');
        }
    }

    protected function sendDeliveryInfoSummary(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation
    ): void {
        $client = $this->client($account);
        $info = $conversation->getDeliveryRawInfo() ?: '(kosong)';

        $body = "📍 *Konfirmasi Informasi Delivery*\n\n"
              .$info
              ."\n\nApakah informasi di atas sudah benar?";

        $action = new ButtonAction([
            new Button(self::BTN_EDIT_DELIVERY, '✏️ Edit'),
            new Button(self::BTN_CONFIRM_DELIVERY, '✅ Konfirmasi'),
        ]);

        try {
            $client->sendButton($contact->wa_id, $this->truncate($body, 1020), $action, null, null);
        } catch (\Throwable $e) {
            Log::error('Failed to send delivery info summary', [
                'error' => $e->getMessage(),
                'contact_wa_id' => $contact->wa_id,
            ]);
            $this->sendText($client, $contact->wa_id,
                "Mohon balas 'edit' untuk mengubah atau 'konfirmasi' untuk lanjut.");
        }
    }

    protected function sendOrderSummary(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent
    ): void {
        $client = $this->client($account);
        $items = $conversation->getCatalogItems();
        $subtotal = $this->itemsSubtotal($items);
        $tax = round($subtotal * OrderService::DEFAULT_TAX_RATE, 2);
        $ongkir = $conversation->getOngkir();
        $deliveryType = $conversation->getDeliveryType() ?? Order::DELIVERY_TYPE_PICKUP;
        $total = $subtotal + $tax + $ongkir;

        $lines = ["🧾 *Ringkasan Pesanan*", ''];
        foreach ($items as $item) {
            $itemTotal = $item['item_price'] * $item['quantity'];
            $lines[] = sprintf(
                '• %s x%d — Rp %s',
                $item['product_name'] ?: $item['product_retailer_id'],
                $item['quantity'],
                number_format($itemTotal, 0, ',', '.')
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

        $body = implode("\n", $lines);

        $action = new ButtonAction([
            new Button(self::BTN_CANCEL_ORDER, '❌ Batal'),
            new Button(self::BTN_CONFIRM_ORDER, '✅ Konfirmasi'),
        ]);

        try {
            $client->sendButton($contact->wa_id, $this->truncate($body, 1020), $action, null, null);
        } catch (\Throwable $e) {
            Log::error('Failed to send order summary', [
                'error' => $e->getMessage(),
                'contact_wa_id' => $contact->wa_id,
            ]);
            $this->sendText($client, $contact->wa_id,
                "Mohon balas 'konfirmasi' untuk lanjut atau 'batal' untuk membatalkan.");
        }
    }

    protected function triggerReservation(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent
    ): void {
        $client = $this->client($account);

        // Reservation flow consumes the catalog cart; we leave catalog items in
        // place in case the user wants to use them later, but we exit our flow.
        $conversation->clearFlowState();

        if (! $aiAgent->isReservationEnabled()) {
            $this->sendText($client, $contact->wa_id,
                'Maaf, fitur reservasi sedang tidak tersedia.');

            return;
        }

        $url = $aiAgent->getReservationFormUrl();
        if ($url) {
            $this->sendText($client, $contact->wa_id,
                "📅 *Reservasi*\n\nSilakan isi form reservasi di link berikut:\n{$url}\n\n"
                .'Tim kami akan menghubungi Anda untuk konfirmasi.');
        } else {
            $this->sendText($client, $contact->wa_id,
                "📅 Untuk reservasi, silakan hubungi kami langsung. Tim kami akan membantu.");
        }
    }

    // ---- Order creation -----------------------------------------------------

    protected function createOrderAndRespond(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent
    ): void {
        $client = $this->client($account);

        if (! $aiAgent->default_store_id) {
            $this->sendText($client, $contact->wa_id,
                'Maaf, toko default belum dikonfigurasi. Hubungi penjual.');

            return;
        }

        $items = $conversation->getCatalogItems();
        if (empty($items)) {
            $this->sendText($client, $contact->wa_id,
                'Maaf, keranjang Anda kosong. Silakan mulai pemesanan dari awal.');
            $conversation->clearFlowState();

            return;
        }

        $deliveryType = $conversation->getDeliveryType() ?? Order::DELIVERY_TYPE_PICKUP;
        $isDelivery = $deliveryType === Order::DELIVERY_TYPE_DELIVERY;

        try {
            $order = DB::transaction(function () use ($conversation, $aiAgent, $contact, $items, $deliveryType, $isDelivery) {
                $subtotal = $this->itemsSubtotal($items);
                $tax = round($subtotal * OrderService::DEFAULT_TAX_RATE, 2);
                $ongkir = $isDelivery ? (float) $conversation->getOngkir() : 0;
                $total = $subtotal + $tax + $ongkir;

                $order = Order::create([
                    'store_id' => $aiAgent->default_store_id,
                    'order_number' => $this->generateOrderNumber($aiAgent->default_store_id),
                    'status' => Order::STATUS_PENDING,
                    'source' => Order::SOURCE_WHATSAPP_AI,
                    'customer_name' => $contact->name ?? 'WhatsApp Customer',
                    'customer_phone' => $contact->wa_id,
                    'delivery_type' => $deliveryType,
                    'alamat' => $isDelivery ? $conversation->getDeliveryAddress() : null,
                    'ongkir' => $ongkir,
                    'catatan' => $conversation->getDeliveryNotes(),
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'discount_amount' => 0,
                    'total' => $total,
                ]);

                foreach ($items as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => null,
                        'product_name' => $item['product_name'] ?: $item['product_retailer_id'],
                        'product_retailer_id' => $item['product_retailer_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['item_price'],
                        'subtotal' => $item['item_price'] * $item['quantity'],
                    ]);
                }

                return $order->fresh();
            });

            $conversation->setCurrentOrder($order->id);

            Log::info('Catalog order created', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $order->total,
                'delivery_type' => $deliveryType,
            ]);

            if ($isDelivery) {
                $this->respondDeliveryWithQris($account, $contact, $conversation, $aiAgent, $order);
            } else {
                $this->respondPickup($account, $contact, $conversation, $order);
            }

        } catch (\Throwable $e) {
            Log::error('Failed to create catalog order', [
                'error' => $e->getMessage(),
                'contact_wa_id' => $contact->wa_id,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->sendText($client, $contact->wa_id,
                'Maaf, terjadi kesalahan saat membuat pesanan. Silakan coba lagi nanti.');
        }
    }

    protected function respondPickup(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        Order $order
    ): void {
        $formattedTotal = 'Rp '.number_format($order->total, 0, ',', '.');

        $msg = "✅ *Pesanan Berhasil Dibuat!*\n\n"
             ."📋 No. Pesanan: {$order->order_number}\n"
             ."💰 Total: {$formattedTotal}\n\n"
             ."🏪 Silakan ambil pesanan Anda di restoran.\n"
             ."Tunjukkan nomor pesanan di atas ke kasir saat pengambilan.\n\n"
             .'Terima kasih! 🙏';

        $this->sendText($this->client($account), $contact->wa_id, $msg);

        // Pickup flow ends here; reset state so customer can place new orders.
        $conversation->clearCatalogItems();
        $conversation->clearDeliveryContext();
        $conversation->clearFlowState();
    }

    protected function respondDeliveryWithQris(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        Order $order
    ): void {
        $client = $this->client($account);

        if (! $aiAgent->isQrisEnabled()) {
            $this->sendText($client, $contact->wa_id,
                "✅ Pesanan dibuat: {$order->order_number}\nTotal: Rp ".number_format($order->total, 0, ',', '.').
                "\n\nMohon hubungi penjual untuk metode pembayaran.");
            $conversation->clearCatalogItems();
            $conversation->clearDeliveryContext();
            $conversation->clearFlowState();

            return;
        }

        $subMerchant = $aiAgent->getSubMerchant();
        if (! $subMerchant) {
            $this->sendText($client, $contact->wa_id,
                'Maaf, pembayaran QRIS belum tersedia. Hubungi penjual untuk metode lain.');

            return;
        }

        try {
            $qrisTransaction = $this->qrisService->generateQris($subMerchant, (float) $order->total, [
                'description' => "Pesanan #{$order->order_number}",
            ]);
            $qrisTransaction->linked_order_id = $order->id;
            $qrisTransaction->save();

            Payment::create([
                'order_id' => $order->id,
                'qris_transaction_id' => $qrisTransaction->id,
                'method' => Payment::METHOD_QRIS,
                'amount' => $order->total,
                'status' => Payment::STATUS_PENDING,
            ]);

            $conversation->setCurrentQrisTransaction($qrisTransaction->id);

            $expiryTime = $qrisTransaction->expires_at->format('H:i');
            $formattedTotal = 'Rp '.number_format($order->total, 0, ',', '.');
            $shareableLink = $qrisTransaction->getShareableLink();

            $msg = "✅ *Pesanan Berhasil Dibuat!*\n\n"
                 ."📋 No. Pesanan: {$order->order_number}\n"
                 ."💰 Total: {$formattedTotal}\n\n"
                 ."💳 Silakan bayar melalui link berikut:\n{$shareableLink}\n\n"
                 ."⏰ Berlaku hingga: {$expiryTime}\n\n"
                 ."Pesanan akan disiapkan setelah pembayaran berhasil. Terima kasih! 🙏";

            $this->sendText($client, $contact->wa_id, $msg);

            // Flow ends; catalog items and delivery context are no longer needed.
            $conversation->clearCatalogItems();
            $conversation->clearDeliveryContext();
            $conversation->clearFlowState();

        } catch (\Throwable $e) {
            Log::error('QRIS generation failed in catalog flow', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);
            $this->sendText($client, $contact->wa_id,
                "✅ Pesanan dibuat: {$order->order_number}\n".
                'Namun pembuatan QRIS gagal. Silakan hubungi penjual.');
        }
    }

    // ---- Helpers ------------------------------------------------------------

    protected function client(WhatsAppAccount $account): WhatsAppCloudApi
    {
        return new WhatsAppCloudApi([
            'from_phone_number_id' => $account->phone_number_id,
            'access_token' => $account->access_token,
        ]);
    }

    protected function sendText(WhatsAppCloudApi $client, string $to, string $message): void
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

    protected function extractItems(array $orderPayload): array
    {
        $rawItems = $orderPayload['product_items'] ?? [];
        $items = [];

        foreach ($rawItems as $raw) {
            $items[] = [
                'product_retailer_id' => (string) ($raw['product_retailer_id'] ?? ''),
                'product_name' => (string) ($raw['name'] ?? $raw['product_retailer_id'] ?? 'Produk'),
                'quantity' => (int) ($raw['quantity'] ?? 1),
                'item_price' => (float) ($raw['item_price'] ?? 0),
                'currency' => (string) ($raw['currency'] ?? 'IDR'),
            ];
        }

        return $items;
    }

    protected function itemsSubtotal(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) $item['item_price'] * (int) $item['quantity'];
        }

        return round($total, 2);
    }

    protected function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 1).'…';
    }

    protected function generateOrderNumber(int $storeId): string
    {
        $date = now()->format('Ymd');
        $prefix = "ORD-{$storeId}-{$date}";
        $count = Order::where('store_id', $storeId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        do {
            $count++;
            $sequence = str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            $orderNumber = "{$prefix}-{$sequence}";
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
