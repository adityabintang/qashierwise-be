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
use Netflie\WhatsAppCloudApi\Message\OptionsList\Action as OptionsListAction;
use Netflie\WhatsAppCloudApi\Message\OptionsList\Row as OptionsListRow;
use Netflie\WhatsAppCloudApi\Message\OptionsList\Section as OptionsListSection;
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
        protected \App\Services\AiAgent\Catalog\DeliveryInfoParser $deliveryParser,
        protected \App\Services\AiAgent\Catalog\OrderCreator $orderCreator,
        protected \App\Services\AiAgent\Catalog\CatalogMessageRenderer $renderer,
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

    // Category picker (list_reply) row IDs.
    public const CAT_PREFIX = 'cat:';

    public const CAT_ALL = 'cat:_all';

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

    // ---- Renderer facade methods --------------------------------------------
    //
    // These thin delegators preserve the existing public API while the heavy
    // rendering logic lives in CatalogMessageRenderer. External callers
    // (IntentRouter, AiAgentService, follow-up job) keep working unchanged.

    public function sendGreetingWithMenuButton(WhatsAppAccount $account, WhatsAppContact $contact, AiAgent $aiAgent, string $greetingText): void
    {
        $this->renderer->sendGreetingWithMenuButton($account, $contact, $aiAgent, $greetingText);
    }

    public function sendFallbackWithMenuButton(WhatsAppAccount $account, WhatsAppContact $contact, AiAgent $aiAgent, string $body): void
    {
        $this->renderer->sendFallbackWithMenuButton($account, $contact, $aiAgent, $body);
    }

    public function sendCancelledReply(WhatsAppAccount $account, WhatsAppContact $contact, AiAgent $aiAgent): void
    {
        $this->renderer->sendCancelledReply($account, $contact, $aiAgent);
    }

    public function sendDripPrompt(WhatsAppAccount $account, WhatsAppContact $contact, AiAgent $aiAgent, AiAgentConversation $conversation): void
    {
        $this->renderer->sendDripPrompt($account, $contact, $aiAgent, $conversation);
    }

    public function sendCartConfirmation(WhatsAppAccount $account, WhatsAppContact $contact, AiAgent $aiAgent, array $items): void
    {
        $this->renderer->sendCartConfirmation($account, $contact, $aiAgent, $items);
    }

    // ---- Entry points -------------------------------------------------------

    /**
     * Send the merchant's Meta product catalog to the customer.
     * Called when the AI detects VIEW_MENU / ORDER intent and a catalog is set.
     *
     * Uses Netflie's sendMultiProduct / sendSingleProduct — both accept an
     * explicit catalog_id so the WABA does NOT need a "primary" catalog linked
     * (avoiding Meta error 131009 "Check if a catalog is linked...").
     */
    /**
     * Show a category picker before sending the full catalog. If the catalog
     * has 0 or 1 distinct categories, this is a no-op upgrade — fall through
     * to direct sendCatalog so the customer doesn't wait an extra round-trip.
     */
    public function sendCategoryPicker(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent
    ): void {
        $client = $this->client($account);
        $catalogId = $aiAgent->catalog_id;
        $botName = $aiAgent->bot_name ?? 'Pesan via WhatsApp';

        if (! $catalogId) {
            $this->sendText($client, $contact->wa_id,
                'Katalog menu belum dikonfigurasi. Silakan hubungi admin.');

            return;
        }

        $result = $this->catalogService->getCatalogProducts($account, $catalogId, self::MAX_TOTAL_ITEMS);
        if (! ($result['success'] ?? false)) {
            $this->sendCatalog($account, $contact, $aiAgent);
            return;
        }

        $products = $this->filterAvailableProducts($result['products'] ?? []);
        $categories = $this->extractCategories($products);

        // 0-1 categories → no picker needed, send the catalog directly.
        if (count($categories) < 2) {
            $this->sendCatalog($account, $contact, $aiAgent);
            return;
        }

        // Build list rows: one per category + "Semua Kategori" at the end.
        // WhatsApp list-message limit: 10 rows per section. Cap categories at 9
        // so "Semua Kategori" always fits.
        $categories = array_slice($categories, 0, 9);

        $rows = [];
        foreach ($categories as $cat) {
            $rows[] = new OptionsListRow(
                self::CAT_PREFIX . rawurlencode($cat),
                mb_substr($cat, 0, 24),
                null
            );
        }
        $rows[] = new OptionsListRow(self::CAT_ALL, 'Semua Kategori', null);

        $section = new OptionsListSection('Kategori', $rows);
        $action  = new OptionsListAction('Pilih Kategori', [$section]);

        try {
            $client->sendList(
                $contact->wa_id,
                mb_substr('Menu ' . $botName, 0, 60),
                'Pilih kategori menu yang ingin dilihat:',
                mb_substr($botName, 0, 60),
                $action
            );

            Log::info('Catalog category picker sent', [
                'ai_agent_id' => $aiAgent->id,
                'catalog_id'  => $catalogId,
                'categories'  => $categories,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send category picker, falling back to direct catalog', [
                'catalog_id' => $catalogId,
                'error'      => $e->getMessage(),
            ]);
            $this->sendCatalog($account, $contact, $aiAgent);
        }
    }

    /**
     * Distinct, case-insensitive, sorted list of `category` values present in
     * the given products. Empty/missing categories are dropped.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, string>
     */
    private function extractCategories(array $products): array
    {
        $seen = [];
        foreach ($products as $p) {
            $cat = trim((string) ($p['category'] ?? ''));
            if ($cat === '') continue;
            $key = mb_strtolower($cat);
            if (! isset($seen[$key])) $seen[$key] = $cat; // preserve original casing of first occurrence
        }
        $values = array_values($seen);
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);
        return $values;
    }

    public function sendCatalog(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        ?string $categoryFilter = null
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

        // Optional category filter (case-insensitive). Used by the category-picker
        // flow so customers can drill into one category before seeing the catalog.
        if ($categoryFilter !== null) {
            $needle = mb_strtolower($categoryFilter);
            $products = array_values(array_filter($products, function ($p) use ($needle) {
                return mb_strtolower((string) ($p['category'] ?? '')) === $needle;
            }));
        }

        if (empty($products)) {
            $this->sendText($client, $contact->wa_id,
                $categoryFilter !== null
                    ? "Maaf, belum ada menu di kategori \"{$categoryFilter}\". Silakan pilih kategori lain."
                    : 'Maaf, saat ini belum ada menu yang tersedia. Silakan coba lagi nanti.');

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
     *
     * NOTE: do NOT pre-filter by image_fetch_status. Empirical observation:
     * a catalog with 6 FETCHED products sent only 4 via MPM — Meta has its
     * own internal MPM-eligibility cache (separate from image_fetch_status)
     * which is opaque to the API. Our pre-filter would only narrow the batch
     * without matching Meta's actual deliverability rule. Better strategy:
     * send everything available, let Meta drop what it can't deliver, and
     * fall back to text menu if Meta rejects the entire batch.
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

    /**
     * Merchant-triggered "test send" — like sendCatalog but takes a raw phone
     * number instead of WhatsAppContact, and returns a structured result with
     * counts so the dashboard can show what passed/got filtered.
     *
     * @return array{success: bool, error?: string, reason?: string, products_sent?: int, products_total?: int, products_skipped?: int}
     */
    public function testSendCatalog(
        WhatsAppAccount $account,
        string $catalogId,
        string $phone,
        string $botName = 'Test'
    ): array {
        $client = $this->client($account);

        $result = $this->catalogService->getCatalogProducts($account, $catalogId, self::MAX_TOTAL_ITEMS);
        if (! ($result['success'] ?? false)) {
            return [
                'success' => false,
                'error'   => 'Gagal mengambil produk dari Meta: ' . ($result['error'] ?? 'unknown'),
            ];
        }

        $allProducts = $result['products'] ?? [];
        // No FETCHED pre-filter. image_fetch_status doesn't match Meta's actual
        // MPM-eligibility (observed: 6 FETCHED → only 4 delivered). Send all
        // available; Meta will drop what it can't deliver. If Meta rejects the
        // whole batch we surface the error so the merchant knows.
        $products = $this->filterAvailableProducts($allProducts);
        $total    = count($allProducts);
        $sent     = count($products);

        if ($sent === 0) {
            return [
                'success'        => false,
                'error'          => 'Tidak ada produk dengan availability=in stock di katalog ini.',
                'products_total' => $total,
                'products_sent'  => 0,
            ];
        }

        try {
            $sections = $this->buildMultiProductSections($products);
            $header   = mb_substr('Menu ' . $botName, 0, 60);
            $body     = $sent === 1
                ? 'Berikut produk Anda. Tap "View" lalu "Add to cart" untuk memesan.'
                : 'Test kirim katalog — silakan pilih menu favorit.';
            $footer   = mb_substr($botName, 0, 60);

            $client->sendMultiProduct(
                $phone,
                (int) $catalogId,
                new MultiProductAction($sections),
                $header,
                $body,
                $footer
            );

            return [
                'success'        => true,
                'products_sent'  => $sent,
                'products_total' => $total,
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            return [
                'success'        => false,
                'error'          => $msg,
                'reason'         => $this->classifyCatalogError($msg),
                'products_total' => $total,
                'products_sent'  => $sent,
            ];
        }
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
        $this->renderer->sendCartConfirmation($account, $contact, $aiAgent, $items);
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

        $this->renderer->sendCartConfirmation($account, $contact, $aiAgent, $items);

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
                // Show category picker first (falls through to direct send
                // if the catalog only has 0-1 distinct categories).
                $this->sendCategoryPicker($account, $contact, $aiAgent);

                return true;
            }

            // Catalog inactive -> route to the standard LLM/POS-products flow.
            app(AiAgentService::class)->processMessage($account, $contact, 'menu');

            return true;
        }

        // Category picker reply (list_reply id="cat:<urlencoded>" or "cat:_all").
        if (str_starts_with($buttonId, self::CAT_PREFIX)) {
            if (! $aiAgent->isCatalogActive()) {
                app(AiAgentService::class)->processMessage($account, $contact, 'menu');
                return true;
            }

            if ($buttonId === self::CAT_ALL) {
                $this->sendCatalog($account, $contact, $aiAgent);
                return true;
            }

            $category = rawurldecode(substr($buttonId, strlen(self::CAT_PREFIX)));
            $this->sendCatalog($account, $contact, $aiAgent, $category);
            return true;
        }

        // "Lanjutkan" (drip CTA) — resume whatever step the order is at.
        if ($buttonId === self::BTN_CONTINUE_FLOW) {
            if ($state === null) {
                $this->renderer->sendFallbackWithMenuButton($account, $contact, $aiAgent,
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
                $this->renderer->sendFallbackWithMenuButton($account, $contact, $aiAgent,
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
                $this->renderer->sendFallbackWithMenuButton($account, $contact, $aiAgent,
                    'Baik. Link pembayaran tetap aktif sampai kadaluarsa — pesanan otomatis batal jika belum dibayar. '
                    .'Tap *Lihat Menu* kalau ingin memesan lagi.');
            } else {
                $this->renderer->sendCancelledReply($account, $contact, $aiAgent);
            }

            return true;
        }

        if ($state === self::STATE_CONFIRMING_CART) {
            if ($buttonId === self::BTN_CONFIRM_CART) {
                // Items confirmed -> proceed to fulfillment selection.
                $conversation->setFlowState(self::STATE_AWAITING_FULFILLMENT);
                $this->renderer->sendFulfillmentButtons($account, $contact, $aiAgent, $conversation->getCatalogItems());

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
            $this->renderer->sendCancelledReply($account, $contact, $aiAgent);

            return true;
        }

        // Labeled input / partial edit: any "label = value" pairs
        // (e.g. "ubah nama = Budi", "nama = Budi, alamat = Jl X", multi-line,
        // bullets). Each labeled field is MERGED into the existing parsed info
        // so single-field edits keep the rest intact.
        $labeled = $this->deliveryParser->extractLabeled($clean);
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
            $conversation->setDeliveryRawInfo($this->deliveryParser->compose($existing));
            $conversation->setFlowState(self::STATE_CONFIRMING_DELIVERY_INFO);

            Log::info('Labeled delivery fields applied', [
                'fields' => array_keys($labeled),
            ]);

            $this->renderer->sendDeliveryInfoSummary($account, $contact, $conversation);

            return true;
        }

        // Otherwise: free-text full parse (e.g. "Budi, 0812xxxx, Jl. Mawar 12").
        $conversation->setDeliveryRawInfo($clean);
        $conversation->setDeliveryParsed($this->deliveryParser->parseFree($clean));
        $conversation->setFlowState(self::STATE_CONFIRMING_DELIVERY_INFO);

        $this->renderer->sendDeliveryInfoSummary($account, $contact, $conversation);

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
            $this->renderer->sendOrderSummary($account, $contact, $conversation, $aiAgent);

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

            $this->renderer->sendDeliveryInfoPrompt($account, $contact);

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
            $this->renderer->sendOrderSummary($account, $contact, $conversation, $aiAgent);

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
            $this->orderCreator->createAndRespond($account, $contact, $conversation, $aiAgent);

            return true;
        }

        return false;
    }

    // ---- Drip / off-context handling ----------------------------------------

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
                $this->renderer->sendCartConfirmation($account, $contact, $aiAgent, $conversation->getCatalogItems());
                break;
            case self::STATE_AWAITING_FULFILLMENT:
                $this->renderer->sendFulfillmentButtons($account, $contact, $aiAgent, $conversation->getCatalogItems());
                break;
            case self::STATE_AWAITING_DELIVERY_INFO:
                $this->renderer->sendDeliveryInfoPrompt($account, $contact);
                break;
            case self::STATE_CONFIRMING_DELIVERY_INFO:
                $this->renderer->sendDeliveryInfoSummary($account, $contact, $conversation);
                break;
            case self::STATE_CONFIRMING_ORDER_SUMMARY:
                $this->renderer->sendOrderSummary($account, $contact, $conversation, $aiAgent);
                break;
            case self::STATE_AWAITING_PAYMENT:
                $this->renderer->resendPaymentLink($account, $contact, $conversation);
                break;
            default:
                // No active flow — nudge back to the menu.
                $this->renderer->sendFallbackWithMenuButton($account, $contact, $aiAgent,
                    'Tidak ada pesanan yang sedang berjalan. Tap *Lihat Menu* untuk mulai memesan.');
        }
    }

    protected function triggerReservation(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent
    ): void {
        // Reservation flow consumes the catalog cart; leave catalog items in
        // place in case the user wants to come back, but exit our state.
        $conversation->clearFlowState();
        $this->renderer->sendReservationHandoff($account, $contact, $aiAgent);
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
                $this->renderer->sendCartConfirmation($account, $contact, $aiAgent, $items);

                return true;

            case self::STATE_AWAITING_FULFILLMENT:
                $items = $conversation->getCatalogItems() ?: $conversation->getCart();
                $this->renderer->sendFulfillmentButtons($account, $contact, $aiAgent, $items);

                return true;

            case self::STATE_AWAITING_DELIVERY_INFO:
                $this->renderer->sendDeliveryInfoPrompt($account, $contact);

                return true;

            case self::STATE_CONFIRMING_DELIVERY_INFO:
                $this->renderer->sendDeliveryInfoSummary($account, $contact, $conversation);

                return true;

            case self::STATE_CONFIRMING_ORDER_SUMMARY:
                $this->renderer->sendOrderSummary($account, $contact, $conversation, $aiAgent);

                return true;

            case self::STATE_AWAITING_PAYMENT:
                return $this->renderer->sendPaymentReminder($account, $contact, $conversation, $aiAgent);
        }

        return false;
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

}
