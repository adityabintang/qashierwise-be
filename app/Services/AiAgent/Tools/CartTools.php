<?php

namespace App\Services\AiAgent\Tools;

use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Cart-mutation tools exposed to the LLM. Three ways to add items:
 *
 *   addById       — exact id, when the LLM has already searched and knows it.
 *   addMultiById  — batch of {product_id, quantity}.
 *   addByName     — fuzzy name match via ProductResolver; preferred path
 *                   because it survives slight typos and ambiguities with
 *                   actionable error messages.
 *
 * Plus the read/edit helpers: summary, remove, clear.
 *
 * All persistence routes through AiAgentConversation::updateCart() so the
 * drip scheduler fires correctly on cart events.
 */
class CartTools
{
    public function __construct(
        protected ProductResolver $productResolver,
    ) {}

    /**
     * Exact-id add. Returns user-facing string suitable to feed back to the
     * LLM, which usually relays it verbatim.
     */
    public function addById(
        AiAgentConversation $conversation,
        int $userId,
        int $productId,
        int $quantity,
    ): string {
        if ($productId <= 0) {
            return 'Maaf, ID produk tidak valid. Mohon berikan ID produk yang benar.';
        }
        if ($quantity <= 0) {
            return 'Maaf, jumlah pesanan harus lebih dari 0.';
        }

        $product = Product::where('user_id', $userId)
            ->where('id', $productId)
            ->where('is_active', true)
            ->first();

        if (! $product) {
            return "Maaf, produk dengan ID {$productId} tidak ditemukan.";
        }

        if ($product->stock_quantity < $quantity) {
            return "Maaf, stok {$product->name} tidak mencukupi. Stok tersedia: {$product->stock_quantity}";
        }

        $cart = $conversation->getCart();
        $this->mergeOrAppend($cart, [
            'product_id' => $productId,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => $quantity,
        ]);

        $conversation->updateCart($cart);

        return "✅ {$product->name} x{$quantity} berhasil ditambahkan ke keranjang!\n\nKetik 'lihat keranjang' untuk melihat isi keranjang Anda.";
    }

    /**
     * Batch add by id. Collects per-item errors so a partial success still
     * returns useful items in the cart.
     */
    public function addMultiById(
        AiAgentConversation $conversation,
        int $userId,
        array $products,
    ): string {
        if (empty($products)) {
            return 'Maaf, tidak ada produk yang ditambahkan.';
        }

        $cart = $conversation->getCart();
        $added = [];
        $errors = [];

        foreach ($products as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? null;

            if (! $productId || ! $quantity || ! is_numeric($productId) || ! is_numeric($quantity)) {
                $errors[] = 'Produk dengan data tidak lengkap dilewati';

                continue;
            }

            $productId = (int) $productId;
            $quantity = (int) $quantity;
            if ($productId <= 0 || $quantity <= 0) {
                $errors[] = "Produk ID {$productId}: nilai tidak valid";

                continue;
            }

            $product = Product::where('user_id', $userId)
                ->where('id', $productId)
                ->where('is_active', true)
                ->first();

            if (! $product) {
                $errors[] = "Produk ID {$productId} tidak ditemukan";

                continue;
            }
            if ($product->stock_quantity < $quantity) {
                $errors[] = "{$product->name}: stok tidak mencukupi (tersedia: {$product->stock_quantity})";

                continue;
            }

            $this->mergeOrAppend($cart, [
                'product_id' => $productId,
                'product_name' => $product->name,
                'price' => $product->price,
                'quantity' => $quantity,
            ]);

            $added[] = ['name' => $product->name, 'quantity' => $quantity];
        }

        if (! empty($added)) {
            $conversation->updateCart($cart);
        }

        if (empty($added) && ! empty($errors)) {
            return "❌ Gagal menambahkan produk:\n".implode("\n", $errors);
        }

        $response = "✅ Berhasil menambahkan ke keranjang!\n\n";
        foreach ($added as $a) {
            $response .= "📦 {$a['name']} x{$a['quantity']}\n";
        }
        if (! empty($errors)) {
            $response .= "\n⚠️ Beberapa produk tidak dapat ditambahkan:\n".implode("\n", $errors);
        }
        $response .= "\nKetik 'lihat keranjang' untuk melihat ringkasan pesanan.";

        return $response;
    }

