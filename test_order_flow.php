<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Product;
use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;

echo "=== Test AI Agent Order Flow ===\n\n";

// Get user and AI Agent
$user = User::first();
if (!$user) {
    echo "❌ Tidak ada user\n";
    exit(1);
}

$whatsappAccount = WhatsAppAccount::where('user_id', $user->id)->first();
if (!$whatsappAccount) {
    echo "❌ Tidak ada WhatsApp Account\n";
    exit(1);
}

$aiAgent = AiAgent::where('whatsapp_account_id', $whatsappAccount->id)->first();
if (!$aiAgent) {
    echo "❌ Tidak ada AI Agent\n";
    exit(1);
}

echo "User: {$user->name} (ID: {$user->id})\n";
echo "AI Agent: {$aiAgent->bot_name}\n";
echo "Order Enabled: " . ($aiAgent->isOrderEnabled() ? 'Ya' : 'Tidak') . "\n\n";

// Show products
echo "=== Produk Tersedia ===\n";
$products = Product::where('user_id', $user->id)->where('is_active', true)->get();
foreach ($products as $p) {
    echo "- ID:{$p->id} | {$p->name} | Rp " . number_format($p->price, 0, ',', '.') . "\n";
}
echo "\n";

// Create test contact and conversation
$testContact = WhatsAppContact::firstOrCreate(
    ['user_id' => $user->id, 'wa_id' => 'test_cli_' . $user->id],
    ['name' => 'Test CLI']
);

// Clear old conversation
AiAgentConversation::where('ai_agent_id', $aiAgent->id)
    ->where('whatsapp_contact_id', $testContact->id)
    ->delete();

$conversation = AiAgentConversation::create([
    'ai_agent_id' => $aiAgent->id,
    'whatsapp_contact_id' => $testContact->id,
    'messages' => [],
    'order_context' => [],
    'expires_at' => now()->addHours(24),
]);

echo "=== Simulasi Flow Pemesanan ===\n\n";

// Simulate the tool calls manually
echo "--- Step 1: User minta pesan dimsum dan teh jumbo ---\n";
echo "User: \"mau pesan dimsum dan teh jumbo\"\n\n";

// Simulate search_multiple_products
echo "AI memanggil: search_multiple_products(['dimsum', 'teh'])\n";
$searchResult = searchMultipleProducts($user->id, ['dimsum', 'teh']);
echo "Hasil (internal, tidak ditampilkan ke user):\n";
echo $searchResult . "\n\n";

// Extract IDs from search result
preg_match_all('/\[ID:(\d+)\]/', $searchResult, $matches);
$productIds = $matches[1] ?? [];
echo "ID yang diekstrak: " . implode(', ', $productIds) . "\n\n";

// Simulate add_to_cart
if (count($productIds) >= 2) {
    echo "AI memanggil: add_to_cart(products=[{product_id:{$productIds[0]}, quantity:1}, {product_id:{$productIds[1]}, quantity:1}])\n";
    $cartResult = addMultipleToCart($conversation, $user->id, [
        ['product_id' => (int)$productIds[0], 'quantity' => 1],
        ['product_id' => (int)$productIds[1], 'quantity' => 1],
    ]);
    echo "\nHasil add_to_cart (ditampilkan ke user):\n";
    echo $cartResult . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n\n";

echo "--- Step 2: User lihat keranjang ---\n";
echo "User: \"lihat keranjang\"\n\n";
$cartSummary = getCartSummary($conversation, $user->id);
echo "Hasil:\n" . $cartSummary . "\n";

echo "\n=== Test Selesai ===\n";

// Helper functions (copy from AiAgentService)
function searchMultipleProducts(int $userId, array $queries): string
{
    if (empty($queries)) {
        return 'Mohon berikan kata kunci pencarian produk.';
    }

    $allResults = [];
    $notFound = [];

    foreach ($queries as $query) {
        if (empty(trim($query))) continue;

        $cleanQuery = trim(strtolower($query));
        $products = Product::where('user_id', $userId)
            ->where('is_active', true)
            ->whereRaw('LOWER(name) LIKE ?', ["%{$cleanQuery}%"])
            ->limit(5)
            ->get(['id', 'name', 'price', 'stock_quantity']);

        if ($products->isEmpty()) {
            $notFound[] = $query;
        } else {
            foreach ($products as $product) {
                if (!isset($allResults[$product->id])) {
                    $allResults[$product->id] = $product;
                }
            }
        }
    }

    if (empty($allResults)) {
        return "Tidak ditemukan: " . implode(', ', $notFound);
    }

    $response = "Berikut produk yang ditemukan:\n\n";
    foreach ($allResults as $product) {
        $response .= "🔹 {$product->name} [ID:{$product->id}]\n";
        $response .= '   Harga: Rp '.number_format($product->price, 0, ',', '.')."\n";
        $response .= "   Stok: {$product->stock_quantity}\n\n";
    }

    if (!empty($notFound)) {
        $response .= "⚠️ Tidak ditemukan: " . implode(', ', $notFound) . "\n";
    }

    return $response;
}

function addMultipleToCart(AiAgentConversation $conversation, int $userId, array $products): string
{
    $cart = $conversation->getCart();
    $addedProducts = [];

    foreach ($products as $item) {
        $productId = $item['product_id'];
        $quantity = $item['quantity'];

        $product = Product::where('user_id', $userId)
            ->where('id', $productId)
            ->where('is_active', true)
            ->first();

        if (!$product) continue;

        $found = false;
        foreach ($cart as &$cartItem) {
            if ($cartItem['product_id'] == $productId) {
                $cartItem['quantity'] += $quantity;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $cart[] = [
                'product_id' => $productId,
                'product_name' => $product->name,
                'price' => $product->price,
                'quantity' => $quantity,
            ];
        }

        $addedProducts[] = "{$product->name} x{$quantity}";
    }

    $conversation->updateCart($cart);

    if (empty($addedProducts)) {
        return "❌ Tidak ada produk yang ditambahkan.";
    }

    $response = "✅ Berhasil menambahkan ke keranjang!\n\n";
    foreach ($addedProducts as $p) {
        $response .= "📦 {$p}\n";
    }
    $response .= "\nKetik 'lihat keranjang' untuk melihat ringkasan pesanan.";

    return $response;
}

function getCartSummary(AiAgentConversation $conversation, int $userId): string
{
    $cart = $conversation->getCart();

    if (empty($cart)) {
        return "🛒 Keranjang belanja kosong.";
    }

    $response = "🛒 Keranjang Belanja:\n\n";
    $subtotal = 0;

    foreach ($cart as $item) {
        $itemTotal = $item['price'] * $item['quantity'];
        $subtotal += $itemTotal;
        $response .= "📦 {$item['product_name']} x{$item['quantity']}\n";
        $response .= "   Rp " . number_format($item['price'], 0, ',', '.') . " × {$item['quantity']} = Rp " . number_format($itemTotal, 0, ',', '.') . "\n\n";
    }

    $response .= "─────────────────\n";
    $response .= "💰 Total: Rp " . number_format($subtotal, 0, ',', '.') . "\n\n";
    $response .= "Ketik 'konfirmasi' untuk membuat pesanan.";

    return $response;
}
