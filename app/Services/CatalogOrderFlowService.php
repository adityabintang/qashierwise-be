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
use Netflie\WhatsAppCloudApi\Message\MultiProduct\Action as MultiProductAction;
use Netflie\WhatsAppCloudApi\Message\MultiProduct\Row as MultiProductRow;
use Netflie\WhatsAppCloudApi\Message\MultiProduct\Section as MultiProductSection;
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
        protected CatalogService $catalogService,
    ) {}

    /** WhatsApp Multi-Product Message constraints. */
    private const MAX_SECTIONS = 10;

    private const MAX_ROWS_PER_SECTION = 30;

    private const MAX_TOTAL_ITEMS = 30;

    // ---- Button IDs ---------------------------------------------------------

    public const BTN_SHOW_MENU = 'show_menu';

    public const BTN_PICKUP = 'fulfill_pickup';

    public const BTN_DELIVERY = 'fulfill_delivery';

    public const BTN_RESERVASI = 'fulfill_reservasi';

    public const BTN_EDIT_DELIVERY = 'delivery_edit';

    public const BTN_CONFIRM_DELIVERY = 'delivery_confirm';

    public const BTN_CONFIRM_ORDER = 'order_confirm';

    public const BTN_CANCEL_ORDER = 'order_cancel';

    // Convergence point ("titik temu"): confirm the selected items (from catalog
    // OR POS) before choosing a fulfillment method.
    public const BTN_CONFIRM_CART = 'cart_confirm';

    // Drip CTA buttons (Sequence A — cart abandoned).
    public const BTN_RESUME_CHECKOUT = 'drip_resume_checkout';

    public const BTN_CLEAR_CART = 'drip_clear_cart';

    public const BTN_STOP_DRIP = 'drip_stop';

    // Drip-message CTA: resume the in-progress order after an off-context message.
    public const BTN_CONTINUE_FLOW = 'flow_continue';

    // ---- Flow states --------------------------------------------------------

    // Items selected (catalog or POS) — awaiting Konfirmasi/Batal before fulfillment.
    public const STATE_CONFIRMING_CART = 'confirming_cart';

    public const STATE_AWAITING_FULFILLMENT = 'awaiting_fulfillment_choice';

    public const STATE_AWAITING_DELIVERY_INFO = 'awaiting_delivery_info';

    public const STATE_CONFIRMING_DELIVERY_INFO = 'confirming_delivery_info';

    public const STATE_CONFIRMING_ORDER_SUMMARY = 'confirming_order_summary';

    // Set after a QRIS payment link is sent; lets the bot keep the order context
    // alive so off-context chatter triggers a "lanjutkan pembayaran" drip instead
    // of dropping back to the generic LLM.
    public const STATE_AWAITING_PAYMENT = 'awaiting_payment';

    // ---- Entry points -------------------------------------------------------

    /**
     * Deterministic reply for off-topic / unrecognised input outside the order
     * flow, attached to a single *Lihat Menu* button. Avoids dead-end text-only
     * fallbacks where the customer had to type "menu" manually.
     */
    public function sendFallbackWithMenuButton(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        string $body
    ): void {
        $client = $this->client($account);

        try {
            $action = new ButtonAction([
                new Button(self::BTN_SHOW_MENU, 'Lihat Menu'),
            ]);
            $client->sendButton(
                $contact->wa_id,
                $body,
                $action,
                null,
                $aiAgent->bot_name ?: null
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
     * Cancelled-order reply with a Lihat Menu button so the customer can
     * one-tap straight back to ordering without typing "menu".
     */
    public function sendCancelledReply(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent
    ): void {
        $client = $this->client($account);
        $body = '❌ Pesanan dibatalkan. Tap *Lihat Menu* untuk memesan lagi kapan saja.';

        try {
            $action = new ButtonAction([
                new Button(self::BTN_SHOW_MENU, 'Lihat Menu'),
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
     * Reply to a greeting ("Halo", "Hi", "Selamat pagi") with a quick-reply
     * button that opens the catalog directly. Saves the customer from having
     * to type "menu" and saves us an LLM call.
     *
     * The button reply ID `BTN_SHOW_MENU` is routed back to sendCatalog()
     * by WhatsAppWebhookController::handleInteractive.
     */
    public function sendGreetingWithMenuButton(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        string $greetingText
    ): void {
        $client = $this->client($account);

        try {
            $action = new ButtonAction([
                new Button(self::BTN_SHOW_MENU, 'Lihat Menu'),
            ]);

            $client->sendButton(
                $contact->wa_id,
                $greetingText,
                $action,
                null,                                  // header (optional)
                $aiAgent->bot_name ?: 'Pesan via WhatsApp' // footer
            );

            Log::info('Greeting with menu button sent', [
                'ai_agent_id' => $aiAgent->id,
                'contact_wa_id' => $contact->wa_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send greeting button, falling back to text', [
                'ai_agent_id' => $aiAgent->id,
                'contact_wa_id' => $contact->wa_id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to plain text so the customer still gets a reply.
            $this->sendText($client, $contact->wa_id, $greetingText);
        }
    }

    /**
     * Send the merchant's Meta product catalog to the customer.
     * Called when the AI detects VIEW_MENU / ORDER intent and a catalog is set.
     *
     * Uses Netflie's sendMultiProduct / sendSingleProduct — both accept an
     * explicit catalog_id so the WABA does NOT need a "primary" catalog linked
     * (avoiding Meta error 131009 "Check if a catalog is linked...").
     */
    public function sendCatalog(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent
    ): void {
        $client = $this->client($account);
        $catalogId = $aiAgent->catalog_id;
        $botName = $aiAgent->bot_name ?? 'Pesan via WhatsApp';

        if (! $catalogId) {
            Log::warning('sendCatalog called without catalog_id', [
                'ai_agent_id' => $aiAgent->id,
            ]);
            $this->sendText($client, $contact->wa_id,
                'Katalog menu belum dikonfigurasi. Silakan hubungi admin.');

            return;
        }

        // Fetch products from Meta. WhatsApp MPM caps at 30 items / 10 sections.
        $result = $this->catalogService->getCatalogProducts($account, $catalogId, self::MAX_TOTAL_ITEMS);

        if (! ($result['success'] ?? false)) {
            Log::error('Failed to fetch catalog products for MPM', [
                'ai_agent_id' => $aiAgent->id,
                'catalog_id' => $catalogId,
                'error' => $result['error'] ?? 'unknown',
            ]);
            $this->sendText($client, $contact->wa_id,
                'Maaf, katalog menu tidak dapat ditampilkan saat ini. Silakan coba lagi nanti.');

            return;
        }

        $products = $this->filterAvailableProducts($result['products'] ?? []);

        if (empty($products)) {
            $this->sendText($client, $contact->wa_id,
                'Maaf, saat ini belum ada menu yang tersedia. Silakan coba lagi nanti.');

            return;
        }

        try {
            $this->sendMultiProductMessage($client, $contact, $catalogId, $products, $botName);

            Log::info('Catalog sent to customer', [
                'ai_agent_id' => $aiAgent->id,
                'contact_wa_id' => $contact->wa_id,
                'catalog_id' => $catalogId,
                'product_count' => count($products),
            ]);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $reason = $this->classifyCatalogError($errorMessage);

            Log::error('Failed to send catalog message', [
                'ai_agent_id' => $aiAgent->id,
                'contact_wa_id' => $contact->wa_id,
                'catalog_id' => $catalogId,
                'product_count' => count($products),
                'reason' => $reason,
                'error' => $errorMessage,
            ]);

            // Smart fallback: when WhatsApp rejects the products (most often
            // because they're still in Meta's automated review queue), still
            // give the customer a usable text menu built from what we just
            // fetched — far better UX than a bare error message.
            if ($reason === 'products_not_approved' || $reason === 'products_invalid') {
                $this->sendTextMenuFallback($client, $contact, $aiAgent, $products);

                return;
            }

            $this->sendText($client, $contact->wa_id,
                'Maaf, katalog menu tidak dapat ditampilkan saat ini. Silakan coba lagi nanti.');
        }
    }

    /**
     * Map Meta error responses to a stable reason code so the fallback path
     * can decide whether to retry, show a text menu, or just apologize.
     */
    private function classifyCatalogError(string $rawError): string
    {
        $h = strtolower($rawError);

        // 131009 + "None of the products provided could be sent" → review pending / rejected
        if (str_contains($h, 'none of the products')) {
            return 'products_not_approved';
        }
        if (str_contains($h, 'check your catalog')) {
            return 'products_not_approved';
        }
        // 131009 + "Invalid catalog_id" → catalog not associated with WABA
        if (str_contains($h, 'invalid catalog_id')) {
            return 'catalog_not_linked';
        }
        // Bad SKU
        if (str_contains($h, 'invalid retailer_id')) {
            return 'products_invalid';
        }

        return 'unknown';
    }

    /**
     * Plain-text fallback menu, built from the product list we already fetched.
     * Used when the WhatsApp catalog UI can't render — keeps the customer in
     * the conversation instead of dead-ending them.
     */
    private function sendTextMenuFallback(
        WhatsAppCloudApi $client,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        array $products
    ): void {
        $botName = $aiAgent->bot_name ?: 'Kami';

        // Group by category for readability — same grouping logic as MPM sections.
        $grouped = [];
        foreach ($products as $p) {
            $cat = trim((string) ($p['category'] ?? '')) ?: 'Menu Lainnya';
            $grouped[$cat][] = $p;
        }

        $lines = ["*Menu {$botName}*", ''];
        foreach ($grouped as $catName => $items) {
            $lines[] = "_{$catName}_";
            foreach ($items as $p) {
                $name = $p['name'] ?? '(tanpa nama)';
                $price = $p['price'] ?? '';
                $lines[] = "• {$name}".($price !== '' ? " — {$price}" : '');
            }
            $lines[] = '';
        }
        $lines[] = 'Ketik nama menu yang ingin dipesan, atau kirim *batal* untuk berhenti.';

        $body = trim(implode("\n", $lines));

        $this->sendText($client, $contact->wa_id, $body);

        Log::info('Catalog fallback: sent text menu', [
            'ai_agent_id' => $aiAgent->id,
            'contact_wa_id' => $contact->wa_id,
            'product_count' => count($products),
        ]);
    }

    /**
     * Keep only products with a usable retailer_id and (where set) availability=in stock.
     * Meta MPM silently drops unknown SKUs; preventing them upfront keeps the message stable.
     */
    private function filterAvailableProducts(array $products): array
    {
        return array_values(array_filter($products, function ($p) {
            if (empty($p['retailer_id'])) {
                return false;
            }
            $avail = strtolower($p['availability'] ?? 'in stock');

            return in_array($avail, ['in stock', 'preorder', 'available for order'], true);
        }));
    }

    private function sendMultiProductMessage(
        WhatsAppCloudApi $client,
        WhatsAppContact $contact,
        string $catalogId,
        array $products,
        string $botName
    ): void {
        $sections = $this->buildMultiProductSections($products);

        $header = mb_substr('Menu '.$botName, 0, 60);
        $body = count($products) === 1
            ? 'Berikut menu kami. Tap "View" lalu "Add to cart" untuk memesan.'
            : 'Silakan pilih menu favorit Anda. Tap "View" untuk detail, '
              .'lalu "Add to cart" untuk menambahkan ke keranjang.';
        $footer = mb_substr($botName, 0, 60);

        // Netflie 2.x types catalog_id as int — Meta catalog IDs are numeric
        // strings; cast safely on 64-bit PHP. PHP_INT_SIZE is 8 on prod servers.
        $client->sendMultiProduct(
            $contact->wa_id,
            (int) $catalogId,
            new MultiProductAction($sections),
            $header,
            $body,
            $footer
        );
    }

    /**
     * Group products into Sections by `category` (Kategori Menu F&B).
     * Sections preserve insertion order; WhatsApp requires 1-10 sections,
     * each with 1-30 rows, total 30 items max.
     */
    private function buildMultiProductSections(array $products): array
    {
        $grouped = [];
        foreach ($products as $p) {
            $cat = trim((string) ($p['category'] ?? '')) ?: 'Menu Lainnya';
            // Section title hard-capped to 24 chars per WhatsApp spec.
            $cat = mb_substr($cat, 0, 24);
            $grouped[$cat][] = $p['retailer_id'];
        }

        $sections = [];
        $totalItems = 0;

        foreach ($grouped as $title => $skus) {
            if (count($sections) >= self::MAX_SECTIONS) {
                break;
            }

            $rows = [];
            foreach ($skus as $sku) {
                if ($totalItems >= self::MAX_TOTAL_ITEMS
                    || count($rows) >= self::MAX_ROWS_PER_SECTION) {
                    break;
                }
                $rows[] = new MultiProductRow($sku);
                $totalItems++;
            }

            if (! empty($rows)) {
                $sections[] = new MultiProductSection($title, $rows);
            }
        }

        // Fallback: single unnamed section if grouping somehow yielded nothing.
        if (empty($sections)) {
            $rows = [];
            foreach (array_slice($products, 0, self::MAX_TOTAL_ITEMS) as $p) {
                $rows[] = new MultiProductRow($p['retailer_id']);
            }
            $sections[] = new MultiProductSection('Menu', $rows);
        }

        return $sections;
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

        // Resolve product names — Meta's order webhook only ships retailer_ids,
        // so without this lookup the order summary would read "SKU-XXX x1".
        if ($aiAgent->catalog_id) {
            $skus = array_column($items, 'product_retailer_id');
            $nameMap = $this->catalogService->getProductNamesByRetailerIds(
                $account,
                (string) $aiAgent->catalog_id,
                $skus
            );
            foreach ($items as &$item) {
                $resolved = $nameMap[$item['product_retailer_id']] ?? null;
                if ($resolved) {
                    $item['product_name'] = $resolved;
                }
            }
            unset($item);
        }

        $conversation->setCatalogItems($items);
        $conversation->setFlowState(self::STATE_CONFIRMING_CART);

        Log::info('Catalog order received, prompting cart confirmation', [
            'ai_agent_id' => $aiAgent->id,
            'contact_wa_id' => $contact->wa_id,
            'item_count' => count($items),
            'subtotal' => $this->itemsSubtotal($items),
        ]);

        // Convergence point: confirm items before fulfillment selection.
        $this->sendCartConfirmation($account, $contact, $aiAgent, $items);
    }

    /**
     * Enter the unified order flow from a POS (non-catalog) cart. Converts the
     * LLM cart [{product_id, product_name, price, quantity}] into the catalog
     * item shape and shows the same cart-confirmation as the catalog flow.
     */
    public function startPosOrderFlow(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent
    ): bool {
        $cart = $conversation->getCart();
        if (empty($cart)) {
            return false;
        }

        $items = [];
        foreach ($cart as $c) {
            $items[] = [
                'product_retailer_id' => 'POS-'.($c['product_id'] ?? ''),
                'pos_product_id'      => $c['product_id'] ?? null,
                'product_name'        => $c['product_name'] ?? 'Produk',
                'quantity'            => (int) ($c['quantity'] ?? 1),
                'item_price'          => (float) ($c['price'] ?? 0),
                'currency'            => 'IDR',
            ];
        }

        $conversation->setCatalogItems($items);
        // LLM cart copied into the unified order items — clear it to avoid
        // double-counting if the customer orders again later.
        $conversation->clearCart();
        $conversation->setFlowState(self::STATE_CONFIRMING_CART);

        Log::info('POS order entering unified flow, prompting cart confirmation', [
            'ai_agent_id'   => $aiAgent->id,
            'contact_wa_id' => $contact->wa_id,
            'item_count'    => count($items),
            'subtotal'      => $this->itemsSubtotal($items),
        ]);

        $this->sendCartConfirmation($account, $contact, $aiAgent, $items);

        return true;
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

        // "Lihat Menu" button works regardless of flow state — it's the
        // greeting CTA shortcut. Equivalent to user typing "menu".
        if ($buttonId === self::BTN_SHOW_MENU) {
            if ($aiAgent->isCatalogActive()) {
                $this->sendCatalog($account, $contact, $aiAgent);

                return true;
            }

            // Catalog inactive -> route to the standard LLM/POS-products flow.
            app(AiAgentService::class)->processMessage($account, $contact, 'menu');

            return true;
        }

        // "Lanjutkan" (drip CTA) — resume whatever step the order is at.
        if ($buttonId === self::BTN_CONTINUE_FLOW) {
            if ($state === null) {
                $this->sendFallbackWithMenuButton($account, $contact, $aiAgent,
                    'Tidak ada pesanan yang sedang berjalan. Tap *Lihat Menu* untuk mulai memesan.');
            } else {
                $this->resumeFlow($account, $contact, $conversation, $aiAgent);
            }

            return true;
        }

        // Drip CTA: pause future drips for this contact for 30 days. Customer
        // can still order normally — only the automated reminders stop.
        if ($buttonId === self::BTN_STOP_DRIP) {
            $contact->forceFill(['drips_paused_until' => now()->addDays(30)])->save();
            app(\App\Services\AiAgent\Drip\DripScheduler::class)->onConversationResumed($conversation);
            $this->sendText(
                $this->client($account),
                $contact->wa_id,
                'Baik, pengingat otomatis dinonaktifkan untuk 30 hari ke depan. Kamu tetap bisa pesan kapan saja dengan ketik *menu*.'
            );

            return true;
        }

        // Drip CTA: resume checkout — funnel back into POS checkout flow.
        if ($buttonId === self::BTN_RESUME_CHECKOUT) {
            if (empty($conversation->getCart())) {
                $this->sendFallbackWithMenuButton($account, $contact, $aiAgent,
                    'Cart kamu kosong. Tap *Lihat Menu* untuk mulai memesan lagi.');

                return true;
            }
            $this->startPosOrderFlow($account, $contact, $conversation, $aiAgent);

            return true;
        }

        // Drip CTA: clear cart on user request from a Sequence A drip.
        if ($buttonId === self::BTN_CLEAR_CART) {
            $conversation->clearCart();
            app(\App\Services\AiAgent\Drip\DripScheduler::class)->onConversationResumed($conversation);
            $this->sendText(
                $this->client($account),
                $contact->wa_id,
                '🗑️ Cart dikosongkan. Tap *Lihat Menu* kalau ingin pesan lagi.'
            );

            return true;
        }

        // "Batal" button is universal — same behavior as typing "batal".
        if ($buttonId === self::BTN_CANCEL_ORDER) {
            // During payment the order already exists; don't claim it's
            // "dibatalkan" — the QRIS link stays valid until it expires.
            $wasAwaitingPayment = $state === self::STATE_AWAITING_PAYMENT;

            $conversation->clearCatalogItems();
            $conversation->clearDeliveryContext();
            $conversation->clearPendingOrder();
            $conversation->clearFlowState();

            if ($wasAwaitingPayment) {
                $this->sendFallbackWithMenuButton($account, $contact, $aiAgent,
                    'Baik. Link pembayaran tetap aktif sampai kadaluarsa — pesanan otomatis batal jika belum dibayar. '
                    .'Tap *Lihat Menu* kalau ingin memesan lagi.');
            } else {
                $this->sendCancelledReply($account, $contact, $aiAgent);
            }

            return true;
        }

        if ($state === self::STATE_CONFIRMING_CART) {
            if ($buttonId === self::BTN_CONFIRM_CART) {
                // Items confirmed -> proceed to fulfillment selection.
                $conversation->setFlowState(self::STATE_AWAITING_FULFILLMENT);
                $this->sendFulfillmentButtons($account, $contact, $aiAgent, $conversation->getCatalogItems());

                return true;
            }

            return false;
        }

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

        $clean = trim($text);

        // Escape hatch: customer types "batal/cancel/stop" while we're awaiting
        // their delivery details — don't save it as their delivery name.
        if (preg_match('/^\s*(batal|cancel|stop|berhenti|gajadi|gak\s+jadi|tidak\s+jadi)\.?\s*$/iu', $clean)) {
            Log::info('Catalog flow cancelled via text in delivery-info state', [
                'ai_agent_id' => $aiAgent->id,
                'contact_wa_id' => $contact->wa_id,
            ]);
            $conversation->clearFlowState();
            $conversation->clearDeliveryContext();
            $conversation->clearCatalogItems();
            $conversation->clearPendingOrder();
            $this->sendCancelledReply($account, $contact, $aiAgent);

            return true;
        }

        // Labeled input / partial edit: any "label = value" pairs
        // (e.g. "ubah nama = Budi", "nama = Budi, alamat = Jl X", multi-line,
        // bullets). Each labeled field is MERGED into the existing parsed info
        // so single-field edits keep the rest intact.
        $labeled = $this->extractLabeledFields($clean);
        if (! empty($labeled)) {
            $existing = $conversation->getDeliveryParsed() ?? [];
            foreach (['name', 'phone', 'address', 'note'] as $k) {
                if (! array_key_exists($k, $existing)) {
                    $existing[$k] = null;
                }
            }
            foreach ($labeled as $field => $value) {
                $existing[$field] = $value;
            }

            $conversation->setDeliveryParsed($existing);
            $conversation->setDeliveryRawInfo($this->composeRawFromParsed($existing));
            $conversation->setFlowState(self::STATE_CONFIRMING_DELIVERY_INFO);

            Log::info('Labeled delivery fields applied', [
                'fields' => array_keys($labeled),
            ]);

            $this->sendDeliveryInfoSummary($account, $contact, $conversation);

            return true;
        }

        // Otherwise: free-text full parse (e.g. "Budi, 0812xxxx, Jl. Mawar 12").
        $conversation->setDeliveryRawInfo($clean);
        $conversation->setDeliveryParsed($this->parseDeliveryInfo($clean));
        $conversation->setFlowState(self::STATE_CONFIRMING_DELIVERY_INFO);

        $this->sendDeliveryInfoSummary($account, $contact, $conversation);

        return true;
    }

    /** Field aliases → canonical key in delivery_parsed. */
    private const FIELD_ALIASES = [
        'nama' => 'name', 'name' => 'name',
        'telepon' => 'phone', 'telpon' => 'phone', 'telp' => 'phone',
        'phone' => 'phone', 'hp' => 'phone', 'no' => 'phone',
        'nomor' => 'phone', 'wa' => 'phone',
        'alamat' => 'address', 'address' => 'address',
        'catatan' => 'note', 'note' => 'note', 'keterangan' => 'note',
    ];

    /**
     * Extract every "label = value" pair from a message and return them mapped
     * to canonical fields. Handles:
     *   - optional verbs: "ubah/ganti/edit/update/set/isi nama = Budi"
     *   - separators: "=", ":"
     *   - multiple fields in one line/message, comma- or newline-separated
     *   - bullet/markdown noise the user may copy: "* ", "• ", "- ", "_"
     *   - values containing commas (address) — value runs until the next label
     *
     * Returns e.g. ['name' => 'Budi', 'phone' => '08123', 'address' => 'Jl X, Padang'].
     * Empty array when no labeled pairs found (caller falls back to heuristic parse).
     */
    private function extractLabeledFields(string $text): array
    {
        $aliasGroup = implode('|', array_keys(self::FIELD_ALIASES));

        // Value is lazy and ends right before the next label (with optional verb
        // + separator) or end-of-string. Leading bullet/markdown chars on the
        // next label are tolerated in the lookahead.
        $re = '/(?:ubah|ganti|edit|update|set|isi)?\s*\b('.$aliasGroup.')\b\s*[:=]\s*'
            .'(.+?)\s*'
            .'(?=(?:[\s,;\n•*_\-]+(?:ubah|ganti|edit|update|set|isi)?\s*\b(?:'.$aliasGroup.')\b\s*[:=])|$)/iu';

        if (! preg_match_all($re, $text, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $result = [];
        foreach ($matches as $m) {
            $alias = strtolower(trim($m[1]));
            $field = self::FIELD_ALIASES[$alias] ?? null;
            if ($field === null) {
                continue;
            }

            $value = trim($m[2], " \t.,-_*•\n");
            if ($value === '') {
                continue;
            }

            if ($field === 'phone') {
                if (preg_match('/(?:\+?62|0)[\s\-]?[2-9]\d(?:[\s\-]?\d){6,11}/', $value, $pm)) {
                    $value = $this->normalizePhone($pm[0]);
                } else {
                    continue; // not a usable phone — skip, keep previous
                }
            } elseif ($field === 'name') {
                $value = $this->titleCase($value);
            }

            $result[$field] = $value;
        }

        return $result;
    }

    /** Rebuild a human-readable raw string from parsed fields (for re-display/edit). */
    private function composeRawFromParsed(array $parsed): string
    {
        $parts = [];
        foreach (['name', 'phone', 'address', 'note'] as $k) {
            if (! empty($parsed[$k])) {
                $parts[] = $parsed[$k];
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Best-effort extraction of structured delivery fields from a free-text
     * customer message. Regex-based & deterministic; surfaces uncertain fields
     * as null so the UI can flag them rather than fabricate.
     *
     * Returns ['name' => ?string, 'phone' => ?string, 'address' => ?string, 'note' => ?string]
     */
    public function parseDeliveryInfo(string $text): array
    {
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Split on commas/semicolons only — splitting on "." breaks "Jl." abbreviations.
        $rawSegments = array_filter(array_map('trim',
            preg_split('/\s*[,;]\s*/u', $text) ?: []
        ), fn ($s) => $s !== '');

        // Second-pass split: for any segment that contains BOTH a geographic
        // term (city/province) and a note keyword separated by a sentence-end
        // period, split on the period so each part can be classified separately.
        // Avoids "sumatera barat. Untuk pesanannya jangan pedas" landing entirely
        // in notes when the first half is actually part of the address.
        $segments = [];
        foreach ($rawSegments as $seg) {
            if (str_contains($seg, '. ') && preg_match('/\b(jakarta|bandung|surabaya|medan|semarang|makassar|palembang|tangerang|depok|bekasi|bogor|padang|pekanbaru|denpasar|yogya|malang|solo|sumatera|sumatra|jawa|kalimantan|sulawesi|bali|aceh|riau|lampung|banten|papua)\b/i', $seg)
                && preg_match('/\b(jangan|tanpa|tolong|minta|catatan|note)\b/i', $seg)) {
                foreach (preg_split('/\.\s+/u', $seg) as $sub) {
                    $sub = trim($sub, ' .');
                    if ($sub !== '') {
                        $segments[] = $sub;
                    }
                }
            } else {
                $segments[] = $seg;
            }
        }

        $phone = null;
        $addressParts = [];
        $noteParts = [];
        $nameCandidates = [];

        $phoneRe = '/(?:\+?62|0)[\s\-]?[2-9]\d(?:[\s\-]?\d){6,11}/';
        $addressRe = '/\b(jl\.?|jalan|gang|gg\.?|komplek|kompleks|kel\.|kelurahan|kec\.|kecamatan|rt\b|rw\b|no\.?\s*\d|nomor\s+\d|alamat|blok|kampung|desa|dusun|perumahan)\b/i';
        // Geographic continuation: city/province segment after an address segment.
        $geoRe = '/\b(jakarta|bandung|surabaya|medan|semarang|makassar|palembang|tangerang|depok|bekasi|bogor|padang|pekanbaru|denpasar|yogya(?:karta)?|malang|solo|sumatera|sumatra|jawa|kalimantan|sulawesi|bali|aceh|riau|lampung|banten|papua)\b/i';
        $noteRe = '/\b(jangan|tanpa|tolong|minta|catatan|note|gak\s+pakai|tidak\s+pakai|extra|less|more|tambah)\b/i';
        $labelRe = '/^(nomor|no\.?|telp\.?|telepon|hp|wa|nama(?:nya)?(?:\s+saya)?|saya|alamat)$/iu';

        foreach ($segments as $seg) {
            // Phone — extract first valid match, strip from segment, keep remainder for further classification.
            if ($phone === null && preg_match($phoneRe, $seg, $m)) {
                $phone = $this->normalizePhone($m[0]);
                $remainder = trim(preg_replace($phoneRe, '', $seg, 1, $count) ?? '');
                // Strip any leading "nomor/telp/hp" label or trailing punctuation.
                $remainder = trim(preg_replace('/^\s*(nomor|no\.?|telp\.?|telepon|hp|wa|telp)\s*[:.-]?\s*/iu', '', $remainder), " \t,.-");
                if ($remainder === '' || preg_match($labelRe, $remainder)) {
                    continue;
                }
                $seg = $remainder;
            }

            if (preg_match($addressRe, $seg)) {
                $addressParts[] = $seg;

                continue;
            }
            // If we already have an address and this segment looks like a city/province, merge it.
            if (! empty($addressParts) && preg_match($geoRe, $seg) && ! preg_match($noteRe, $seg)) {
                $addressParts[] = $seg;

                continue;
            }
            if (preg_match($noteRe, $seg)) {
                $noteParts[] = $seg;

                continue;
            }

            // Default bucket: name candidate (segments without phone/address/note markers).
            $nameCandidates[] = $seg;
        }

        $name = null;
        if (! empty($nameCandidates)) {
            $first = array_shift($nameCandidates);
            // Strip leading "saya"/"nama saya"/"nama".
            $first = preg_replace('/^\s*(nama(?:nya)?(?:\s+saya)?|saya)\s+/iu', '', $first);
            $first = trim($first);
            // Reject "names" that are too long (likely a sentence misclassified)
            // or contain verbs/greetings.
            if ($first !== '' && mb_strlen($first) <= 60 && ! preg_match('/\b(halo|hai|hi|kasih|info|dulu|deh|pesan|order|tolong)\b/iu', $first)) {
                $name = $this->titleCase($first);
            } else {
                array_unshift($nameCandidates, $first); // put it back; treat as note
            }
            $noteParts = array_merge($nameCandidates, $noteParts);
        }

        return [
            'name' => $name !== null && $name !== '' ? $name : null,
            'phone' => $phone,
            'address' => ! empty($addressParts) ? trim(implode(', ', $addressParts), ' ,') : null,
            'note' => ! empty($noteParts) ? trim(implode('. ', $noteParts), ' .') : null,
        ];
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/[^\d+]/', '', $raw);
        // Normalize "+62..." or "62..." to "08..." for local Indonesian readability.
        if (str_starts_with($digits, '+62')) {
            $digits = '0'.substr($digits, 3);
        } elseif (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    private function titleCase(string $s): string
    {
        return mb_convert_case(mb_strtolower($s), MB_CASE_TITLE, 'UTF-8');
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

            $this->sendDeliveryInfoPrompt($account, $contact);

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
                "✏️ *Edit Informasi Delivery*\n\n"
                ."Ketik field yang ingin diperbaiki, contoh:\n"
                ."ubah nama = Budi\n"
                ."ubah telepon = 0812xxxx\n"
                ."ubah alamat = Jl. Mawar no 12\n"
                ."ubah catatan = jangan pedas\n\n"
                ."Bisa beberapa sekaligus, pisahkan dengan koma:\n"
                ."nama = Budi, telepon = 0812xxxx, alamat = Jl. Mawar 12\n\n"
                ."Atau kirim ulang lengkap dalam satu pesan:\n"
                .'Budi, 0812xxxx, Jl. Mawar no 12, jangan pedas'
            );

            return true;
        }

        if ($buttonId === self::BTN_CONFIRM_DELIVERY) {
            // Prefer parsed structured fields over the raw text.
            $parsed = $conversation->getDeliveryParsed() ?? [];
            $address = $parsed['address'] ?? $conversation->getDeliveryRawInfo() ?? '';
            $note = $parsed['note'] ?? null;

            $conversation->setDeliveryAddress($address);
            if ($note) {
                $conversation->setDeliveryNotes($note);
            }
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

        // Note: BTN_CANCEL_ORDER is handled at the top of handleButtonReply()
        // as a universal escape — no per-state branch needed here.

        if ($buttonId === self::BTN_CONFIRM_ORDER) {
            $this->createOrderAndRespond($account, $contact, $conversation, $aiAgent);

            return true;
        }

        return false;
    }

    // ---- Outbound messages --------------------------------------------------

    /**
     * Convergence point ("titik temu") for BOTH modes: show the selected items
     * + subtotal and ask the customer to confirm before choosing fulfillment.
     * Catalog (MPM submit) and POS (LLM cart + checkout) both land here.
     */
    public function sendCartConfirmation(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        array $items
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
                number_format($itemTotal, 0, ',', '.')
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
                new Button(self::BTN_CONFIRM_CART, '✅ Konfirmasi'),
                new Button(self::BTN_CANCEL_ORDER, '❌ Batal'),
            ]);
            $client->sendButton($contact->wa_id, $this->truncate($body, 1020), $action, null, null);
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
     * Fulfillment method picker (shown AFTER cart confirmation). Pickup always
     * available; delivery & reservasi follow the agent's feature flags.
     */
    protected function sendFulfillmentButtons(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        array $items
    ): void {
        $client = $this->client($account);

        $body = "Silakan pilih metode lanjutan:";

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
                'Silakan balas dengan "pickup", "delivery", atau "reservasi".');
        }
    }

    /**
     * Prompt asking the customer to send their delivery details in one message.
     * Extracted so the drip "Lanjutkan" path can re-show it.
     */
    protected function sendDeliveryInfoPrompt(
        WhatsAppAccount $account,
        WhatsAppContact $contact
    ): void {
        $this->sendText(
            $this->client($account),
            $contact->wa_id,
            "🚚 *Delivery dipilih*\n\n"
            ."Mohon kirim *dalam satu pesan* informasi berikut:\n"
            ."• Nama penerima\n"
            ."• Nomor telepon\n"
            ."• Alamat lengkap (jalan, nomor, RT/RW)\n"
            ."• Patokan / catatan kurir (opsional)\n\n"
            .'Contoh: _Budi, 0812xxxx, Jl. Mawar no 12 RT 03/04, dekat warung Ibu Siti._'
        );
    }

    // ---- Drip / off-context handling ----------------------------------------

    /**
     * Human-readable label for the current flow step (Bahasa Indonesia).
     * Used in the drip message so the customer knows where they left off.
     */
    protected function flowStepLabel(?string $state): string
    {
        return match ($state) {
            self::STATE_CONFIRMING_CART => 'konfirmasi pesanan',
            self::STATE_AWAITING_FULFILLMENT => 'memilih metode (Pickup / Delivery / Reservasi)',
            self::STATE_AWAITING_DELIVERY_INFO => 'mengisi informasi pengiriman',
            self::STATE_CONFIRMING_DELIVERY_INFO => 'konfirmasi informasi pengiriman',
            self::STATE_CONFIRMING_ORDER_SUMMARY => 'konfirmasi ringkasan pesanan',
            self::STATE_AWAITING_PAYMENT => 'menunggu pembayaran',
            default => 'pemesanan',
        };
    }

    /**
     * Drip message: customer sent something that doesn't fit the current flow
     * step. Nudge them with a contextual reminder + two buttons:
     *   [✅ Lanjutkan]  -> resume / re-show the current step
     *   [❌ Batal]      -> cancel the in-progress order
     *
     * Keeps the flow consistent and the UX friendly instead of a dead-end nag.
     */
    public function sendDripPrompt(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        AiAgentConversation $conversation
    ): void {
        $client = $this->client($account);
        $state = $conversation->getFlowState();
        $step = $this->flowStepLabel($state);

        $isPayment = $state === self::STATE_AWAITING_PAYMENT;
        $body = $isPayment
            ? "Pesanan Anda sudah dibuat dan sedang *menunggu pembayaran*.\n\n"
              .'Mau lanjut ke pembayaran, atau batalkan pesanan?'
            : "Sepertinya pesan Anda di luar konteks pesanan yang sedang berjalan.\n\n"
              ."📌 Pesanan masih tersimpan di tahap: *{$step}*.\n\n"
              .'Mau lanjutkan pesanan ini?';

        try {
            $action = new ButtonAction([
                new Button(self::BTN_CONTINUE_FLOW, $isPayment ? '💳 Lanjutkan' : '✅ Lanjutkan'),
                new Button(self::BTN_CANCEL_ORDER, '❌ Batal'),
            ]);
            $client->sendButton($contact->wa_id, $this->truncate($body, 1020), $action, null, null);

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
     * Re-show the prompt/buttons for whatever step the order is currently at.
     * Triggered by the drip "Lanjutkan" button.
     */
    public function resumeFlow(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent
    ): void {
        switch ($conversation->getFlowState()) {
            case self::STATE_CONFIRMING_CART:
                $this->sendCartConfirmation($account, $contact, $aiAgent, $conversation->getCatalogItems());
                break;
            case self::STATE_AWAITING_FULFILLMENT:
                $this->sendFulfillmentButtons($account, $contact, $aiAgent, $conversation->getCatalogItems());
                break;
            case self::STATE_AWAITING_DELIVERY_INFO:
                $this->sendDeliveryInfoPrompt($account, $contact);
                break;
            case self::STATE_CONFIRMING_DELIVERY_INFO:
                $this->sendDeliveryInfoSummary($account, $contact, $conversation);
                break;
            case self::STATE_CONFIRMING_ORDER_SUMMARY:
                $this->sendOrderSummary($account, $contact, $conversation, $aiAgent);
                break;
            case self::STATE_AWAITING_PAYMENT:
                $this->resendPaymentLink($account, $contact, $conversation);
                break;
            default:
                // No active flow — nudge back to the menu.
                $this->sendFallbackWithMenuButton($account, $contact, $aiAgent,
                    'Tidak ada pesanan yang sedang berjalan. Tap *Lihat Menu* untuk mulai memesan.');
        }
    }

    /**
     * Re-send the active QRIS payment link (drip "Lanjutkan" while awaiting payment).
     */
    protected function resendPaymentLink(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation
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

    protected function sendDeliveryInfoSummary(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation
    ): void {
        $client = $this->client($account);
        $parsed = $conversation->getDeliveryParsed() ?: [];
        $raw = $conversation->getDeliveryRawInfo() ?: '';

        $name = $parsed['name'] ?? null;
        $phone = $parsed['phone'] ?? null;
        $address = $parsed['address'] ?? null;
        $note = $parsed['note'] ?? null;

        // If parser missed all required fields, show raw as a single block + ask
        // user to re-send in the requested format.
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

            if (! empty($missing)) {
                $lines[] = '⚠️ Belum lengkap: '.implode(', ', $missing).'. '
                         .'Tap *Edit* untuk koreksi atau *Konfirmasi* kalau sudah benar.';
            } else {
                $lines[] = 'Apakah informasi di atas sudah benar?';
            }
            $body = implode("\n", $lines);
        }

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

        $lines = ['🧾 *Ringkasan Pesanan*', ''];
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
                '📅 Untuk reservasi, silakan hubungi kami langsung. Tim kami akan membantu.');
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

                // For delivery orders, prefer the recipient details the customer
                // typed (parsed) over their WhatsApp profile — the WA name/number
                // may not match the delivery recipient.
                $parsed = $conversation->getDeliveryParsed() ?? [];
                $customerName = $isDelivery && ! empty($parsed['name'])
                    ? $parsed['name']
                    : ($contact->name ?? 'WhatsApp Customer');
                $customerPhone = $isDelivery && ! empty($parsed['phone'])
                    ? $parsed['phone']
                    : $contact->wa_id;

                $order = Order::create([
                    'store_id' => $aiAgent->default_store_id,
                    'order_number' => $this->generateOrderNumber($aiAgent->default_store_id),
                    'status' => Order::STATUS_PENDING,
                    'source' => Order::SOURCE_WHATSAPP_AI,
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
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
                        // Link to a real POS product when the order originated
                        // from the POS flow; catalog items have no local product.
                        'product_id' => $item['pos_product_id'] ?? null,
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
                 .'Pesanan akan disiapkan setelah pembayaran berhasil. Terima kasih! 🙏';

            $this->sendText($client, $contact->wa_id, $msg);

            // Keep the order context alive in AWAITING_PAYMENT so off-context
            // chatter triggers a "lanjutkan pembayaran" drip. State + context are
            // cleared on payment success (AiAgentService::sendPaymentConfirmation).
            $conversation->setFlowState(self::STATE_AWAITING_PAYMENT);

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

    /**
     * Render and send a follow-up reminder for whatever state the conversation
     * is in. Dispatched by the follow-up scheduler when the customer has been
     * silent for the merchant-configured interval. Each branch reuses the
     * regular state prompt so the customer sees the same UI they would on a
     * fresh entry into the state.
     */
    public function sendStateReminder(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent
    ): bool {
        $state = $conversation->getFlowState();

        switch ($state) {
            case self::STATE_CONFIRMING_CART:
                $items = $conversation->getCatalogItems();
                if (empty($items)) {
                    $items = $conversation->getCart();
                }
                $this->sendCartConfirmation($account, $contact, $aiAgent, $items);

                return true;

            case self::STATE_AWAITING_FULFILLMENT:
                $items = $conversation->getCatalogItems() ?: $conversation->getCart();
                $this->sendFulfillmentButtons($account, $contact, $aiAgent, $items);

                return true;

            case self::STATE_AWAITING_DELIVERY_INFO:
                $this->sendDeliveryInfoPrompt($account, $contact);

                return true;

            case self::STATE_CONFIRMING_DELIVERY_INFO:
                $this->sendDeliveryInfoSummary($account, $contact, $conversation);

                return true;

            case self::STATE_CONFIRMING_ORDER_SUMMARY:
                $this->sendOrderSummary($account, $contact, $conversation, $aiAgent);

                return true;

            case self::STATE_AWAITING_PAYMENT:
                return $this->sendPaymentReminder($account, $contact, $conversation, $aiAgent);
        }

        return false;
    }

    /**
     * Lightweight payment-state reminder: re-send the QRIS link with the
     * remaining minutes until expiry. Falls back gracefully when the QRIS
     * transaction has been cleared from the conversation.
     */
    protected function sendPaymentReminder(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent
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