    /**
     * Preferred add path — accepts customer-supplied names with typos.
     * Delegates name resolution to ProductResolver and turns "ambiguous"
     * or "not found" outcomes into helpful suggestions the LLM relays.
     */
    public function addByName(
        AiAgentConversation $conversation,
        int $userId,
        array $items,
    ): string {
        if (empty($items)) {
            return 'Maaf, tidak ada produk yang ditambahkan.';
        }

        $cart = $conversation->getCart();
        $added = [];
        $errors = [];
        $totalAdded = 0;

        foreach ($items as $item) {
            $productName = $item['product_name'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 1);
            if ($quantity <= 0) {
                $quantity = 1;
            }
            if (empty($productName)) {
                continue;
            }

            $resolution = $this->productResolver->resolve($userId, $productName);

            if ($resolution['status'] === 'not_found') {
                Log::info('add_to_cart: product not found', [
                    'query' => $productName,
                    'alternatives_count' => count($resolution['alternatives']),
                ]);
                $hint = $this->productResolver->formatSuggestions($resolution['alternatives']);
                $errors[] = "'{$productName}' tidak ditemukan.".($hint ? ' Mungkin maksudnya: '.$hint.'?' : '');

                continue;
            }

            if ($resolution['status'] === 'ambiguous') {
                Log::info('add_to_cart: ambiguous match', [
                    'query' => $productName,
                    'candidates_count' => count($resolution['candidates']),
                ]);
                $list = $this->productResolver->formatSuggestions($resolution['candidates']);
                $errors[] = "'{$productName}' cocok dengan beberapa produk: {$list}. Mohon sebutkan nama yang tepat.";

                continue;
            }

            $product = $resolution['product'];

            if ($product->stock_quantity !== null && $product->stock_quantity < $quantity) {
                $errors[] = "{$product->name}: stok tidak mencukupi";

                continue;
            }

            $this->mergeOrAppend($cart, [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => (float) $product->price,
                'quantity' => $quantity,
            ]);

            $subtotal = $product->price * $quantity;
            $totalAdded += $subtotal;
            $added[] = [
                'name' => $product->name,
                'quantity' => $quantity,
                'price' => $product->price,
                'subtotal' => $subtotal,
            ];
        }

        if (! empty($added)) {
            $conversation->updateCart($cart);
        }

        if (empty($added) && ! empty($errors)) {
            return "❌ Gagal menambahkan produk:\n".implode("\n", $errors);
        }

        $response = "✅ Berhasil menambahkan ke keranjang!\n\n";
        foreach ($added as $p) {
            $formattedPrice = 'Rp '.number_format($p['price'], 0, ',', '.');
            $formattedSubtotal = 'Rp '.number_format($p['subtotal'], 0, ',', '.');
            $response .= "📦 {$p['name']} x{$p['quantity']}\n";
            $response .= "   {$formattedPrice} × {$p['quantity']} = {$formattedSubtotal}\n";
        }
        if (! empty($errors)) {
            $response .= "\n⚠️ ".implode(', ', $errors);
        }

        $response .= "\n─────────────────\n";
        $response .= '💰 Subtotal: Rp '.number_format($totalAdded, 0, ',', '.')."\n\n";
        $response .= "Ketik 'lihat keranjang' untuk melihat ringkasan pesanan atau 'konfirmasi' untuk checkout.";

        return $response;
    }

