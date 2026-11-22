<?php

namespace App\Services\AiAgent\Checkout;

use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\QrisService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tools that finalise an order from the cart.
 *
 *   confirmOrder  — creates the Order row, optionally fires QRIS, clears
 *                   conversation cart/context. The single LLM-driven path
 *                   from "items selected" to "order created".
 *   setOrderNotes — stash the customer's free-text note for the kitchen.
 *
 * Both methods return a user-facing message the LLM relays verbatim.
 */
class CheckoutTools
{
    public function __construct(
        protected OrderService $orderService,
        protected QrisService $qrisService,
    ) {}

    /**
     * Persist the customer's checkout-time note. Empty / "tidak ada" both
     * clear any previous note so the kitchen doesn't see stale text.
     */
    public function setOrderNotes(AiAgentConversation $conversation, array $arguments): string
    {
        $notes = trim($arguments['notes'] ?? '');

        if ($notes === '' || strtolower($notes) === 'tidak ada') {
            $conversation->setDeliveryNotes(null);

            return "✅ Tidak ada catatan khusus.\n\nKetik 'konfirmasi' untuk melanjutkan checkout.";
        }

        $conversation->setDeliveryNotes($notes);

        return "📝 Catatan tersimpan: {$notes}\n\nKetik 'konfirmasi' untuk melanjutkan checkout.";
    }

    /**
     * Create the Order, attach items, and either fire QRIS (when configured)
     * or fall through to a manual-payment message. Wrapped in a DB transaction
     * so a partial failure (e.g. QRIS gen) rolls back the whole thing.
     */
    public function confirmOrder(AiAgentConversation $conversation, int $userId, ?AiAgent $aiAgent = null): string
    {
        $cart = $conversation->getCart();
        if (empty($cart)) {
            return '🛒 Keranjang belanja Anda masih kosong. Silakan tambahkan produk terlebih dahulu.';
        }
        if (! $aiAgent || ! $aiAgent->default_store_id) {
            return 'Maaf, toko default belum dikonfigurasi.';
        }
        if ($aiAgent->isDeliveryEnabled() && ! $conversation->getDeliveryType()) {
            return "Sebelum checkout, pilih metode pengiriman:\n• Ketik 'pickup' untuk ambil di tempat\n• Ketik 'delivery' untuk diantar";
        }

        try {
            return DB::transaction(function () use ($conversation, $aiAgent, $userId, $cart) {
                $contact = $conversation->whatsappContact()->withoutGlobalScopes()->first();

                $deliveryType = $conversation->getDeliveryType();
                $deliveryAddress = $conversation->getDeliveryAddress();
                $deliveryNotes = $conversation->getDeliveryNotes();
                $ongkir = $conversation->getOngkir();

                Log::info('Creating order via WhatsApp AI confirm_order tool', [
                    'user_id' => $userId,
                    'store_id' => $aiAgent->default_store_id,
                    'cart_items' => count($cart),
                    'qris_enabled' => $aiAgent->isQrisEnabled(),
                    'delivery_type' => $deliveryType,
                    'ongkir' => $ongkir,
                ]);

                $order = $this->orderService->create([
                    'store_id' => $aiAgent->default_store_id,
                    'table_id' => null,
                    'pos_user_id' => null,
                    'source' => Order::SOURCE_WHATSAPP_AI,
                    'customer_name' => $contact->name ?? 'WhatsApp Customer',
                    'customer_phone' => $contact->wa_id,
                    'delivery_type' => $deliveryType ?? Order::DELIVERY_TYPE_PICKUP,
                    'alamat' => $deliveryAddress,
                    'ongkir' => $ongkir,
                    'catatan' => $deliveryNotes,
                ]);

                foreach ($cart as $item) {
                    $product = Product::find($item['product_id']);
                    if ($product) {
                        $this->orderService->addItem($order, $product, $item['quantity']);
                    }
                }

                $order->refresh();

                $conversation->setCurrentOrder($order->id);
                $conversation->clearCart();
                $conversation->clearPendingOrder();
                $conversation->clearDeliveryContext();

                Log::info('Order created via WhatsApp AI', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                ]);

                if ($aiAgent->isQrisEnabled() && ($qrisMessage = $this->tryAttachQris($conversation, $aiAgent, $order)) !== null) {
                    return $qrisMessage;
                }

                $formattedTotal = 'Rp '.number_format($order->total, 0, ',', '.');

                return "✅ Pesanan Berhasil Dibuat!\n\n"
                    ."📋 No. Pesanan: {$order->order_number}\n"
                    ."💰 Total: {$formattedTotal}\n\n"
                    ."Pesanan Anda sedang diproses.\n"
                    ."Silakan tunjukkan pesan ini ke kasir untuk melakukan pembayaran.\n\n"
                    .'Terima kasih! 🙏';
            });
        } catch (\Exception $e) {
            Log::error('Order creation failed via WhatsApp AI confirm_order', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 'Maaf, terjadi kesalahan saat membuat pesanan. Silakan coba lagi.';
        }
    }

    /**
     * Best-effort QRIS attachment. Returns the customer-facing message on
     * success, or null if the merchant has no active sub-merchant / QRIS
     * generation fails. Caller falls back to the manual-payment branch.
     */
    protected function tryAttachQris(AiAgentConversation $conversation, AiAgent $aiAgent, Order $order): ?string
    {
        $subMerchant = $aiAgent->getSubMerchant();
        if (! $subMerchant) {
            return null;
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

            return "✅ Pesanan Berhasil Dibuat!\n\n"
                ."📋 No. Pesanan: {$order->order_number}\n"
                ."💰 Total: {$formattedTotal}\n\n"
                ."💳 Silakan bayar melalui link berikut:\n"
                ."{$qrisTransaction->getShareableLink()}\n\n"
                ."⏰ Berlaku hingga: {$expiryTime}\n\n"
                ."Cara pembayaran:\n"
                ."1. Klik link di atas\n"
                ."2. Scan QR Code yang muncul\n"
                ."3. Buka aplikasi e-wallet/mobile banking\n"
                ."4. Konfirmasi pembayaran\n\n"
                ."Ketik 'cek status' setelah membayar. 🙏";
        } catch (\Exception $e) {
            Log::error('QRIS generation failed during WhatsApp order confirmation', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);

            return null;
        }
    }
}
