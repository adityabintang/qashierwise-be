<?php

namespace App\Services;

use App\Models\CatalogProduct;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * High-level notifications sent to MERCHANT's WhatsApp number.
 *
 * Two notification families:
 *   1. New-order notifications (3 sub-flows: pickup, delivery, reservation)
 *   2. Low-stock notifications (deduplicated per product per 24h)
 *
 * Recipient resolution: Order → Store → User → WhatsAppAccount.display_phone_number
 * Stock notifications use the product owner (user_id on Product / CatalogProduct).
 */
class MerchantNotificationService
{
    public function __construct(
        protected FonnteService $fonnte,
    ) {}

    /**
     * Notify merchant of a new order. Auto-detects sub-flow from order data.
     * Safe to call multiple times — Fonnte itself doesn't dedupe, but each
     * order is created once so this naturally fires once per order.
     */
    public function notifyNewOrder(Order $order): void
    {
        $phone = $this->resolveMerchantPhoneFromOrder($order);
        if (! $phone) {
            Log::warning('MerchantNotificationService: phone tidak ditemukan untuk order', [
                'order_id' => $order->id,
                'store_id' => $order->store_id,
            ]);
            return;
        }

        $merchantName = $this->resolveMerchantName($order);
        $items = $this->formatItems($order);
        $link = $this->orderDetailLink($order);

        // Single template per PM spec. Sub-flow distinction is conveyed via
        // a one-line prefix so the merchant immediately knows the order type.
        $flowLabel = match ($order->delivery_type) {
            Order::DELIVERY_TYPE_DELIVERY  => '🛵 Delivery',
            Order::DELIVERY_TYPE_PICKUP    => '🏪 Pickup',
            'reservasi'                    => '📅 Reservasi',
            default                        => '📋 Pesanan baru',
        };

        $msg  = "Halo {$merchantName}, kamu ada pesanan baru nih ({$flowLabel}):\n\n";
        $msg .= "ID Pesanan: {$order->order_number}\n\n";
        $msg .= "Item:\n{$items}\n\n";
        $msg .= "Total Harga: Rp" . number_format((float) $order->total, 0, ',', '.') . "\n\n";
        $msg .= "Silakan konfirmasi pada link berikut:\n{$link}";

        $this->fonnte->send($phone, $msg);
    }

    /**
     * Notify merchant when a product's stock crosses the low-stock threshold.
     * Deduplicated by (product key, day) so repeat orders don't spam.
     *
     * @param  Product|CatalogProduct  $product  newly-decremented row
     */
    public function notifyLowStockIfApplicable(Product|CatalogProduct $product): void
    {
        $threshold = (int) config('fonnte.low_stock_threshold', 5);
        $stock = $product->stock_quantity ?? null;
        if ($stock === null || $stock > $threshold || $stock < 0) {
            return;
        }

        // Resolve key for dedupe + merchant
        $userId = $product->user_id ?? null;
        $sku    = $product->sku ?? $product->retailer_id ?? null;
        $name   = $product->name ?? '(produk)';

        if (! $userId || ! $sku) {
            return;
        }

        $cacheKey = "fonnte.lowstock.{$userId}.{$sku}";
        if (Cache::has($cacheKey)) {
            Log::info('MerchantNotificationService: low-stock notif deduped', [
                'user_id' => $userId, 'sku' => $sku, 'stock' => $stock,
            ]);
            return;
        }

        $phone = $this->resolveMerchantPhoneFromUserId($userId);
        if (! $phone) {
            Log::warning('MerchantNotificationService: phone tidak ditemukan untuk low-stock', [
                'user_id' => $userId,
            ]);
            return;
        }

        $merchantName = $this->resolveMerchantNameFromUserId($userId);
        $stockWord = $stock === 0 ? 'HABIS' : "tinggal {$stock} unit";

        $msg  = "Halo {$merchantName}, stok produk hampir habis:\n\n";
        $msg .= "📦 Produk : {$name}\n";
        $msg .= "🏷️ SKU    : {$sku}\n";
        $msg .= "📉 Stok   : {$stockWord}\n\n";
        $msg .= "Segera tambah stok agar pesanan tidak terganggu.\n";
        $msg .= config('app.url') . "/dashboard/meta-catalog";

        $result = $this->fonnte->send($phone, $msg);
        if ($result['success']) {
            Cache::put($cacheKey, true, (int) config('fonnte.low_stock_dedupe_ttl', 86400));
        }
    }

    // ---- helpers ----------------------------------------------------------

    private function resolveMerchantPhoneFromOrder(Order $order): ?string
    {
        $userId = optional($order->store)->user_id;
        return $userId ? $this->resolveMerchantPhoneFromUserId($userId) : null;
    }

    private function resolveMerchantPhoneFromUserId(int $userId): ?string
    {
        // WABA's display_phone_number — empirically the field that holds the
        // merchant's WhatsApp number after onboarding.
        return \App\Models\WhatsAppAccount::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->orderByDesc('is_active')
            ->value('display_phone_number');
    }

    private function resolveMerchantName(Order $order): string
    {
        $user = optional($order->store)->user;
        return $user?->name ?? 'Merchant';
    }

    private function resolveMerchantNameFromUserId(int $userId): string
    {
        $user = \App\Models\User::find($userId);
        return $user?->name ?? 'Merchant';
    }

    private function formatItems(Order $order): string
    {
        $items = $order->items ?? collect();
        if ($items->isEmpty()) {
            return '(tidak ada item)';
        }

        return $items->map(function ($it) {
            $name = $it->product_name ?: ($it->product_retailer_id ?? 'Produk');
            return "  • {$name} x{$it->quantity}";
        })->implode("\n");
    }

    private function orderDetailLink(Order $order): string
    {
        // /dashboard/pos/orders is the list page (no per-order route exists).
        // Append a query hint so the dashboard can scroll/highlight the order.
        return rtrim(config('app.url'), '/') . '/dashboard/pos/orders?order=' . urlencode($order->order_number);
    }
}
