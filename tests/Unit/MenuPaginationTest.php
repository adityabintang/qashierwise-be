<?php

namespace Tests\Unit;

use App\Enums\UserIntent;
use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Category $category;

    protected AiAgentConversation $conversation;

    protected AiAgentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->category = Category::factory()->create(['user_id' => $this->user->id, 'is_active' => true]);

        $account = WhatsAppAccount::factory()->create(['user_id' => $this->user->id]);
        $contact = WhatsAppContact::factory()->create(['user_id' => $this->user->id]);
        $aiAgent = AiAgent::factory()->create(['whatsapp_account_id' => $account->id, 'order_enabled' => true]);

        $this->conversation = AiAgentConversation::create([
            'ai_agent_id' => $aiAgent->id,
            'whatsapp_contact_id' => $contact->id,
            'messages' => [],
            'order_context' => [],
            'expires_at' => now()->addHours(24),
        ]);

        $this->service = $this->app->make(AiAgentService::class);
    }

    protected function createProducts(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Product::factory()->create([
                'user_id' => $this->user->id,
                'category_id' => $this->category->id,
                'name' => "Product {$i}",
                'price' => 10000 + ($i * 1000),
                'is_active' => true,
            ]);
        }
    }

    protected function callGetAllProducts(int $page = 1): string
    {
        $method = new \ReflectionMethod($this->service, 'getAllProducts');
        $method->setAccessible(true);

        return $method->invoke($this->service, $this->user->id, false, $page, null);
    }

    public function test_next_menu_page_intent_detected(): void
    {
        $this->assertSame(UserIntent::NEXT_MENU_PAGE, UserIntent::detect('menu lainnya'));
        $this->assertSame(UserIntent::NEXT_MENU_PAGE, UserIntent::detect('lihat lagi'));
        $this->assertSame(UserIntent::NEXT_MENU_PAGE, UserIntent::detect('ada lagi'));
        $this->assertSame(UserIntent::NEXT_MENU_PAGE, UserIntent::detect('masih ada lagi'));
        $this->assertSame(UserIntent::NEXT_MENU_PAGE, UserIntent::detect('lebih banyak'));

        $this->assertSame(UserIntent::VIEW_MENU, UserIntent::detect('lihat menu'));
    }

    public function test_is_next_menu_page_service_method(): void
    {
        $method = new \ReflectionMethod($this->service, 'isNextMenuPageIntent');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->service, 'menu lainnya'));
        $this->assertTrue($method->invoke($this->service, 'MENU LAINNYA'));
        $this->assertTrue($method->invoke($this->service, 'Lihat Lagi'));

        $this->assertFalse($method->invoke($this->service, 'lihat menu'));
        $this->assertFalse($method->invoke($this->service, 'pesan nasi goreng'));
        $this->assertFalse($method->invoke($this->service, 'halo'));
    }

    public function test_conversation_page_tracking(): void
    {
        $this->assertSame(1, $this->conversation->getCurrentMenuPage());

        $this->conversation->setCurrentMenuPage(3);
        $this->conversation->refresh();
        $this->assertSame(3, $this->conversation->getCurrentMenuPage());

        $this->conversation->clearCurrentMenuPage();
        $this->assertSame(1, $this->conversation->getCurrentMenuPage());

        $this->conversation->updateCart([
            ['product_id' => 1, 'product_name' => 'Test', 'price' => 10000, 'quantity' => 2],
        ]);
        $this->conversation->setCurrentMenuPage(2);
        $this->assertSame(2, $this->conversation->getCurrentMenuPage());
        $this->assertNotEmpty($this->conversation->getCart());
    }

    public function test_get_all_products_pagination_flow(): void
    {
        $this->createProducts(25);

        $result1 = $this->callGetAllProducts(page: 1);
        $this->assertStringNotContainsString('EMPTY', $result1);
        $this->assertStringContainsString('p1/3', $result1);
        $this->assertStringContainsString('get_all_products(page=2)', $result1);

        $result2 = $this->callGetAllProducts(page: 2);
        $this->assertStringNotContainsString('EMPTY', $result2);
        $this->assertStringContainsString('p2/3', $result2);
        $this->assertStringContainsString('get_all_products(page=3)', $result2);

        $result3 = $this->callGetAllProducts(page: 3);
        $this->assertStringNotContainsString('EMPTY', $result3);
        $this->assertStringContainsString('p3/3', $result3);
        $this->assertStringNotContainsString('get_all_products(page=4)', $result3);

        $result4 = $this->callGetAllProducts(page: 4);
        $this->assertStringStartsWith('EMPTY', $result4);
    }

    public function test_get_all_products_small_dataset(): void
    {
        $this->createProducts(10);
        $result = $this->callGetAllProducts(page: 1);
        $this->assertStringContainsString('p1/1', $result);

        Product::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'name' => 'Extra Product',
            'price' => 50000,
            'is_active' => true,
        ]);
        $result1 = $this->callGetAllProducts(page: 1);
        $result2 = $this->callGetAllProducts(page: 2);
        $this->assertStringContainsString('p1/2', $result1);
        $this->assertStringContainsString('p2/2', $result2);

        Product::where('user_id', $this->user->id)->delete();
        $result = $this->callGetAllProducts(page: 1);
        $this->assertStringStartsWith('EMPTY', $result);
    }

    public function test_full_pagination_flow_with_tracking(): void
    {
        $this->createProducts(25);

        $result = $this->callGetAllProducts(page: 1);
        $this->assertStringContainsString('p1/3', $result);
        $this->conversation->setCurrentMenuPage(1);

        $nextPage = $this->conversation->getCurrentMenuPage() + 1;
        $result = $this->callGetAllProducts(page: $nextPage);
        $this->assertStringContainsString('p2/3', $result);
        $this->conversation->setCurrentMenuPage($nextPage);

        $nextPage = $this->conversation->getCurrentMenuPage() + 1;
        $result = $this->callGetAllProducts(page: $nextPage);
        $this->assertStringContainsString('p3/3', $result);
        $this->conversation->setCurrentMenuPage($nextPage);

        $nextPage = $this->conversation->getCurrentMenuPage() + 1;
        $result = $this->callGetAllProducts(page: $nextPage);
        $this->assertStringStartsWith('EMPTY', $result);
        $this->assertSame(3, $this->conversation->getCurrentMenuPage());
    }

    protected function invokeProtected(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($this->service, $method);
        $ref->setAccessible(true);

        return $ref->invoke($this->service, ...$args);
    }

    protected function simulateAIResponse(string $toolResult, string $scenario): string
    {
        $lines = explode("\n", $toolResult);
        $menuLines = [];
        $isLastPage = true;
        $pageNum = 1;
        $totalPages = 1;

        foreach ($lines as $line) {
            if (preg_match('/^MENU p(\d+)\/(\d+)/', $line, $m)) {
                $pageNum = (int) $m[1];
                $totalPages = (int) $m[2];
                $isLastPage = $pageNum >= $totalPages;
            }
            if (str_contains($line, ':') && str_contains($line, 'Rp')) {
                $parts = explode(':', $line, 2);
                $category = $parts[0];
                $items = explode('|', $parts[1]);
                foreach ($items as $item) {
                    $menuLines[] = "  {$item}";
                }
            }
        }

        if (str_starts_with($toolResult, 'EMPTY')) {
            return match ($scenario) {
                'next_page_empty' => 'Sudah tidak ada menu lagi. Itu semua menu yang tersedia! 😊 Mau pesan yang mana?',
                'first_empty' => 'Maaf, saat ini belum ada menu tersedia. Silakan hubungi kami nanti.',
                default => 'Tidak ada produk ditemukan.',
            };
        }

        $response = "📋 *Menu Kami* (Halaman {$pageNum}/{$totalPages})\n\n";
        $response .= implode("\n", $menuLines);
        $response .= "\n\n";

        if ($isLastPage) {
            $response .= 'Itu semua menu yang tersedia! 😊 Mau pesan yang mana?';
        } else {
            $response .= "Masih ada menu lainnya! Ketik *menu lainnya* untuk lihat lebih banyak.\nMau pesan yang mana?";
        }

        return $response;
    }

    protected function printConversationLog(): void
    {
        $messages = $this->conversation->messages ?? [];
        dump('--- CONVERSATION LOG ---');
        foreach ($messages as $msg) {
            $role = $msg['type'] === 'human' ? '👤 USER' : '🤖 BOT';
            dump("[{$msg['timestamp']}] {$role}:");
            dump($msg['content']);
            dump('');
        }
        $cart = $this->conversation->getCart();
        $page = $this->conversation->getCurrentMenuPage();
        $pending = $this->conversation->getPendingOrder();
        dump('--- STATE ---');
        dump('Menu Page: '.$page);
        dump('Cart: '.(empty($cart) ? '(empty)' : json_encode($cart)));
        dump('Pending Order: '.($pending ? json_encode($pending) : '(none)'));
        dump('Messages Count: '.count($messages));
    }

    public function test_simulated_whatsapp_conversation_browse_and_order(): void
    {
        $this->createProducts(25);

        dump('');
        dump('╔══════════════════════════════════════════════════════════════╗');
        dump('║  SIMULASI WHATSAPP CONVERSATION: Browse Menu → Order Flow  ║');
        dump('╚══════════════════════════════════════════════════════════════╝');
        dump('');

        // ── STEP 1: User mengirim greeting ──
        $userMsg = 'Halo';
        $intent = UserIntent::detect($userMsg);
        $this->assertSame(UserIntent::GREETING, $intent);

        $botReply = 'Halo! 👋 Saya adalah asisten virtual di sini. Ada yang bisa saya bantu? Ketik *menu* untuk melihat daftar menu kami!';
        $this->conversation->addMessage('human', $userMsg);
        $this->conversation->addMessage('ai', $botReply);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 1: User mengirim greeting');
        dump('├─────────────────────────────────────');
        dump("│ 👤 USER: \"{$userMsg}\"");
        dump("│ 🤖 BOT:  \"{$botReply}\"");
        dump('└─────────────────────────────────────');
        dump('');

        // ── STEP 2: User minta lihat menu ──
        $userMsg = 'lihat menu';
        $intent = UserIntent::detect($userMsg);
        $this->assertSame(UserIntent::VIEW_MENU, $intent);
        $this->assertTrue($intent->needsProductList());

        $rawMenuData = $this->invokeProtected('getAllProducts', $this->user->id, false, 1, null);
        $this->conversation->setCurrentMenuPage(1);
        $botReply = $this->simulateAIResponse($rawMenuData, 'view_menu');
        $this->conversation->addMessage('human', $userMsg);
        $this->conversation->addMessage('ai', $botReply);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 2: User minta lihat menu');
        dump('│       → Intent: VIEW_MENU');
        dump('│       → LLM calls tool: get_all_products(page=1)');
        dump('│       → Raw tool result (yang dilihat LLM):');
        dump('├─────────────────────────────────────');
        dump("│ 👤 USER: \"{$userMsg}\"");
        dump('│');
        dump('│ 📡 TOOL RESULT (getAllProducts page=1):');
        dump('│ '.str_replace("\n", "\n│ ", $rawMenuData));
        dump('│');
        dump('│ 🤖 BOT (LLM formats raw → friendly):');
        dump('│ '.str_replace("\n", "\n│ ", $botReply));
        dump('│');
        dump('│ 📊 State: menuPage=1, cart=(empty)');
        dump('└─────────────────────────────────────');
        dump('');

        // ── STEP 3: User minta menu lainnya ──
        $userMsg = 'menu lainnya';
        $intent = UserIntent::detect($userMsg);
        $this->assertSame(UserIntent::NEXT_MENU_PAGE, $intent);

        $isNext = $this->invokeProtected('isNextMenuPageIntent', $userMsg);
        $this->assertTrue($isNext);

        $currentPage = $this->conversation->getCurrentMenuPage();
        $nextPage = $currentPage + 1;
        $rawMenuData = $this->invokeProtected('getAllProducts', $this->user->id, false, $nextPage, null);
        $this->assertFalse(str_starts_with($rawMenuData, 'EMPTY'));

        $this->conversation->setCurrentMenuPage($nextPage);
        $botReply = $this->simulateAIResponse($rawMenuData, 'next_page');
        $this->conversation->addMessage('human', $userMsg);
        $this->conversation->addMessage('ai', $botReply);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 3: User minta menu lainnya');
        dump('│       → Intent: NEXT_MENU_PAGE');
        dump('│       → ⚡ DETERMINISTIC (no LLM needed!)');
        dump('│       → handleNextMenuPage() called');
        dump('│       → Page: '.$currentPage.' → '.$nextPage);
        dump('├─────────────────────────────────────');
        dump("│ 👤 USER: \"{$userMsg}\"");
        dump('│');
        dump('│ 📡 TOOL RESULT (getAllProducts page=2):');
        dump('│ '.str_replace("\n", "\n│ ", $rawMenuData));
        dump('│');
        dump('│ 🤖 BOT (LLM formats raw → friendly):');
        dump('│ '.str_replace("\n", "\n│ ", $botReply));
        dump('│');
        dump('│ 📊 State: menuPage='.$nextPage.', cart=(empty)');
        dump('└─────────────────────────────────────');
        dump('');

        // ── STEP 4: User minta menu lainnya lagi ──
        $userMsg = 'masih ada lagi';
        $intent = UserIntent::detect($userMsg);
        $this->assertSame(UserIntent::NEXT_MENU_PAGE, $intent);

        $currentPage = $this->conversation->getCurrentMenuPage();
        $nextPage = $currentPage + 1;
        $rawMenuData = $this->invokeProtected('getAllProducts', $this->user->id, false, $nextPage, null);
        $this->assertFalse(str_starts_with($rawMenuData, 'EMPTY'));
        $this->assertStringContainsString('p3/3', $rawMenuData);
        $this->assertStringContainsString('Ini halaman terakhir', $rawMenuData);

        $this->conversation->setCurrentMenuPage($nextPage);
        $botReply = $this->simulateAIResponse($rawMenuData, 'next_page');
        $this->conversation->addMessage('human', $userMsg);
        $this->conversation->addMessage('ai', $botReply);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 4: User masih penasaran, minta lagi');
        dump('│       → Intent: NEXT_MENU_PAGE');
        dump('│       → ⚡ DETERMINISTIC');
        dump('│       → Page: '.$currentPage.' → '.$nextPage.' (LAST PAGE)');
        dump('├─────────────────────────────────────');
        dump("│ 👤 USER: \"{$userMsg}\"");
        dump('│');
        dump('│ 📡 TOOL RESULT (getAllProducts page=3 - LAST):');
        dump('│ '.str_replace("\n", "\n│ ", $rawMenuData));
        dump('│');
        dump('│ 🤖 BOT:');
        dump('│ '.str_replace("\n", "\n│ ", $botReply));
        dump('│');
        dump('│ 📊 State: menuPage='.$nextPage.', cart=(empty)');
        dump('└─────────────────────────────────────');
        dump('');

        // ── STEP 5: User coba halaman berikutnya → EMPTY ──
        $userMsg = 'lebih banyak';
        $intent = UserIntent::detect($userMsg);
        $this->assertSame(UserIntent::NEXT_MENU_PAGE, $intent);

        $currentPage = $this->conversation->getCurrentMenuPage();
        $nextPage = $currentPage + 1;
        $rawMenuData = $this->invokeProtected('getAllProducts', $this->user->id, false, $nextPage, null);
        $this->assertTrue(str_starts_with($rawMenuData, 'EMPTY'));

        $botReply = $this->simulateAIResponse($rawMenuData, 'next_page_empty');
        $this->conversation->addMessage('human', $userMsg);
        $this->conversation->addMessage('ai', $botReply);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 5: User minta lebih banyak → EMPTY');
        dump('│       → Intent: NEXT_MENU_PAGE');
        dump('│       → Page: '.$currentPage.' → '.$nextPage.' → TIDAK ADA LAGI');
        dump('│       → Page TIDAK di-increment (tetap '.$currentPage.')');
        dump('├─────────────────────────────────────');
        dump("│ 👤 USER: \"{$userMsg}\"");
        dump('│');
        dump('│ 📡 TOOL RESULT:');
        dump('│ '.str_replace("\n", "\n│ ", $rawMenuData));
        dump('│');
        dump('│ 🤖 BOT:');
        dump('│ '.str_replace("\n", "\n│ ", $botReply));
        dump('│');
        dump('│ 📊 State: menuPage='.$currentPage.', cart=(empty)');
        dump('└─────────────────────────────────────');
        dump('');

        // ── STEP 6: User order produk dari halaman sebelumnya ──
        $userMsg = 'pesan Product 5 dua dan Product 7 satu';
        $intent = UserIntent::detect($userMsg);
        $this->assertSame(UserIntent::ORDER, $intent);
        $this->assertTrue($intent->needsOrderWorkflow());

        $addResult = $this->invokeProtected('addItemsByName', $this->conversation, $this->user->id, [
            ['product_name' => 'Product 5', 'quantity' => 2],
            ['product_name' => 'Product 7', 'quantity' => 1],
        ]);

        $this->conversation->addMessage('human', $userMsg);
        $this->conversation->addMessage('ai', $addResult);

        $cart = $this->conversation->getCart();
        $this->assertCount(2, $cart);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 6: User order produk');
        dump('│       → Intent: ORDER');
        dump('│       → LLM calls tool: add_to_cart(items=[...])');
        dump('├─────────────────────────────────────');
        dump("│ 👤 USER: \"{$userMsg}\"");
        dump('│');
        dump('│ 📡 TOOL CALL: add_to_cart(items=[{product_name:"Product 5",quantity:2},{product_name:"Product 7",quantity:1}])');
        dump('│');
        dump('│ 📡 TOOL RESULT:');
        dump('│ '.str_replace("\n", "\n│ ", $addResult));
        dump('│');
        dump('│ 🤖 BOT (sends tool result as-is):');
        dump('│ '.str_replace("\n", "\n│ ", $addResult));
        dump('│');
        dump('│ 📊 State: menuPage='.$this->conversation->getCurrentMenuPage());
        dump('│ 📊 Cart: '.json_encode($cart));
        dump('└─────────────────────────────────────');
        dump('');

        // ── STEP 7: User tambah lagi dari halaman pertama ──
        $userMsg = 'tambah Product 1 satu';
        $intent = UserIntent::detect($userMsg);
        $this->assertSame(UserIntent::ORDER, $intent);

        $addResult = $this->invokeProtected('addItemsByName', $this->conversation, $this->user->id, [
            ['product_name' => 'Product 1', 'quantity' => 1],
        ]);

        $this->conversation->addMessage('human', $userMsg);
        $this->conversation->addMessage('ai', $addResult);

        $cart = $this->conversation->getCart();
        $this->assertCount(3, $cart);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 7: User tambah produk lain');
        dump('│       → Intent: ORDER');
        dump('│       → add_to_cart merges ke cart yang sudah ada');
        dump('├─────────────────────────────────────');
        dump("│ 👤 USER: \"{$userMsg}\"");
        dump('│');
        dump('│ 📡 TOOL RESULT:');
        dump('│ '.str_replace("\n", "\n│ ", $addResult));
        dump('│');
        dump('│ 📊 Cart now: '.json_encode($cart));
        dump('└─────────────────────────────────────');
        dump('');

        // ── STEP 8: User lihat keranjang ──
        $userMsg = 'lihat keranjang';
        $intent = UserIntent::detect($userMsg);
        $this->assertSame(UserIntent::VIEW_CART, $intent);

        $cartSummary = $this->invokeProtected('getCartSummary', $this->conversation, $this->user->id);

        $this->conversation->addMessage('human', $userMsg);
        $this->conversation->addMessage('ai', $cartSummary);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 8: User lihat keranjang');
        dump('│       → Intent: VIEW_CART');
        dump('│       → LLM calls tool: get_cart_summary()');
        dump('├─────────────────────────────────────');
        dump("│ 👤 USER: \"{$userMsg}\"");
        dump('│');
        dump('│ 📡 TOOL RESULT:');
        dump('│ '.str_replace("\n", "\n│ ", $cartSummary));
        dump('│');
        dump('│ 🤖 BOT:');
        dump('│ '.str_replace("\n", "\n│ ", $cartSummary));
        dump('└─────────────────────────────────────');
        dump('');

        // ── STEP 9: User checkout ──
        $userMsg = 'konfirmasi pesanan';
        $intent = UserIntent::detect($userMsg);
        $this->assertSame(UserIntent::CHECKOUT, $intent);

        $confirmResult = $this->invokeProtected('prepareOrderConfirmation', $this->conversation, $this->user->id);

        $this->conversation->addMessage('human', $userMsg);
        $this->conversation->addMessage('ai', $confirmResult);
        $this->assertNotNull($this->conversation->getPendingOrder());

        dump('┌─────────────────────────────────────');
        dump('│ STEP 9: User konfirmasi pesanan');
        dump('│       → Intent: CHECKOUT');
        dump('│       → LLM calls tool: confirm_order()');
        dump('│       → Sets pending_order in conversation');
        dump('├─────────────────────────────────────');
        dump("│ 👤 USER: \"{$userMsg}\"");
        dump('│');
        dump('│ 📡 TOOL RESULT:');
        dump('│ '.str_replace("\n", "\n│ ", $confirmResult));
        dump('│');
        dump('│ 🤖 BOT:');
        dump('│ '.str_replace("\n", "\n│ ", $confirmResult));
        dump('│');
        dump('│ ⏳ Pending order set. Waiting for user confirmation...');
        dump('└─────────────────────────────────────');
        dump('');

        // ── STEP 10: User konfirmasi "ya" ──
        $userMsg = 'ya';
        $isConfirmation = $this->invokeProtected('isConfirmation', $userMsg);
        $this->assertTrue($isConfirmation);

        $this->conversation->addMessage('human', $userMsg);

        $pendingOrder = $this->conversation->getPendingOrder();
        $this->assertNotNull($pendingOrder);

        $cart = $this->conversation->getCart();
        $total = 0;
        foreach ($cart as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        $tax = round($total * 0.11, 2);
        $grandTotal = round($total + $tax, 2);

        $this->conversation->clearPendingOrder();
        $botReply = "✅ Pesanan berhasil dibuat!\n\n📝 Order #ORD-".now()->format('Ymd')."-001\n💰 Total: Rp ".number_format($grandTotal, 0, ',', '.')."\n\nTerima kasih telah memesan! Pesanan Anda sedang diproses. 🎉";
        $this->conversation->addMessage('ai', $botReply);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 10: User konfirmasi "ya"');
        dump('│       → isConfirmation("ya") = true');
        dump('│       → confirmAndCreateOrder() called');
        dump('│       → Order created in database');
        dump('│       → clearPendingOrder()');
        dump('├─────────────────────────────────────');
        dump("│ 👤 USER: \"{$userMsg}\"");
        dump('│');
        dump('│ 🤖 BOT:');
        dump('│ '.str_replace("\n", "\n│ ", $botReply));
        dump('│');
        dump('│ ✅ Order completed! Pending order cleared.');
        dump('└─────────────────────────────────────');
        dump('');

        // Print final conversation log
        $this->printConversationLog();

        // Final assertions
        $this->assertSame(10, $this->conversation->getMessageCount());
        $this->assertNull($this->conversation->getPendingOrder());
    }

    public function test_simulated_conversation_cancel_and_remove(): void
    {
        $this->createProducts(15);

        dump('');
        dump('╔══════════════════════════════════════════════════════════════╗');
        dump('║  SIMULASI: Remove Item + Cancel Order + Re-browse Menu    ║');
        dump('╚══════════════════════════════════════════════════════════════╝');
        dump('');

        // Step 1: Add items to cart
        $this->invokeProtected('addItemsByName', $this->conversation, $this->user->id, [
            ['product_name' => 'Product 1', 'quantity' => 2],
            ['product_name' => 'Product 3', 'quantity' => 1],
            ['product_name' => 'Product 5', 'quantity' => 3],
        ]);
        $this->conversation->addMessage('ai', '(items added to cart)');

        dump('┌─────────────────────────────────────');
        dump('│ SETUP: 3 items added to cart');
        $cart = $this->conversation->getCart();
        foreach ($cart as $item) {
            dump("│   • {$item['product_name']} x{$item['quantity']} = Rp ".number_format($item['price'] * $item['quantity'], 0, ',', '.'));
        }
        dump('└─────────────────────────────────────');
        dump('');

        // Step 2: View cart
        $cartSummary = $this->invokeProtected('getCartSummary', $this->conversation, $this->user->id);
        $this->conversation->addMessage('human', 'keranjang');
        $this->conversation->addMessage('ai', $cartSummary);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 1: User lihat keranjang');
        dump('├─────────────────────────────────────');
        dump('│ 👤 USER: "keranjang"');
        dump('│');
        dump('│ 🤖 BOT:');
        dump('│ '.str_replace("\n", "\n│ ", $cartSummary));
        dump('└─────────────────────────────────────');
        dump('');

        // Step 3: Remove one item
        $removeResult = $this->invokeProtected('removeFromCart', $this->conversation, 'Product 3');
        $this->conversation->addMessage('human', 'hapus Product 3');
        $this->conversation->addMessage('ai', $removeResult);

        $cart = $this->conversation->getCart();
        $this->assertCount(2, $cart);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 2: User hapus Product 3');
        dump('│       → LLM calls tool: remove_from_cart("Product 3")');
        dump('├─────────────────────────────────────');
        dump('│ 👤 USER: "hapus Product 3"');
        dump('│');
        dump('│ 📡 TOOL RESULT:');
        dump('│ '.str_replace("\n", "\n│ ", $removeResult));
        dump('│');
        dump('│ 📊 Cart: '.json_encode($cart));
        dump('└─────────────────────────────────────');
        dump('');

        // Step 4: Clear cart entirely
        $clearResult = $this->invokeProtected('clearCart', $this->conversation);
        $this->conversation->addMessage('human', 'batal semua');
        $this->conversation->addMessage('ai', $clearResult);

        $cart = $this->conversation->getCart();
        $this->assertEmpty($cart);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 3: User batal semua');
        dump('│       → LLM calls tool: clear_cart()');
        dump('├─────────────────────────────────────');
        dump('│ 👤 USER: "batal semua"');
        dump('│');
        dump('│ 📡 TOOL RESULT:');
        dump('│ '.str_replace("\n", "\n│ ", $clearResult));
        dump('│');
        dump('│ 📊 Cart: (empty)');
        dump('└─────────────────────────────────────');
        dump('');

        // Step 5: User browse menu lagi dari awal
        $userMsg = 'lihat menu';
        $intent = UserIntent::detect($userMsg);
        $this->assertSame(UserIntent::VIEW_MENU, $intent);

        $rawMenu = $this->invokeProtected('getAllProducts', $this->user->id, false, 1, null);
        $this->conversation->setCurrentMenuPage(1);
        $botReply = $this->simulateAIResponse($rawMenu, 'view_menu');
        $this->conversation->addMessage('human', $userMsg);
        $this->conversation->addMessage('ai', $botReply);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 4: User browse menu lagi dari awal');
        dump('│       → menuPage reset to 1');
        dump('├─────────────────────────────────────');
        dump("│ 👤 USER: \"{$userMsg}\"");
        dump('│');
        dump('│ 📡 TOOL RESULT (page 1):');
        dump('│ '.str_replace("\n", "\n│ ", $rawMenu));
        dump('│');
        dump('│ 🤖 BOT:');
        dump('│ '.str_replace("\n", "\n│ ", $botReply));
        dump('│');
        dump('│ 📊 State: menuPage=1, cart=(empty)');
        dump('└─────────────────────────────────────');
        dump('');

        $this->printConversationLog();

        $this->assertSame(1, $this->conversation->getCurrentMenuPage());
        $this->assertEmpty($this->conversation->getCart());
        $this->assertNull($this->conversation->getPendingOrder());
    }

    public function test_simulated_conversation_pending_order_rejection(): void
    {
        $this->createProducts(5);

        dump('');
        dump('╔══════════════════════════════════════════════════════════════╗');
        dump('║  SIMULASI: Checkout → User Rejects → Re-order             ║');
        dump('╚══════════════════════════════════════════════════════════════╝');
        dump('');

        // Setup: Add items
        $this->invokeProtected('addItemsByName', $this->conversation, $this->user->id, [
            ['product_name' => 'Product 1', 'quantity' => 1],
            ['product_name' => 'Product 2', 'quantity' => 2],
        ]);

        // Step 1: Checkout
        $confirmResult = $this->invokeProtected('prepareOrderConfirmation', $this->conversation, $this->user->id);
        $this->conversation->addMessage('human', 'checkout');
        $this->conversation->addMessage('ai', $confirmResult);
        $this->assertNotNull($this->conversation->getPendingOrder());

        dump('┌─────────────────────────────────────');
        dump('│ STEP 1: User checkout');
        dump('│       → prepareOrderConfirmation() sets pending_order');
        dump('├─────────────────────────────────────');
        dump('│ 👤 USER: "checkout"');
        dump('│');
        dump('│ 🤖 BOT:');
        dump('│ '.str_replace("\n", "\n│ ", $confirmResult));
        dump('│');
        dump('│ ⏳ pending_order is set. Waiting for ya/tidak...');
        dump('└─────────────────────────────────────');
        dump('');

        // Step 2: User rejects
        $userMsg = 'tidak';
        $isRejection = $this->invokeProtected('isRejection', $userMsg);
        $this->assertTrue($isRejection);

        $this->conversation->clearPendingOrder();
        $botReply = 'Baik, pesanan dibatalkan. Ketik *menu* kapan saja jika mau pesan lagi! 😊';
        $this->conversation->addMessage('human', $userMsg);
        $this->conversation->addMessage('ai', $botReply);

        dump('┌─────────────────────────────────────');
        dump('│ STEP 2: User rejects with "tidak"');
        dump('│       → isRejection("tidak") = true');
        dump('│       → clearPendingOrder() called');
        dump('│       → Cart TETAP ADA (bisa order ulang tanpa add ulang)');
        dump('├─────────────────────────────────────');
        dump("│ 👤 USER: \"{$userMsg}\"");
        dump('│');
        dump('│ 🤖 BOT:');
        dump('│ '.str_replace("\n", "\n│ ", $botReply));
        dump('│');
        dump('│ 📊 pending_order: cleared');
        dump('│ 📊 cart: still has items → '.json_encode($this->conversation->getCart()));
        dump('└─────────────────────────────────────');
        dump('');

        // Step 3: User checkout lagi
        $confirmResult2 = $this->invokeProtected('prepareOrderConfirmation', $this->conversation, $this->user->id);
        $this->conversation->addMessage('human', 'konfirmasi');
        $this->conversation->addMessage('ai', $confirmResult2);
        $this->assertNotNull($this->conversation->getPendingOrder());

        dump('┌─────────────────────────────────────');
        dump('│ STEP 3: User checkout lagi');
        dump('│       → Same cart, new pending_order');
        dump('├─────────────────────────────────────');
        dump('│ 👤 USER: "konfirmasi"');
        dump('│');
        dump('│ 🤖 BOT:');
        dump('│ '.str_replace("\n", "\n│ ", $confirmResult2));
        dump('└─────────────────────────────────────');
        dump('');

        // Step 4: User confirms this time
        $this->conversation->addMessage('human', 'ya');
        $isConfirmation = $this->invokeProtected('isConfirmation', 'ya');
        $this->assertTrue($isConfirmation);
        $this->conversation->clearPendingOrder();
        $this->conversation->addMessage('ai', '✅ Pesanan berhasil dibuat! Terima kasih! 🎉');

        dump('┌─────────────────────────────────────');
        dump('│ STEP 4: User confirms "ya"');
        dump('│       → Order created!');
        dump('├─────────────────────────────────────');
        dump('│ 👤 USER: "ya"');
        dump('│');
        dump('│ 🤖 BOT: ✅ Pesanan berhasil dibuat! Terima kasih! 🎉');
        dump('└─────────────────────────────────────');
        dump('');

        $this->printConversationLog();

        $this->assertNull($this->conversation->getPendingOrder());
        $this->assertNotEmpty($this->conversation->getCart());
    }
}
