<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

class AiAgentService
{
    protected WhatsAppAccountService $whatsappAccountService;

    protected OrderService $orderService;

    public function __construct(
        WhatsAppAccountService $whatsappAccountService,
        OrderService $orderService
    ) {
        $this->whatsappAccountService = $whatsappAccountService;
        $this->orderService = $orderService;
    }

    /**
     * Process incoming message and generate AI response.
     */
    public function processMessage(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        string $messageText
    ): void {
        try {
            // Get AI Agent for this account
            $aiAgent = AiAgent::where('whatsapp_account_id', $account->id)
                ->where('is_active', true)
                ->first();

            if (! $aiAgent) {
                return;
            }

            // Get or create conversation
            $conversation = $this->getOrCreateConversation($aiAgent->id, $contact->id);

            // Check if there's a pending order confirmation
            $pendingOrder = $conversation->getPendingOrder();
            if ($pendingOrder && $this->isConfirmation($messageText)) {
                $this->confirmAndCreateOrder($conversation, $account, $contact);

                return;
            } elseif ($pendingOrder && $this->isRejection($messageText)) {
                $conversation->clearPendingOrder();
                $this->sendReply(
                    $account,
                    $contact->wa_id,
                    'Baik, pesanan dibatalkan. Ada yang bisa saya bantu lagi?'
                );

                return;
            }

            // Add user message to conversation
            $conversation->addMessage('user', $messageText);

            // Build system prompt
            $systemPrompt = $aiAgent->buildSystemPrompt($account->user_id);

            // Get conversation messages
            $messages = $conversation->messages ?? [];

            // Call LLM with tools
            $tools = $aiAgent->isOrderEnabled() ? $this->getToolDefinitions() : null;
            $response = $this->callLLM($systemPrompt, $messages, $tools);

            // Handle tool calls if present
            if (isset($response['tool_calls'])) {
                $this->handleToolCalls(
                    $response['tool_calls'],
                    $conversation,
                    $account,
                    $contact,
                    $aiAgent
                );

                return;
            }

            // Send AI response
            $assistantMessage = $response['content'] ?? 'Maaf, saya tidak mengerti. Bisa diulang?';
            $conversation->addMessage('assistant', $assistantMessage);
            $this->sendReply($account, $contact->wa_id, $assistantMessage);

        } catch (\Exception $e) {
            Log::error('AI Agent Error: '.$e->getMessage(), [
                'account_id' => $account->id,
                'contact_id' => $contact->id,
                'trace' => $e->getTraceAsString(),
            ]);

            // Send fallback message
            $this->sendReply(
                $account,
                $contact->wa_id,
                'Maaf, sistem sedang sibuk. Mohon coba lagi.'
            );
        }
    }

    /**
     * Get or create conversation for AI agent and contact.
     */
    public function getOrCreateConversation(int $aiAgentId, int $contactId): AiAgentConversation
    {
        // Clean up expired conversations first
        AiAgentConversation::where('expires_at', '<', now())->delete();

        $conversation = AiAgentConversation::where('ai_agent_id', $aiAgentId)
            ->where('whatsapp_contact_id', $contactId)
            ->first();

        if (! $conversation || $conversation->isExpired()) {
            $conversation = AiAgentConversation::create([
                'ai_agent_id' => $aiAgentId,
                'whatsapp_contact_id' => $contactId,
                'messages' => [],
                'order_context' => [],
                'expires_at' => now()->addHours(24),
            ]);
        }

        return $conversation;
    }

    /**
     * Call BytePlus ARK LLM API.
     */
    public function callLLM(string $systemPrompt, array $messages, ?array $tools = null): array
    {
        $config = config('services.byteplus_ark');
        $url = $config['base_url'].'/chat/completions';

        // Build messages array
        $llmMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($messages as $msg) {
            $llmMessages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        // Build request payload
        $payload = [
            'model' => $config['model'],
            'messages' => $llmMessages,
            'temperature' => 0.7,
            'max_tokens' => 500,
        ];

        if ($tools) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        // Make API call with retry logic (3 attempts with exponential backoff)
        $attempts = 0;
        $maxAttempts = 3;
        $backoff = [10, 30, 60]; // seconds

        while ($attempts < $maxAttempts) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$config['api_key'],
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post($url, $payload);

                if ($response->successful()) {
                    $data = $response->json();

                    // Extract response content or tool calls
                    $choice = $data['choices'][0] ?? null;
                    if (! $choice) {
                        throw new \Exception('Invalid LLM response format');
                    }

                    if (isset($choice['message']['tool_calls'])) {
                        return [
                            'tool_calls' => $choice['message']['tool_calls'],
                        ];
                    }

                    return [
                        'content' => $choice['message']['content'] ?? '',
                    ];
                }

                // If not successful, log and retry
                Log::warning('LLM API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            } catch (\Exception $e) {
                Log::error('LLM API exception', [
                    'attempt' => $attempts + 1,
                    'error' => $e->getMessage(),
                ]);
            }

            $attempts++;
            if ($attempts < $maxAttempts) {
                sleep($backoff[$attempts - 1]);
            }
        }

        // All attempts failed
        throw new \Exception('LLM API failed after '.$maxAttempts.' attempts');
    }