    /**
     * Itemised cart with tax + optional delivery ongkir. The ongkir line
     * appears only when delivery is the active fulfillment.
     */
    public function summary(
        AiAgentConversation $conversation,
        int $userId,
        ?AiAgent $aiAgent = null,
    ): string {
        $cart = $conversation->getCart();
        if (empty($cart)) {
            return 'Keranjang belanja Anda masih kosong.';
        }

        $response = "🛒 Keranjang Belanja Anda:\n\n";
        $total = 0;

        foreach ($cart as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $total += $subtotal;

            $response .= "• {$item['product_name']}\n";
            $response .= "  Jumlah: {$item['quantity']} x Rp ".number_format($item['price'], 0, ',', '.')."\n";
            $response .= '  Subtotal: Rp '.number_format($subtotal, 0, ',', '.')."\n\n";
        }

        $ongkir = 0;
        if ($aiAgent && $aiAgent->isDeliveryEnabled()) {
            if ($conversation->getDeliveryType() === 'delivery') {
                $ongkir = (float) $aiAgent->default_ongkir;
            }
        }

        $tax = round($total * 0.11, 2);
        $grandTotal = round($total + $tax + $ongkir, 2);

        $response .= 'Subtotal: Rp '.number_format($total, 0, ',', '.')."\n";
        $response .= 'Pajak (11%): Rp '.number_format($tax, 0, ',', '.')."\n";
        if ($ongkir > 0) {
            $response .= 'Ongkir: Rp '.number_format($ongkir, 0, ',', '.')."\n";
        }
        $response .= 'Total: Rp '.number_format($grandTotal, 0, ',', '.')."\n\n";
        $response .= "Ketik 'konfirmasi pesanan' untuk melanjutkan checkout.";

        return $response;
    }

    /**
     * Remove by partial-name match. Returns a summary of what's left so the
     * customer doesn't have to re-ask "lihat keranjang".
     */
    public function remove(AiAgentConversation $conversation, string $productName): string
    {
        $cart = $conversation->getCart();
        if (empty($cart)) {
            return '🛒 Keranjang sudah kosong.';
        }

        $cleanName = trim(strtolower($productName));
        $removed = null;
        $newCart = [];

        foreach ($cart as $item) {
            if (stripos($item['product_name'], $cleanName) !== false) {
                $removed = $item;
            } else {
                $newCart[] = $item;
            }
        }

        if (! $removed) {
            return "❌ Produk '{$productName}' tidak ditemukan di keranjang.\n\nKetik 'lihat keranjang' untuk melihat isi keranjang Anda.";
        }

        $conversation->updateCart($newCart);

        $response = "✅ {$removed['product_name']} berhasil dihapus dari keranjang.\n\n";
        if (empty($newCart)) {
            return $response.'🛒 Keranjang sekarang kosong.';
        }

        $total = 0;
        $response .= "📦 Sisa pesanan:\n";
        foreach ($newCart as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $total += $subtotal;
            $response .= "• {$item['product_name']} x{$item['quantity']} - Rp ".number_format($subtotal, 0, ',', '.')."\n";
        }
        $grandTotal = round($total + round($total * 0.11, 2), 2);
        $response .= "\nTotal: Rp ".number_format($grandTotal, 0, ',', '.')."\n";
        $response .= "\nKetik 'konfirmasi' untuk checkout atau tambah pesanan lagi.";

        return $response;
    }

    public function clear(AiAgentConversation $conversation): string
    {
        if (empty($conversation->getCart())) {
            return '🛒 Keranjang sudah kosong.';
        }
        $conversation->updateCart([]);

        return "🗑️ Keranjang berhasil dikosongkan.\n\nSilakan mulai pesan lagi jika berubah pikiran! 😊";
    }

    /**
     * Either bump quantity of an existing cart row or push a new one. Shared
     * by addById, addMultiById, and addByName so they stay behaviorally
     * identical when a product is already in the cart.
     */
    protected function mergeOrAppend(array &$cart, array $newItem): void
    {
        foreach ($cart as &$cartItem) {
            if ($cartItem['product_id'] == $newItem['product_id']) {
                $cartItem['quantity'] += $newItem['quantity'];

                return;
            }
        }
        unset($cartItem);

        $cart[] = $newItem;
    }
}
