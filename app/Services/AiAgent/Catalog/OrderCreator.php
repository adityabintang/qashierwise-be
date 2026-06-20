<?php

namespace App\Services\AiAgent\Catalog;

use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\CatalogProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgent\Reply\ReplySender;
use App\Services\CatalogOrderFlowService;
use App\Services\OrderService;
use App\Services\QrisService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Materializes a confirmed catalog cart into a real Order row, then routes
 * the customer to the appropriate post-order reply: pickup-instruction (no
 * payment link) or delivery-with-QRIS.
 *
 * The whole order-create path lives in one place so that the DB transaction
 * boundary and the post-create messaging are obvious.
 */
class OrderCreator
{
    public function __construct(
        protected QrisService $qrisService,
        protected ReplySender $replySender,
        protected \App\Services\MerchantNotificationService $merchantNotif,
    ) {}

    /**
     * End-to-end: take confirmed catalog items + delivery context off the
     * conversation, write Order + OrderItem rows in one transaction, then
     * branch to pickup or delivery+QRIS messaging.
     *
     * On any failure: log and send a generic "try again" — the customer is
     * never left wondering because we always reply.
     */
    public function createAndRespond(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
    ): void {
        if (! $aiAgent->default_store_id) {
            $this->replySender->send($account, $contact->wa_id,
                'Maaf, toko default belum dikonfigurasi. Hubungi penjual.');

            return;
        }

        $items = $conversation->getCatalogItems();
        if (empty($items)) {
            $this->replySender->send($account, $contact->wa_id,
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

                // For delivery, prefer the recipient details the customer
                // typed (parsed) over WhatsApp profile — the WA owner is
                // often not the delivery recipient.
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

                // Stock lives in TWO local tables depending on where the product
                // was created:
                //   - Product (POS Products page) — for POS-native items
                //   - CatalogProduct (Meta Katalog page) — for catalog-native items
                // A single product can exist in both (when synced from POS to
                // Meta). To be safe, decrement WHEREVER the row is found.
                $retailerIds = array_filter(array_column($items, 'product_retailer_id'));

                $posProductsBySku = $retailerIds
                    ? Product::whereIn('sku', $retailerIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('sku')
                    : collect();

                $catalogRowsBySku = $retailerIds
                    ? CatalogProduct::whereIn('retailer_id', $retailerIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('retailer_id')
                    : collect();

                foreach ($items as $item) {
                    $sku = $item['product_retailer_id'] ?? null;
                    $posProduct     = $sku ? $posProductsBySku->get($sku) : null;
                    $catalogProduct = $sku ? $catalogRowsBySku->get($sku) : null;
                    $qty            = (int) $item['quantity'];

                    // product_id links to POS Product so order detail UI can
                    // pull from the POS relationship. CatalogProduct lives in a
                    // separate table — no FK link, just stock mirror.
                    $productId = $item['pos_product_id'] ?? $posProduct?->id;

                    OrderItem::create([
                        'order_id'            => $order->id,
                        'product_id'          => $productId,
                        'product_name'        => $item['product_name']
                            ?: ($posProduct?->name ?: ($catalogProduct?->name ?: $sku)),
                        'product_retailer_id' => $sku,
                        'quantity'            => $qty,
                        'unit_price'          => $item['item_price'],
                        'subtotal'            => $item['item_price'] * $qty,
                    ]);

                    // POS-side stock (Products table).
                    if ($posProduct && $posProduct->stock_quantity !== null) {
                        $posProduct->stock_quantity = max(0, $posProduct->stock_quantity - $qty);
                        $posProduct->save();
                    }

                    // Catalog-side stock (CatalogProduct table) — same record
                    // shown in /dashboard/meta-catalog stock pill.
                    if ($catalogProduct && $catalogProduct->stock_quantity !== null) {
                        $catalogProduct->stock_quantity = max(0, $catalogProduct->stock_quantity - $qty);
                        $catalogProduct->is_available = $catalogProduct->stock_quantity > 0;
                        $catalogProduct->save();
                    }

                    // Low-stock notif — fire OUTSIDE this transaction would be
                    // safer (notification failure shouldn't roll back the order),
                    // but the service swallows its own errors so it's safe here.
                    // Dedupes per (user, sku, day) so we don't spam on repeats.
                    if ($posProduct) {
                        $this->merchantNotif->notifyLowStockIfApplicable($posProduct);
                    }
                    if ($catalogProduct) {
                        $this->merchantNotif->notifyLowStockIfApplicable($catalogProduct);
                    }
                }

                return $order->fresh();
            });

            // Merchant notification fires per-flow:
            //   - pickup: immediately after customer is replied (in respondPickup)
            //   - delivery / reservation: deferred to ProcessQrisPayment (after
            //     payment settles — notifying merchant before payment would be
            //     misleading because the order isn't "real" yet).

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
            $this->replySender->send($account, $contact->wa_id,
                'Maaf, terjadi kesalahan saat membuat pesanan. Silakan coba lagi nanti.');
        }
    }

    /**
     * Pickup orders close the flow loop immediately — no payment link,
     * customer pays at the counter. State + cart are cleared so the next
     * message starts a fresh conversation cycle.
     */
    protected function respondPickup(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        Order $order,
    ): void {
        $formattedTotal = 'Rp '.number_format($order->total, 0, ',', '.');

        $msg = "✅ *Pesanan Berhasil Dibuat!*\n\n"
            ."📋 No. Pesanan: {$order->order_number}\n"
            ."💰 Total: {$formattedTotal}\n\n"
            ."🏪 Silakan ambil pesanan Anda di restoran.\n"
            ."Tunjukkan nomor pesanan di atas ke kasir saat pengambilan.\n\n"
            .'Terima kasih! 🙏';

        $this->replySender->send($account, $contact->wa_id, $msg);

        $conversation->clearCatalogItems();
        $conversation->clearDeliveryContext();
        $conversation->clearFlowState();

        // Pickup closes the loop without payment — notify merchant immediately.
        try {
            $this->merchantNotif->notifyNewOrder($order);
        } catch (\Throwable $e) {
            Log::warning('Merchant pickup notif failed (non-fatal)', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delivery orders attach a QRIS link; the conversation stays in
     * STATE_AWAITING_PAYMENT so follow-ups & drips fire correctly. Falls
     * back to a "contact penjual" message if QRIS is disabled or generation
     * fails (rare but the customer should never be left without a reply).
     */
    protected function respondDeliveryWithQris(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        Order $order,
    ): void {
        if (! $aiAgent->isQrisEnabled()) {
            $this->replySender->send($account, $contact->wa_id,
                "✅ Pesanan dibuat: {$order->order_number}\nTotal: Rp ".number_format($order->total, 0, ',', '.').
                "\n\nMohon hubungi penjual untuk metode pembayaran.");
            $conversation->clearCatalogItems();
            $conversation->clearDeliveryContext();
            $conversation->clearFlowState();

            return;
        }

        $subMerchant = $aiAgent->getSubMerchant();
        if (! $subMerchant) {
            $this->replySender->send($account, $contact->wa_id,
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

            $this->replySender->send($account, $contact->wa_id, $msg);

            // Keep order context alive in AWAITING_PAYMENT so off-context
            // chatter triggers the "lanjutkan pembayaran" follow-up. State
            // + context are cleared on payment success (AiAgentService::sendPaymentConfirmation).
            $conversation->setFlowState(CatalogOrderFlowService::STATE_AWAITING_PAYMENT);
        } catch (\Throwable $e) {
            Log::error('QRIS generation failed in catalog flow', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);
            $this->replySender->send($account, $contact->wa_id,
                "✅ Pesanan dibuat: {$order->order_number}\n".'Namun pembuatan QRIS gagal. Silakan hubungi penjual.');
        }
    }

    /**
     * Sum item price × quantity. Tax + ongkir are applied by the caller.
     */
    protected function itemsSubtotal(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) $item['item_price'] * (int) $item['quantity'];
        }

        return round($total, 2);
    }

    /**
     * Per-store, per-day sequential order number. Loops on collision so the
     * generated number is guaranteed unique under concurrent creates.
     */
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