    /**
     * Get tool definitions for LLM function calling.
     */
    protected function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Cari produk berdasarkan nama atau SKU',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Kata kunci pencarian (nama produk atau SKU)',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_product_details',
                    'description' => 'Dapatkan detail lengkap produk berdasarkan ID',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'ID produk',
                            ],
                        ],
                        'required' => ['product_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_to_cart',
                    'description' => 'Tambahkan produk ke keranjang belanja',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'ID produk',
                            ],
                            'quantity' => [
                                'type' => 'integer',
                                'description' => 'Jumlah yang ingin dipesan',
                            ],
                        ],
                        'required' => ['product_id', 'quantity'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_cart_summary',
                    'description' => 'Dapatkan ringkasan keranjang belanja saat ini',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'confirm_order',
                    'description' => 'Konfirmasi dan buat pesanan dari keranjang',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Handle tool calls from LLM.
     */
    protected function handleToolCalls(
        array $toolCalls,
        AiAgentConversation $conversation,
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent
    ): void {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $functionName = $toolCall['function']['name'] ?? null;
            $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);

            $result = $this->executeToolCall($functionName, $arguments, $account->user_id, $conversation);
            $results[] = $result;
        }

        // Send combined result to user
        $responseMessage = implode("\n\n", array_filter($results));
        if ($responseMessage) {
            $conversation->addMessage('assistant', $responseMessage);
            $this->sendReply($account, $contact->wa_id, $responseMessage);
        }
    }

    /**
     * Execute a tool call.
     */
    protected function executeToolCall(
        string $functionName,
        array $arguments,
        int $userId,
        AiAgentConversation $conversation
    ): string {
        try {
            switch ($functionName) {
                case 'search_products':
                    return $this->searchProducts($userId, $arguments['query'] ?? '');

                case 'get_product_details':
                    return $this->getProductDetails($userId, $arguments['product_id'] ?? 0);

                case 'add_to_cart':
                    return $this->addToCart(
                        $conversation,
                        $userId,
                        $arguments['product_id'] ?? 0,
                        $arguments['quantity'] ?? 1
                    );

                case 'get_cart_summary':
                    return $this->getCartSummary($conversation, $userId);

                case 'confirm_order':
                    return $this->prepareOrderConfirmation($conversation, $userId);

                default:
                    return "Fungsi '{$functionName}' tidak dikenali.";
            }
        } catch (\Exception $e) {
            Log::error("Tool call error: {$functionName}", [
                'error' => $e->getMessage(),
                'arguments' => $arguments,
            ]);

            return 'Maaf, terjadi kesalahan saat memproses permintaan Anda.';
        }
    }

    /**
     * Search products by query.
     */
    protected function searchProducts(int $userId, string $query): string
    {
        if (empty($query)) {
            return 'Mohon berikan kata kunci pencarian produk.';
        }

        $products = Product::where('user_id', $userId)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'price', 'stock_quantity', 'description']);

        if ($products->isEmpty()) {
            return "Maaf, tidak ada produk yang ditemukan dengan kata kunci '{$query}'.";
        }

        $response = "Berikut produk yang saya temukan:\n\n";
        foreach ($products as $product) {
            $response .= "🔹 {$product->name} (ID: {$product->id})\n";
            $response .= '   Harga: Rp '.number_format($product->price, 0, ',', '.')."\n";
            $response .= "   Stok: {$product->stock_quantity}\n";
            if ($product->description) {
                $response .= "   {$product->description}\n";
            }
            $response .= "\n";
        }

        return $response;
    }

    /**
     * Get product details.
     */
    protected function getProductDetails(int $userId, int $productId): string
    {
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
     * Add product to cart.
     */
    protected function addToCart(
        AiAgentConversation $conversation,
        int $userId,
        int $productId,
        int $quantity
    ): string {
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

        // Check if product already in cart
        $found = false;
        foreach ($cart as &$item) {
            if ($item['product_id'] == $productId) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }

        if (! $found) {
            $cart[] = [
                'product_id' => $productId,
                'product_name' => $product->name,
                'price' => $product->price,
                'quantity' => $quantity,
            ];
        }

        $conversation->updateCart($cart);

        return "✅ {$product->name} x{$quantity} berhasil ditambahkan ke keranjang!\n\nKetik 'lihat keranjang' untuk melihat isi keranjang Anda.";
    }

    /**
     * Get cart summary.
     */
    protected function getCartSummary(AiAgentConversation $conversation, int $userId): string
    {
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

        $tax = $total * 0.11;
        $grandTotal = $total + $tax;

        $response .= 'Subtotal: Rp '.number_format($total, 0, ',', '.')."\n";
        $response .= 'Pajak (11%): Rp '.number_format($tax, 0, ',', '.')."\n";
        $response .= 'Total: Rp '.number_format($grandTotal, 0, ',', '.')."\n\n";
        $response .= "Ketik 'konfirmasi pesanan' untuk melanjutkan checkout.";

        return $response;
    }

    /**
     * Prepare order confirmation.
     */
    protected function prepareOrderConfirmation(AiAgentConversation $conversation, int $userId): string
    {
        $cart = $conversation->getCart();

        if (empty($cart)) {
            return 'Keranjang belanja Anda masih kosong. Silakan tambahkan produk terlebih dahulu.';
        }

        // Save to pending order
        $conversation->setPendingOrder($cart);

        // Build confirmation message
        $response = "📝 Konfirmasi Pesanan:\n\n";
        $total = 0;

        foreach ($cart as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $total += $subtotal;

            $response .= "• {$item['product_name']} x{$item['quantity']}\n";
            $response .= '  Rp '.number_format($subtotal, 0, ',', '.')."\n";
        }

        $tax = $total * 0.11;
        $grandTotal = $total + $tax;

        $response .= "\nSubtotal: Rp ".number_format($total, 0, ',', '.')."\n";
        $response .= 'Pajak (11%): Rp '.number_format($tax, 0, ',', '.')."\n";
        $response .= 'Total: Rp '.number_format($grandTotal, 0, ',', '.')."\n\n";
        $response .= "Apakah Anda yakin ingin melanjutkan pesanan ini?\n";
        $response .= "Balas 'Ya' untuk konfirmasi atau 'Tidak' untuk membatalkan.";

        return $response;
    }

    /**
     * Check if message is confirmation.
     */
    protected function isConfirmation(string $message): bool
    {
        $message = strtolower(trim($message));

        return in_array($message, ['ya', 'yes', 'iya', 'ok', 'oke', 'konfirmasi', 'setuju']);
    }

    /**
     * Check if message is rejection.
     */
    protected function isRejection(string $message): bool
    {
        $message = strtolower(trim($message));

        return in_array($message, ['tidak', 'no', 'batal', 'cancel', 'gak', 'nggak']);
    }

    /**
     * Confirm and create order.
     */
    protected function confirmAndCreateOrder(
        AiAgentConversation $conversation,
        WhatsAppAccount $account,
        WhatsAppContact $contact
    ): void {
        try {
            $pendingOrder = $conversation->getPendingOrder();
            $aiAgent = $conversation->aiAgent;

            if (! $aiAgent->default_store_id) {
                $this->sendReply(
                    $account,
                    $contact->wa_id,
                    'Maaf, toko default belum dikonfigurasi. Silakan hubungi administrator.'
                );

                return;
            }

            // Create order
            $order = $this->orderService->create([
                'store_id' => $aiAgent->default_store_id,
                'table_id' => null,
                'pos_user_id' => null,
            ]);

            // Add items
            foreach ($pendingOrder['items'] as $item) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $this->orderService->addItem($order, $product, $item['quantity']);
                }
            }

            // Clear cart and pending order
            $conversation->clearCart();
            $conversation->clearPendingOrder();

            // Send confirmation
            $response = "✅ Pesanan berhasil dibuat!\n\n";
            $response .= "Nomor Pesanan: {$order->order_number}\n";
            $response .= 'Total: Rp '.number_format($order->total, 0, ',', '.')."\n\n";
            $response .= 'Terima kasih atas pesanan Anda! 🙏';

            $this->sendReply($account, $contact->wa_id, $response);

        } catch (\Exception $e) {
            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
            ]);

            $this->sendReply(
                $account,
                $contact->wa_id,
                'Maaf, terjadi kesalahan saat membuat pesanan. Silakan coba lagi nanti.'
            );
        }
    }

    /**
     * Send reply via WhatsApp.
     */
    protected function sendReply(WhatsAppAccount $account, string $to, string $message): void
    {
        try {
            $whatsapp = new WhatsAppCloudApi([
                'from_phone_number_id' => $account->phone_number_id,
                'access_token' => $account->access_token,
            ]);

            $whatsapp->sendTextMessage($to, $message);

        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp reply', [
                'error' => $e->getMessage(),
                'to' => $to,
            ]);
        }
    }
}
