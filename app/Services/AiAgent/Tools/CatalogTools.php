<?php

namespace App\Services\AiAgent\Tools;

use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Product;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgent\Reply\ReplySender;
use Sbsaga\Toon\Facades\Toon;

/**
 * Product/menu lookups exposed to the LLM as tools.
 *
 * Outputs are intentionally compact — LLM tokens cost money, and these
 * results are read by the model (not by humans) except for getAllProducts,
 * which doubles as the user-facing menu list when the agent forwards the
 * tool result verbatim.
 *
 * Search results use sentinels (FOUND / NOT_FOUND) so the LLM can branch
 * without parsing prose — see the "action:..." trailing line.
 */
class CatalogTools
{
    public function __construct(
        protected ReplySender $replySender,
    ) {}

    /**
     * Loose-match search by user text. Returns either:
     *   - "FOUND:..." with up to 10 items (TOON or compact format)
     *   - "NOT_FOUND..." with the original query for the LLM to relay
     *
     * Each item has an `[id]` the LLM uses with add_to_cart.
     */
    public function searchProducts(int $userId, string $query, bool $useToon = false): string
    {
        if (empty(trim($query))) {
            return 'Mohon berikan kata kunci pencarian produk.';
        }

        $cleanQuery = trim(strtolower($query));
        $keywords = explode(' ', $cleanQuery);

        $products = Product::where('user_id', $userId)
            ->where('is_active', true)
            ->with('category:id,name')
            ->where(function ($q) use ($cleanQuery, $keywords) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$cleanQuery}%"])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$cleanQuery}%"]);

                foreach ($keywords as $keyword) {
                    if (strlen($keyword) >= 2) {
                        $q->orWhereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"]);
                    }
                }
            })
            ->orderBy('category_id', 'asc')
            ->orderBy('name', 'asc')
            ->limit(10)
            ->get(['id', 'name', 'price', 'stock_quantity', 'description', 'category_id']);

        if ($products->isEmpty()) {
            return "NOT_FOUND\nquery:{$query}\naction:katakan tidak tersedia, jangan sebutkan produk ini";
        }

        $groupedProducts = $products->groupBy(fn ($p) => $p->category?->name ?? 'Lainnya');

        if ($useToon) {
            $categorizedArray = [];
            foreach ($groupedProducts as $categoryName => $categoryProducts) {
                $categorizedArray[$categoryName] = $categoryProducts->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price,
                    'stock' => $p->stock_quantity,
                ])->toArray();
            }

            return "FOUND\n".Toon::convert(['results' => $categorizedArray])."\naction:panggil add_to_cart dengan ID di atas";
        }

        $items = [];
        foreach ($groupedProducts as $categoryProducts) {
            foreach ($categoryProducts as $product) {
                $items[] = "{$product->name}[{$product->id}]Rp".number_format($product->price, 0, ',', '.');
            }
        }

        return "FOUND:{$query}|".implode('|', $items).'|ACT:add_to_cart(id,qty)';
    }

    /**
     * Like searchProducts but for multiple queries in one call. Useful when
     * the customer orders several things in a single message — saves round
     * trips. Returns combined FOUND list + NOT_FOUND tail when applicable.
     */
    public function searchMultipleProducts(int $userId, array $queries, bool $useToon = false): string
    {
        if (empty($queries)) {
            return 'Mohon berikan kata kunci pencarian produk.';
        }

        $allResults = [];
        $notFound = [];

        foreach ($queries as $query) {
            if (empty(trim($query))) {
                continue;
            }

            $cleanQuery = trim(strtolower($query));
            $keywords = explode(' ', $cleanQuery);

            $products = Product::where('user_id', $userId)
                ->where('is_active', true)
                ->with('category:id,name')
                ->where(function ($q) use ($cleanQuery, $keywords) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$cleanQuery}%"])
                        ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$cleanQuery}%"]);

                    foreach ($keywords as $keyword) {
                        if (strlen($keyword) >= 2) {
                            $q->orWhereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"]);
                        }
                    }
                })
                ->limit(5)
                ->get(['id', 'name', 'price', 'stock_quantity', 'category_id']);

            if ($products->isEmpty()) {
                $notFound[] = $query;

                continue;
            }

            foreach ($products as $product) {
                if (! isset($allResults[$product->id])) {
                    $allResults[$product->id] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                        'stock' => $product->stock_quantity,
                        'category' => $product->category?->name ?? 'Lainnya',
                    ];
                }
            }
        }

        if (empty($allResults) && ! empty($notFound)) {
            return "NOT_FOUND\nqueries:".implode(',', $notFound)."\naction:katakan tidak tersedia";
        }

        $groupedResults = collect($allResults)->groupBy('category');

        if ($useToon) {
            $response = '';
            if (! empty($allResults)) {
                $categorizedArray = [];
                foreach ($groupedResults as $categoryName => $categoryProducts) {
                    $categorizedArray[$categoryName] = $categoryProducts->map(fn ($p) => [
                        'id' => $p['id'],
                        'name' => $p['name'],
                        'price' => $p['price'],
                        'stock' => $p['stock'],
                    ])->toArray();
                }
                $response .= "FOUND\n".Toon::convert(['results' => $categorizedArray]);
            }
            if (! empty($notFound)) {
                $response .= "\nNOT_FOUND[".count($notFound).']: '.implode(',', $notFound);
            }
            $response .= "\naction:panggil add_to_cart dengan ID ditemukan, jangan sebutkan yang tidak ada";

            return $response;
        }

        $items = [];
        foreach ($groupedResults as $categoryProducts) {
            foreach ($categoryProducts as $product) {
                $items[] = "{$product['name']}[{$product['id']}]Rp".number_format($product['price'], 0, ',', '.');
            }
        }

        $response = 'FOUND:'.implode('|', $items);
        if (! empty($notFound)) {
            $response .= '|NOTFOUND:'.implode(',', $notFound);
        }
        $response .= '|ACT:add_to_cart(id,qty)';

        return $response;
    }

    /**
     * Paginated, user-facing menu list. 10 items per page, grouped by
     * category, with a "next page" hint at the bottom when applicable.
     * `EMPTY\n...` sentinel for "no products on this page".
     */
    public function getAllProducts(int $userId, bool $useToon = false, int $page = 1, ?string $search = null): string
    {
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $query = Product::where('user_id', $userId)->where('is_active', true);

        if ($search) {
            $cleanSearch = trim(strtolower($search));
            $query->where(function ($q) use ($cleanSearch) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$cleanSearch}%"]);
            });
        }

        $totalProducts = $query->count();
        $totalPages = max(1, ceil($totalProducts / $perPage));

        $products = $query->with('category:id,name')
            ->orderBy('category_id', 'asc')
            ->orderBy('name', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get(['id', 'name', 'price', 'stock_quantity', 'category_id']);

        if ($products->isEmpty()) {
            return $page > 1
                ? "EMPTY\nTidak ada menu lagi di halaman ini."
                : "EMPTY\nMaaf, belum ada menu tersedia saat ini.";
        }

        $groupedProducts = $products->groupBy(fn ($p) => $p->category?->name ?? 'Lainnya');

        $lines = [];
        $lines[] = "📋 **DAFTAR MENU** (Halaman {$page}/{$totalPages})";
        $lines[] = str_repeat('─', 30);

        foreach ($groupedProducts as $categoryName => $categoryProducts) {
            $lines[] = "\n🏷️ **{$categoryName}**";
            foreach ($categoryProducts as $product) {
                $price = number_format($product->price, 0, ',', '.');
                $stock = ($product->stock_quantity !== null && $product->stock_quantity <= 0) ? ' _(Habis)_' : '';
                $lines[] = "  • {$product->name} - Rp {$price}{$stock}";
            }
        }

        $lines[] = "\n".str_repeat('─', 30);

        if ($page < $totalPages) {
            $remaining = $totalProducts - ($page * $perPage);
            $lines[] = "📄 Masih ada {$remaining} menu lagi. Ketik \"menu lainnya\" untuk lihat selanjutnya.";
        } else {
            $lines[] = "✅ Total: {$totalProducts} menu tersedia.";
        }

        $lines[] = "\n💬 Mau pesan apa? Contoh: \"pesan nasi goreng 2 porsi\"";

        return implode("\n", $lines);
    }

    /**
     * Single-product detail card. Called by the LLM when the customer asks
     * about a specific product by id.
     */
    public function getProductDetails(int $userId, int $productId): string
    {
        if ($productId <= 0) {
            return 'Maaf, ID produk tidak valid. Mohon berikan ID produk yang benar.';
        }

        $product = Product::where('user_id', $userId)
            ->where('id', $productId)
            ->where('is_active', true)
            ->first();

        if (! $product) {
            return "Maaf, produk dengan ID {$productId} tidak ditemukan.";
        }

        $response = "📦 Detail Produk:\n\n";
        $response .= "Nama: {$product->name}\n";
        $response .= "SKU: {$product->sku}\n";
        $response .= 'Harga: Rp '.number_format($product->price, 0, ',', '.')."\n";
        $response .= "Stok: {$product->stock_quantity}\n";
        if ($product->description) {
            $response .= "Deskripsi: {$product->description}\n";
        }

        return $response;
    }

    /**
     * Deterministic pagination handler — called by AiAgentService when the
     * customer types "menu lainnya" / "selanjutnya". Lives here (not in the
     * router) because it shares formatting helpers with getAllProducts.
     */
    public function handleNextMenuPage(
        AiAgentConversation $conversation,
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
    ): void {
        $currentPage = $conversation->getCurrentMenuPage();
        $nextPage = $currentPage + 1;

        $result = $this->getAllProducts($account->user_id, false, $nextPage);

        if (str_starts_with($result, 'EMPTY')) {
            $msg = 'Sudah tidak ada menu lagi. Itu semua menu yang tersedia! 😊 Mau pesan yang mana?';
            $conversation->addMessage('human', 'menu lainnya');
            $conversation->addMessage('ai', $msg);
            $this->replySender->send($account, $contact->wa_id, $msg);

            return;
        }

        $conversation->setCurrentMenuPage($nextPage);
        $conversation->addMessage('human', 'menu lainnya');
        $conversation->addMessage('ai', $result);
        $this->replySender->send($account, $contact->wa_id, $result);
    }
}
