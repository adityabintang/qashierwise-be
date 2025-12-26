<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAiAgentRequest;
use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Payment;
use App\Models\QrisTransaction;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgentService;
use App\Services\OrderService;
use App\Services\QrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiAgentController extends Controller
{
    protected AiAgentService $aiAgentService;

    protected QrisService $qrisService;

    protected OrderService $orderService;

    public function __construct(AiAgentService $aiAgentService, QrisService $qrisService, OrderService $orderService)
    {
        $this->aiAgentService = $aiAgentService;
        $this->qrisService = $qrisService;
        $this->orderService = $orderService;
    }

    /**
     * Get AI Agent configuration.
     */
    public function show(): JsonResponse
    {
        try {
            $userId = auth()->id();

            $whatsappAccount = WhatsAppAccount::where('user_id', $userId)->first();

            if (! $whatsappAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp account not connected',
                ], 404);
            }

            $aiAgent = AiAgent::where('whatsapp_account_id', $whatsappAccount->id)->first();

            if (! $aiAgent) {
                return response()->json([
                    'success' => true,
                    'message' => 'AI Agent not configured yet',
                    'data' => null,
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'AI Agent configuration retrieved',
                'data' => [
                    'id' => $aiAgent->id,
                    'bot_name' => $aiAgent->bot_name,
                    'system_prompt' => $aiAgent->system_prompt,
                    'business_info' => $aiAgent->business_info,
                    'default_store_id' => $aiAgent->default_store_id,
                    'order_enabled' => $aiAgent->order_enabled,
                    'qris_enabled' => $aiAgent->qris_enabled,
                    'is_active' => $aiAgent->is_active,
                    'settings' => $aiAgent->settings,
                    'created_at' => $aiAgent->created_at,
                    'updated_at' => $aiAgent->updated_at,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve AI Agent configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create or update AI Agent configuration.
     */
    public function store(StoreAiAgentRequest $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $whatsappAccount = WhatsAppAccount::where('user_id', $userId)->first();

            if (! $whatsappAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp account not connected. Please connect your WhatsApp Business Account first.',
                ], 404);
            }

            // Validate default_store_id belongs to user
            if ($request->filled('default_store_id')) {
                $storeExists = \App\Models\Store::where('id', $request->default_store_id)
                    ->where('user_id', $userId)
                    ->exists();

                if (! $storeExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid store. Store not found or does not belong to you.',
                    ], 422);
                }
            }

            $aiAgent = AiAgent::updateOrCreate(
                ['whatsapp_account_id' => $whatsappAccount->id],
                [
                    'bot_name' => $request->bot_name,
                    'system_prompt' => $request->system_prompt,
                    'business_info' => $request->business_info,
                    'default_store_id' => $request->default_store_id,
                    'order_enabled' => $request->boolean('order_enabled', false),
                    'qris_enabled' => $request->boolean('qris_enabled', false),
                    'is_active' => $request->boolean('is_active', false),
                    'settings' => $request->settings,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'AI Agent configuration saved successfully',
                'data' => [
                    'id' => $aiAgent->id,
                    'bot_name' => $aiAgent->bot_name,
                    'system_prompt' => $aiAgent->system_prompt,
                    'business_info' => $aiAgent->business_info,
                    'default_store_id' => $aiAgent->default_store_id,
                    'order_enabled' => $aiAgent->order_enabled,
                    'qris_enabled' => $aiAgent->qris_enabled,
                    'is_active' => $aiAgent->is_active,
                    'settings' => $aiAgent->settings,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save AI Agent configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle AI Agent active status.
     */
    public function toggleActive(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $whatsappAccount = WhatsAppAccount::where('user_id', $userId)->first();

            if (! $whatsappAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp account not connected',
                ], 404);
            }

            $aiAgent = AiAgent::where('whatsapp_account_id', $whatsappAccount->id)->first();

            if (! $aiAgent) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI Agent not configured yet',
                ], 404);
            }

            $aiAgent->is_active = ! $aiAgent->is_active;
            $aiAgent->save();

            return response()->json([
                'success' => true,
                'message' => 'AI Agent '.($aiAgent->is_active ? 'activated' : 'deactivated'),
                'data' => [
                    'is_active' => $aiAgent->is_active,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle AI Agent status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle order feature.
     */
    public function toggleOrder(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $whatsappAccount = WhatsAppAccount::where('user_id', $userId)->first();

            if (! $whatsappAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp account not connected',
                ], 404);
            }

            $aiAgent = AiAgent::where('whatsapp_account_id', $whatsappAccount->id)->first();

            if (! $aiAgent) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI Agent not configured yet',
                ], 404);
            }

            if (! $aiAgent->default_store_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot enable order feature. Please set a default store first.',
                ], 422);
            }

            $aiAgent->order_enabled = ! $aiAgent->order_enabled;
            $aiAgent->save();

            return response()->json([
                'success' => true,
                'message' => 'Order feature '.($aiAgent->order_enabled ? 'enabled' : 'disabled'),
                'data' => [
                    'order_enabled' => $aiAgent->order_enabled,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle order feature',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle QRIS feature with validation.
     */
    public function toggleQris(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $whatsappAccount = WhatsAppAccount::where('user_id', $userId)->first();

            if (! $whatsappAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp account not connected',
                ], 404);
            }

            $aiAgent = AiAgent::where('whatsapp_account_id', $whatsappAccount->id)->first();

            if (! $aiAgent) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI Agent not configured yet',
                ], 404);
            }

            // If trying to enable, validate configuration
            if (!$aiAgent->qris_enabled) {
                $errors = $aiAgent->validateQrisConfiguration();
                
                if (!empty($errors)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot enable QRIS feature',
                        'errors' => $errors,
                    ], 422);
                }
            }

            $aiAgent->qris_enabled = ! $aiAgent->qris_enabled;
            $aiAgent->save();

            return response()->json([
                'success' => true,
                'message' => 'QRIS feature '.($aiAgent->qris_enabled ? 'enabled' : 'disabled'),
                'data' => [
                    'qris_enabled' => $aiAgent->qris_enabled,
                    'is_qris_ready' => $aiAgent->isQrisEnabled(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle QRIS feature',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test AI Agent with a sample message.
     */
    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        try {
            $userId = auth()->id();

            $whatsappAccount = WhatsAppAccount::where('user_id', $userId)->first();

            if (! $whatsappAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp account not connected',
                ], 404);
            }

            $aiAgent = AiAgent::where('whatsapp_account_id', $whatsappAccount->id)
                ->where('is_active', true)
                ->first();

            if (! $aiAgent) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI Agent not configured or not active',
                ], 404);
            }

            // Create or reuse test contact for this user (use fixed wa_id to avoid creating new contacts)
            $testContact = WhatsAppContact::firstOrCreate(
                [
                    'user_id' => $userId,
                    'wa_id' => 'test_user_'.$userId,
                ],
                [
                    'name' => 'Test Contact',
                ]
            );

            // Get or create conversation
            $conversation = $this->aiAgentService->getOrCreateConversation(
                $aiAgent->id,
                $testContact->id
            );

            // Add user message
            $conversation->addMessage('human', $request->message);

            // Build system prompt
            $systemPrompt = $aiAgent->buildSystemPrompt($userId);

            // Get tool definitions including QRIS if enabled
            $tools = $this->getTestToolDefinitions($aiAgent);

            // Call LLM
            $response = $this->aiAgentService->callLLM(
                $systemPrompt,
                $conversation->messages ?? [],
                $tools
            );

            // Handle tool calls if present
            $responseContent = '';
            if (isset($response['tool_calls'])) {
                $responseContent = $this->handleTestToolCalls($response['tool_calls'], $userId, $conversation, $aiAgent);
            } else {
                $responseContent = $response['content'] ?? 'No response generated';
            }

            // Add assistant message
            $conversation->addMessage('ai', $responseContent);

            // Refresh to get latest messages from database
            $conversation->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Test message processed',
                'data' => [
                    'user_message' => $request->message,
                    'ai_response' => $responseContent,
                    'conversation_history' => $conversation->messages,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process test message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tool definitions for test endpoint.
     */
    protected function getTestToolDefinitions(AiAgent $aiAgent): ?array
    {
        if (!$aiAgent->isOrderEnabled()) {
            return null;
        }

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Cari produk berdasarkan nama atau SKU. Gunakan ini ketika user bertanya tentang menu, produk, atau daftar item.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Kata kunci pencarian. Jika user minta semua menu/produk, gunakan string kosong atau kata umum seperti "menu"',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_all_products',
                    'description' => 'Dapatkan semua produk/menu yang tersedia. Gunakan ini ketika user minta daftar menu lengkap atau semua produk.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_product_details',
                    'description' => 'Dapatkan detail lengkap dari satu produk berdasarkan ID produk.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'ID produk yang ingin dilihat detailnya',
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
                    'description' => 'Tambahkan produk ke keranjang belanja. Gunakan setelah user memilih produk yang ingin dipesan.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'ID produk yang akan ditambahkan',
                            ],
                            'quantity' => [
                                'type' => 'integer',
                                'description' => 'Jumlah produk yang dipesan',
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
                    'description' => 'Lihat ringkasan keranjang belanja saat ini.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'confirm_order',
                    'description' => 'Konfirmasi dan buat pesanan dari keranjang belanja.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                        'required' => [],
                    ],
                ],
            ],
        ];

        // Add QRIS tools if enabled
        if ($aiAgent->isQrisEnabled()) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_qris',
                    'description' => 'Generate kode QRIS untuk pembayaran. Gunakan setelah pesanan dikonfirmasi.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'amount' => [
                                'type' => 'number',
                                'description' => 'Jumlah pembayaran dalam Rupiah',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Deskripsi pembayaran (opsional)',
                            ],
                        ],
                        'required' => ['amount'],
                    ],
                ],
            ];

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'check_payment_status',
                    'description' => 'Cek status pembayaran QRIS terakhir',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ];
        }

        return $tools;
    }

    /**
     * Handle tool calls for test endpoint.
     */
    protected function handleTestToolCalls(array $toolCalls, int $userId, AiAgentConversation $conversation, AiAgent $aiAgent): string
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $functionName = $toolCall['function']['name'] ?? null;
            $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);

            $result = $this->executeTestToolCall($functionName, $arguments, $userId, $conversation, $aiAgent);
            $results[] = $result;
        }

        return implode("\n\n", array_filter($results));
    }

    /**
     * Execute a tool call for test endpoint.
     */
    protected function executeTestToolCall(
        string $functionName,
        array $arguments,
        int $userId,
        AiAgentConversation $conversation,
        AiAgent $aiAgent
    ): string {
        try {
            // Validate parameters based on function requirements
            switch ($functionName) {
                case 'search_products':
                    if (!isset($arguments['query'])) {
                        return 'Maaf, parameter pencarian tidak lengkap. Mohon berikan kata kunci pencarian.';
                    }
                    return $this->searchProducts($userId, $arguments['query']);

                case 'get_all_products':
                    return $this->getAllProducts($userId);

                case 'get_product_details':
                    if (!isset($arguments['product_id'])) {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan ID produk.';
                    }
                    if (!is_numeric($arguments['product_id'])) {
                        return 'Maaf, ID produk harus berupa angka.';
                    }
                    return $this->getProductDetails($userId, (int) $arguments['product_id']);

                case 'add_to_cart':
                    if (!isset($arguments['product_id'])) {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan ID produk.';
                    }
                    if (!isset($arguments['quantity'])) {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan jumlah pesanan.';
                    }
                    if (!is_numeric($arguments['product_id'])) {
                        return 'Maaf, ID produk harus berupa angka.';
                    }
                    if (!is_numeric($arguments['quantity'])) {
                        return 'Maaf, jumlah pesanan harus berupa angka.';
                    }
                    return $this->addToCart(
                        $conversation,
                        $userId,
                        (int) $arguments['product_id'],
                        (int) $arguments['quantity']
                    );

                case 'get_cart_summary':
                    return $this->getCartSummary($conversation);

                case 'confirm_order':
                    return $this->confirmOrder($conversation, $aiAgent, $userId);

                case 'generate_qris':
                    if (!isset($arguments['amount'])) {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan jumlah pembayaran.';
                    }
                    if (!is_numeric($arguments['amount'])) {
                        return 'Maaf, jumlah pembayaran harus berupa angka.';
                    }
                    return $this->generateTestQris(
                        $conversation,
                        $aiAgent,
                        (float) $arguments['amount'],
                        $arguments['description'] ?? null
                    );

                case 'check_payment_status':
                    return $this->checkTestPaymentStatus($conversation);

                default:
                    return "Fungsi '{$functionName}' tidak dikenali.";
            }
        } catch (\Exception $e) {
            Log::error("Test tool call error: {$functionName}", [
                'user_id' => $userId,
                'tool_name' => $functionName,
                'parameters' => $arguments,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 'Maaf, terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi.';
        }
    }

    /**
     * Generate QRIS for test endpoint.
     */
    protected function generateTestQris(
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        float $amount,
        ?string $description = null
    ): string {
        try {
            // Validate QRIS is enabled
            if (!$aiAgent->isQrisEnabled()) {
                $errors = $aiAgent->validateQrisConfiguration();
                return 'Maaf, pembayaran QRIS belum tersedia. ' . implode(' ', $errors);
            }

            // Get SubMerchant
            $subMerchant = $aiAgent->getSubMerchant();
            if (!$subMerchant) {
                return 'Maaf, pembayaran QRIS belum tersedia. Sub-merchant belum dikonfigurasi.';
            }

            // Validate amount
            if ($amount <= 0) {
                return 'Maaf, jumlah pembayaran tidak valid.';
            }

            // Generate QRIS using QrisService
            $qrisTransaction = $this->qrisService->generateQris($subMerchant, $amount, [
                'description' => $description ?? 'Test pembayaran via AI Agent',
            ]);

            // Link to order if exists
            $currentOrder = $conversation->getCurrentOrder();
            if ($currentOrder) {
                $qrisTransaction->linked_order_id = $currentOrder->id;
                $qrisTransaction->save();

                // Create Payment record linking order and QRIS transaction
                Payment::create([
                    'order_id' => $currentOrder->id,
                    'qris_transaction_id' => $qrisTransaction->id,
                    'amount' => $amount,
                    'payment_method' => 'qris',
                    'status' => 'pending',
                ]);
            }

            // Store QRIS transaction in conversation context
            $conversation->setCurrentQrisTransaction($qrisTransaction->id);

            // Format response message
            $expiryTime = $qrisTransaction->expires_at->format('H:i');
            $formattedAmount = 'Rp ' . number_format($amount, 0, ',', '.');
            $shareableLink = $qrisTransaction->getShareableLink();

            $response = "💳 Pembayaran QRIS (Test)\n\n";
            $response .= "Total: {$formattedAmount}\n\n";
            $response .= "📱 Silakan bayar melalui link berikut:\n";
            $response .= "{$shareableLink}\n\n";
            $response .= "⏰ Berlaku hingga: {$expiryTime}\n\n";
            $response .= "No. Transaksi: {$qrisTransaction->order_id}\n\n";
            $response .= "Ketik 'cek status' untuk melihat status pembayaran.";

            return $response;

        } catch (\Exception $e) {
            return 'Maaf, terjadi kesalahan saat membuat kode pembayaran: ' . $e->getMessage();
        }
    }

    /**
     * Check payment status for test endpoint.
     */
    protected function checkTestPaymentStatus(AiAgentConversation $conversation): string
    {
        try {
            $qrisTransaction = $conversation->getCurrentQrisTransaction();

            if (!$qrisTransaction) {
                return 'Tidak ada pembayaran yang sedang diproses.';
            }

            // Refresh from database
            $qrisTransaction->refresh();

            $formattedAmount = 'Rp ' . number_format($qrisTransaction->amount, 0, ',', '.');

            switch ($qrisTransaction->status) {
                case QrisTransaction::STATUS_SETTLEMENT:
                    return "✅ Pembayaran Berhasil!\n\n" .
                           "Jumlah: {$formattedAmount}\n" .
                           "No. Transaksi: {$qrisTransaction->order_id}";

                case QrisTransaction::STATUS_PENDING:
                    if ($qrisTransaction->isExpired()) {
                        return "⏰ Kode pembayaran sudah kadaluarsa.";
                    }
                    $remainingMinutes = ceil($qrisTransaction->getRemainingTimeInSeconds() / 60);
                    return "⏳ Pembayaran Menunggu\n\n" .
                           "Jumlah: {$formattedAmount}\n" .
                           "Sisa waktu: {$remainingMinutes} menit";

                case QrisTransaction::STATUS_EXPIRE:
                    return "⏰ Kode pembayaran sudah kadaluarsa.";

                case QrisTransaction::STATUS_CANCEL:
                    return "❌ Pembayaran dibatalkan.";

                default:
                    return "Status: {$qrisTransaction->status}";
            }

        } catch (\Exception $e) {
            return 'Maaf, terjadi kesalahan saat mengecek status pembayaran.';
        }
    }

    /**
     * Search products by query.
     */
    protected function searchProducts(int $userId, string $query): string
    {
        $productsQuery = \App\Models\Product::where('user_id', $userId)
            ->where('is_active', true);

        // If query is empty or generic like "menu", get all products
        if (empty($query) || in_array(strtolower($query), ['menu', 'semua', 'all', 'produk', 'daftar'])) {
            return $this->getAllProducts($userId);
        }

        $products = $productsQuery
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
            $response .= "🔹 {$product->name}\n";
            $response .= '   Harga: Rp '.number_format($product->price, 0, ',', '.')."\n";
            if ($product->stock_quantity !== null) {
                $response .= "   Stok: {$product->stock_quantity}\n";
            }
            $response .= "\n";
        }

        return $response;
    }

    /**
     * Get all active products.
     */
    protected function getAllProducts(int $userId): string
    {
        $products = \App\Models\Product::where('user_id', $userId)
            ->where('is_active', true)
            ->limit(20)
            ->get(['id', 'name', 'price', 'stock_quantity', 'description']);

        if ($products->isEmpty()) {
            return 'Maaf, belum ada produk yang tersedia saat ini.';
        }

        $response = "📋 Berikut daftar menu/produk kami:\n\n";
        foreach ($products as $index => $product) {
            $response .= ($index + 1).". {$product->name}\n";
            $response .= '   💰 Rp '.number_format($product->price, 0, ',', '.')."\n";
            if ($product->description) {
                $response .= "   📝 {$product->description}\n";
            }
            $response .= "\n";
        }

        $response .= 'Silakan pilih produk yang Anda inginkan! 😊';

        return $response;
    }

    /**
     * Get product details by ID.
     */
    protected function getProductDetails(int $userId, int $productId): string
    {
        if ($productId <= 0) {
            return 'Maaf, ID produk tidak valid.';
        }

        $product = \App\Models\Product::where('id', $productId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first(['id', 'name', 'price', 'stock_quantity', 'description', 'sku']);

        if (!$product) {
            return 'Maaf, produk tidak ditemukan.';
        }

        $response = "📦 Detail Produk\n\n";
        $response .= "Nama: {$product->name}\n";
        $response .= '💰 Harga: Rp '.number_format($product->price, 0, ',', '.')."\n";
        
        if ($product->stock_quantity !== null) {
            $response .= "📊 Stok: {$product->stock_quantity}\n";
        }
        
        if ($product->sku) {
            $response .= "🏷️ SKU: {$product->sku}\n";
        }
        
        if ($product->description) {
            $response .= "\n📝 Deskripsi:\n{$product->description}\n";
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
        if ($productId <= 0) {
            return 'Maaf, ID produk tidak valid.';
        }

        if ($quantity <= 0) {
            return 'Maaf, jumlah pesanan harus lebih dari 0.';
        }

        // Validate product exists and belongs to user
        $product = \App\Models\Product::where('id', $productId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first(['id', 'name', 'price', 'stock_quantity']);

        if (!$product) {
            return 'Maaf, produk tidak ditemukan.';
        }

        // Check stock availability
        if ($product->stock_quantity !== null && $product->stock_quantity < $quantity) {
            return "Maaf, stok tidak mencukupi. Stok tersedia: {$product->stock_quantity}";
        }

        // Get current cart
        $cart = $conversation->getCart();

        // Check if product already in cart
        $existingIndex = null;
        foreach ($cart as $index => $item) {
            if ($item['product_id'] === $productId) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null) {
            // Update existing item
            $newQuantity = $cart[$existingIndex]['quantity'] + $quantity;
            
            // Check stock for new quantity
            if ($product->stock_quantity !== null && $product->stock_quantity < $newQuantity) {
                return "Maaf, stok tidak mencukupi. Stok tersedia: {$product->stock_quantity}";
            }
            
            $cart[$existingIndex]['quantity'] = $newQuantity;
        } else {
            // Add new item
            $cart[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => (float) $product->price,
                'quantity' => $quantity,
            ];
        }

        // Update cart in conversation
        $conversation->updateCart($cart);

        $formattedPrice = 'Rp '.number_format($product->price, 0, ',', '.');
        $subtotal = $product->price * $quantity;
        $formattedSubtotal = 'Rp '.number_format($subtotal, 0, ',', '.');

        return "✅ Berhasil menambahkan ke keranjang!\n\n" .
               "📦 {$product->name}\n" .
               "💰 {$formattedPrice} x {$quantity} = {$formattedSubtotal}\n\n" .
               "Ketik 'lihat keranjang' untuk melihat ringkasan pesanan.";
    }

    /**
     * Get cart summary.
     */
    protected function getCartSummary(AiAgentConversation $conversation): string
    {
        $cart = $conversation->getCart();

        if (empty($cart)) {
            return '🛒 Keranjang belanja Anda masih kosong.';
        }

        $subtotal = 0;
        $response = "🛒 Keranjang Belanja\n\n";

        foreach ($cart as $index => $item) {
            $itemSubtotal = $item['price'] * $item['quantity'];
            $subtotal += $itemSubtotal;

            $response .= ($index + 1) . ". {$item['product_name']}\n";
            $response .= '   💰 Rp ' . number_format($item['price'], 0, ',', '.') . 
                        " x {$item['quantity']} = Rp " . 
                        number_format($itemSubtotal, 0, ',', '.') . "\n\n";
        }

        // Calculate tax (11%)
        $taxAmount = $subtotal * 0.11;
        $total = $subtotal + $taxAmount;

        $response .= "━━━━━━━━━━━━━━━━━━━━\n";
        $response .= 'Subtotal: Rp ' . number_format($subtotal, 0, ',', '.') . "\n";
        $response .= 'Pajak (11%): Rp ' . number_format($taxAmount, 0, ',', '.') . "\n";
        $response .= 'Total: Rp ' . number_format($total, 0, ',', '.') . "\n\n";
        $response .= "Ketik 'konfirmasi pesanan' untuk melanjutkan.";

        return $response;
    }

    /**
     * Confirm order and create Order record.
     */
    protected function confirmOrder(
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        int $userId
    ): string {
        $cart = $conversation->getCart();

        if (empty($cart)) {
            return '🛒 Keranjang belanja Anda masih kosong. Silakan tambahkan produk terlebih dahulu.';
        }

        // Validate store is configured
        if (!$aiAgent->default_store_id) {
            Log::warning('Order creation failed - no default store', [
                'user_id' => $userId,
                'ai_agent_id' => $aiAgent->id,
            ]);
            return 'Maaf, toko default belum dikonfigurasi.';
        }

        try {
            return DB::transaction(function () use ($conversation, $aiAgent, $userId, $cart) {
                // Get contact for customer info
                $contact = $conversation->whatsappContact;

                Log::info('Creating order via test endpoint', [
                    'user_id' => $userId,
                    'store_id' => $aiAgent->default_store_id,
                    'cart_items' => count($cart),
                    'qris_enabled' => $aiAgent->isQrisEnabled(),
                ]);

                // Create order using OrderService
                $order = $this->orderService->create([
                    'store_id' => $aiAgent->default_store_id,
                    'table_id' => null,
                    'pos_user_id' => null,
                    'source' => \App\Models\Order::SOURCE_WHATSAPP_AI,
                    'customer_name' => $contact->name ?? 'Test Customer',
                    'customer_phone' => $contact->wa_id ?? 'test',
                ]);

                Log::info('Order created', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);

                // Add order items using OrderService
                foreach ($cart as $item) {
                    $product = \App\Models\Product::find($item['product_id']);
                    if ($product) {
                        $this->orderService->addItem($order, $product, $item['quantity']);
                        Log::debug('Order item added', [
                            'order_id' => $order->id,
                            'product_id' => $product->id,
                            'quantity' => $item['quantity'],
                        ]);
                    } else {
                        Log::warning('Product not found when adding to order', [
                            'product_id' => $item['product_id'],
                        ]);
                    }
                }

                // Refresh order to get updated total
                $order->refresh();

                // Store order_id in conversation
                $conversation->setCurrentOrder($order->id);

                // Clear cart
                $conversation->clearCart();

                Log::info('Order completed successfully', [
                    'order_id' => $order->id,
                    'total' => $order->total,
                ]);

                // Check if QRIS is enabled - auto generate QRIS
                if ($aiAgent->isQrisEnabled()) {
                    $subMerchant = $aiAgent->getSubMerchant();
                    
                    if ($subMerchant) {
                        try {
                            Log::info('Auto-generating QRIS for order', [
                                'order_id' => $order->id,
                                'amount' => $order->total,
                            ]);

                            // Generate QRIS for the order
                            $qrisTransaction = $this->qrisService->generateQris($subMerchant, (float) $order->total, [
                                'description' => "Pesanan #{$order->order_number}",
                            ]);

                            // Link QRIS to order
                            $qrisTransaction->linked_order_id = $order->id;
                            $qrisTransaction->save();

                            // Create Payment record
                            \App\Models\Payment::create([
                                'order_id' => $order->id,
                                'qris_transaction_id' => $qrisTransaction->id,
                                'method' => \App\Models\Payment::METHOD_QRIS,
                                'amount' => $order->total,
                                'status' => \App\Models\Payment::STATUS_PENDING,
                            ]);

                            // Store QRIS transaction in conversation
                            $conversation->setCurrentQrisTransaction($qrisTransaction->id);

                            Log::info('QRIS generated successfully', [
                                'qris_transaction_id' => $qrisTransaction->id,
                                'order_id' => $qrisTransaction->order_id,
                            ]);

                            // Send order confirmation with QRIS
                            $expiryTime = $qrisTransaction->expires_at->format('H:i');
                            $formattedTotal = 'Rp ' . number_format($order->total, 0, ',', '.');

                            $response = "✅ Pesanan Berhasil Dibuat!\n\n";
                            $shareableLink = $qrisTransaction->getShareableLink();
                            
                            $response .= "📋 No. Pesanan: {$order->order_number}\n";
                            $response .= "💰 Total: {$formattedTotal}\n\n";
                            $response .= "💳 Silakan bayar melalui link berikut:\n";
                            $response .= "{$shareableLink}\n\n";
                            $response .= "⏰ Berlaku hingga: {$expiryTime}\n\n";
                            $response .= "Cara pembayaran:\n";
                            $response .= "1. Klik link di atas\n";
                            $response .= "2. Scan QR Code yang muncul\n";
                            $response .= "3. Buka aplikasi e-wallet/mobile banking\n";
                            $response .= "4. Konfirmasi pembayaran\n\n";
                            $response .= "Ketik 'cek status' setelah membayar. 🙏";

                            return $response;

                        } catch (\Exception $e) {
                            Log::error('QRIS generation failed during order confirmation', [
                                'error' => $e->getMessage(),
                                'order_id' => $order->id,
                                'trace' => $e->getTraceAsString(),
                            ]);
                            // Fall through to send order without QRIS
                        }
                    } else {
                        Log::warning('SubMerchant not found for QRIS generation', [
                            'ai_agent_id' => $aiAgent->id,
                        ]);
                    }
                }

                // Format response (fallback without QRIS)
                $formattedTotal = 'Rp ' . number_format($order->total, 0, ',', '.');

                return "✅ Pesanan Berhasil Dibuat!\n\n" .
                       "📋 No. Pesanan: {$order->order_number}\n" .
                       "💰 Total: {$formattedTotal}\n\n" .
                       "Pesanan Anda sedang diproses. Terima kasih! 🙏";
            });

        } catch (\InvalidArgumentException $e) {
            Log::error('Order creation failed - validation error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return "Maaf, gagal membuat pesanan: {$e->getMessage()}";
        } catch (\Exception $e) {
            Log::error('Order creation failed - unexpected error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 'Maaf, terjadi kesalahan saat membuat pesanan.';
        }
    }

    /**
     * Clear conversation history for a contact.
     */
    public function clearConversation(Request $request, int $contactId): JsonResponse
    {
        try {
            $userId = auth()->id();

            $whatsappAccount = WhatsAppAccount::where('user_id', $userId)->first();

            if (! $whatsappAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp account not connected',
                ], 404);
            }

            $aiAgent = AiAgent::where('whatsapp_account_id', $whatsappAccount->id)->first();

            if (! $aiAgent) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI Agent not configured',
                ], 404);
            }

            AiAgentConversation::where('ai_agent_id', $aiAgent->id)
                ->where('whatsapp_contact_id', $contactId)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Conversation history cleared',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear conversation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
