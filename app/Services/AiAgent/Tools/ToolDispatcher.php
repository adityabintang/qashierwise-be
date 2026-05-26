<?php

namespace App\Services\AiAgent\Tools;

use App\Enums\UserIntent;
use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgent\Checkout\CheckoutTools;
use App\Services\AiAgent\LLM\LlmClient;
use App\Services\AiAgent\Payment\PaymentTools;
use App\Services\AiAgent\Reply\ReplySender;
use Illuminate\Support\Facades\Log;

/**
 * Owns the tool layer: definitions sent to the LLM, dispatch from tool-call
 * name to handler, and the small loop that re-prompts the LLM with tool
 * results (for two-step search → add patterns).
 *
 * Tool handlers live in dedicated classes (CartTools / CatalogTools / etc).
 * This class is the seam between "string tool name + JSON args from LLM"
 * and those typed handler methods.
 */
class ToolDispatcher
{
    public function __construct(
        protected CatalogTools $catalogTools,
        protected CartTools $cartTools,
        protected CheckoutTools $checkoutTools,
        protected PaymentTools $paymentTools,
        protected ReplySender $replySender,
        protected LlmClient $llmClient,
    ) {}

    /**
     * Whether to attach tool definitions to the LLM call for this intent.
     * Cheap conversational intents (greeting, off-topic, business info)
     * don't need the ~500-800 tokens of tool specs.
     */
    public function intentNeedsTools(UserIntent $intent): bool
    {
        return ! in_array($intent, [
            UserIntent::GREETING,
            UserIntent::OFF_TOPIC,
            UserIntent::BUSINESS_INFO,
        ], true);
    }

    /**
     * Tool definitions assembled per agent: base ordering tools, plus QRIS
     * payment tools when the merchant has it enabled. Returns null when
     * ordering is disabled entirely (the LLM call goes tool-less).
     */
    public function getToolDefinitionsForAgent(AiAgent $aiAgent): ?array
    {
        if (! $aiAgent->isOrderEnabled()) {
            return null;
        }

        $tools = $this->baseDefinitions();

        if ($aiAgent->isQrisEnabled()) {
            $tools = array_merge($tools, $this->qrisDefinitions());
        }

        return $tools;
    }

    /**
     * Drive a (possibly multi-step) tool-call cycle initiated by the LLM.
     *
     * The loop handles a recurring pattern: LLM searches for products,
     * receives results, then calls add_to_cart. We let the LLM see search
     * results before sending anything to the customer — but cap iterations
     * to prevent runaway loops if the LLM keeps re-searching.
     */
    public function handleToolCalls(
        array $toolCalls,
        AiAgentConversation $conversation,
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        int $iteration = 1,
    ): void {
        if ($iteration > 3) {
            Log::error('Tool call iteration limit reached, stopping to prevent infinite loop', [
                'conversation_id' => $conversation->id,
                'iteration' => $iteration,
            ]);

            $errorMessage = "Maaf, saya mengalami kesulitan memproses pesanan Anda. Silakan coba lagi dengan format yang lebih sederhana.\n\n"
                ."Contoh: 'pesan dimsum 2 porsi'";
            $conversation->addMessage('ai', $errorMessage);
            $this->replySender->send($account, $contact->wa_id, $errorMessage);

            return;
        }

        Log::info('Handling tool calls from LLM', [
            'iteration' => $iteration,
            'tool_calls_count' => count($toolCalls),
            'tool_calls' => array_map(fn ($tc) => [
                'function' => $tc['function']['name'] ?? 'unknown',
                'arguments' => $tc['function']['arguments'] ?? '{}',
            ], $toolCalls),
        ]);

        $toolResults = [];
        $hasSearchCall = false;
        $hasFinalAction = false;

        foreach ($toolCalls as $toolCall) {
            $functionName = $toolCall['function']['name'] ?? null;
            $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
            if (! is_array($arguments)) {
                $arguments = [];
            }
            $toolCallId = $toolCall['id'] ?? uniqid();

            Log::info('Executing tool call', [
                'function' => $functionName,
                'arguments' => $arguments,
            ]);

            $result = $this->executeToolCall($functionName, $arguments, $account->user_id, $conversation, $aiAgent);

            if (in_array($functionName, ['search_products', 'search_multiple_products'])) {
                $hasSearchCall = true;
            }
            if (in_array($functionName, [
                'add_to_cart', 'confirm_order', 'get_cart_summary', 'generate_qris',
                'check_payment_status', 'remove_from_cart', 'clear_cart',
                'set_order_notes', 'get_all_products',
            ])) {
                $hasFinalAction = true;
            }

            $toolResults[] = [
                'tool_call_id' => $toolCallId,
                'function_name' => $functionName,
                'result' => $result,
            ];
        }

        // Search-only path: LLM needs another turn to act on results.
        if ($hasSearchCall && ! $hasFinalAction) {
            if ($this->detectSearchLoop($conversation)) {
                Log::warning('Preventing search loop - already searched multiple times recently', [
                    'conversation_id' => $conversation->id,
                ]);

                $msg = "Maaf, saya mengalami kesulitan memproses pesanan Anda. Silakan coba lagi dengan format:\n\n"
                    ."Contoh: 'pesan dimsum 2 porsi'\n"
                    ."Atau: 'pesan dimsum 1 dan teh jumbo 2'";
                $conversation->addMessage('ai', $msg);
                $this->replySender->send($account, $contact->wa_id, $msg);

                return;
            }

            Log::info('Search completed, calling LLM again to process results and add to cart');

            $messages = $this->buildFollowUpMessages($conversation, $toolCalls, $toolResults);
            $systemPrompt = $aiAgent->buildSystemPrompt($account->user_id);
            $tools = $this->getToolDefinitionsForAgent($aiAgent);

            try {
                $response = $this->llmClient->callWithToolResults($systemPrompt, $messages, $tools);

                if (isset($response['tool_calls'])) {
                    $this->handleToolCalls($response['tool_calls'], $conversation, $account, $contact, $aiAgent, $iteration + 1);

                    return;
                }

                $assistantMessage = $response['content'] ?? '';
                if (empty(trim($assistantMessage))) {
                    Log::warning('LLM returned empty content after search', [
                        'conversation_id' => $conversation->id,
                        'tool_results' => array_map(fn ($tr) => $tr['function_name'], $toolResults),
                    ]);
                    $assistantMessage = $this->generateContextualFallback($toolResults, $conversation);
                }

                $conversation->addMessage('ai', $assistantMessage);
                $this->replySender->send($account, $contact->wa_id, $assistantMessage);

                return;
            } catch (\Exception $e) {
                Log::error('Error in follow-up LLM call', ['error' => $e->getMessage()]);
                // Fall through to send raw results.
            }
        }

        // Final actions: forward results directly. search_* results are
        // internal sentinels; the customer never sees them.
        $userFacing = [];
        foreach ($toolResults as $tr) {
            if (! in_array($tr['function_name'], ['search_products', 'search_multiple_products'])) {
                $result = $tr['result'];
                if (str_starts_with($result, 'EMPTY\n')) {
                    $result = substr($result, 6);
                }
                $userFacing[] = $result;
            }
        }

        $message = implode("\n\n", array_filter($userFacing));
        if (empty(trim($message))) {
            Log::warning('No user-facing results from tool calls', [
                'conversation_id' => $conversation->id,
                'tool_calls' => array_map(fn ($tr) => $tr['function_name'], $toolResults),
            ]);
            $message = $this->generateContextualFallback($toolResults, $conversation);
        }

        $conversation->addMessage('ai', $message);
        $this->replySender->send($account, $contact->wa_id, $message);
    }

    /**
     * Single tool-name → handler call. Wraps each call in try/catch so a
     * single misbehaving tool doesn't take down the whole turn.
     */
    public function executeToolCall(
        string $functionName,
        array $arguments,
        int $userId,
        AiAgentConversation $conversation,
        ?AiAgent $aiAgent = null,
    ): string {
        $useToon   = $aiAgent?->use_toon_format ?? false;
        $catalogId = $aiAgent?->catalog_id ?? null;

        try {
            return match ($functionName) {
                'get_all_products' => $this->dispatchGetAllProducts($conversation, $userId, $arguments, $useToon, $catalogId),
                'search_products' => $this->dispatchSearchProducts($userId, $arguments, $useToon, $catalogId),
                'search_multiple_products' => $this->dispatchSearchMultipleProducts($userId, $arguments, $useToon, $catalogId),
                'get_product_details' => $this->dispatchGetProductDetails($userId, $arguments, $catalogId),
                'add_to_cart' => $this->dispatchAddToCart($conversation, $userId, $arguments),
                'get_cart_summary' => $this->cartTools->summary($conversation, $userId, $aiAgent),
                'confirm_order' => $this->checkoutTools->confirmOrder($conversation, $userId, $aiAgent),
                'set_order_notes' => $this->checkoutTools->setOrderNotes($conversation, $arguments),
                'remove_from_cart' => $this->dispatchRemoveFromCart($conversation, $arguments),
                'clear_cart' => $this->cartTools->clear($conversation),
                'generate_qris' => $this->dispatchGenerateQris($conversation, $userId, $arguments),
                'check_payment_status' => $this->paymentTools->checkPaymentStatus($conversation),
                default => "Fungsi '{$functionName}' tidak dikenali.",
            };
        } catch (\Exception $e) {
            Log::error("Tool call error: {$functionName}", [
                'user_id' => $userId,
                'tool_name' => $functionName,
                'parameters' => $arguments,
                'error_message' => $e->getMessage(),
            ]);

            return 'Maaf, terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi.';
        }
    }

    protected function dispatchGetAllProducts(AiAgentConversation $conversation, int $userId, array $arguments, bool $useToon, ?string $catalogId = null): string
    {
        $page   = isset($arguments['page']) ? (int) $arguments['page'] : 1;
        $search = $arguments['search'] ?? null;

        $result = $this->catalogTools->getAllProducts($userId, $useToon, $page, $search, $catalogId);

        if (! str_starts_with($result, 'EMPTY')) {
            $conversation->setCurrentMenuPage($page);
        }

        return $result;
    }

    protected function dispatchSearchProducts(int $userId, array $arguments, bool $useToon, ?string $catalogId = null): string
    {
        if (! isset($arguments['query'])) {
            return 'Maaf, parameter pencarian tidak lengkap. Mohon berikan kata kunci pencarian.';
        }

        return $this->catalogTools->searchProducts($userId, $arguments['query'], $useToon, $catalogId);
    }

    protected function dispatchSearchMultipleProducts(int $userId, array $arguments, bool $useToon, ?string $catalogId = null): string
    {
        if (! isset($arguments['queries']) || ! is_array($arguments['queries'])) {
            return 'Maaf, parameter pencarian tidak lengkap. Mohon berikan array kata kunci pencarian.';
        }

        return $this->catalogTools->searchMultipleProducts($userId, $arguments['queries'], $useToon, $catalogId);
    }

    protected function dispatchGetProductDetails(int $userId, array $arguments, ?string $catalogId = null): string
    {
        if (! isset($arguments['product_id']) || ! is_numeric($arguments['product_id'])) {
            return 'Maaf, parameter tidak lengkap atau ID produk bukan angka.';
        }

        return $this->catalogTools->getProductDetails($userId, (int) $arguments['product_id'], $catalogId);
    }

    /**
     * Add-to-cart supports four shapes (in order of preference):
     *   - items: [{product_name, quantity}, ...]  ← canonical, name-based
     *   - products: [{product_id, quantity}, ...]
     *   - product_name + quantity
     *   - product_id + quantity                   ← legacy
     */
    protected function dispatchAddToCart(AiAgentConversation $conversation, int $userId, array $arguments): string
    {
        if (isset($arguments['items']) && is_array($arguments['items'])) {
            return $this->cartTools->addByName($conversation, $userId, $arguments['items']);
        }
        if (isset($arguments['products']) && is_array($arguments['products'])) {
            return $this->cartTools->addMultiById($conversation, $userId, $arguments['products']);
        }
        if (isset($arguments['product_name'])) {
            $quantity = isset($arguments['quantity']) ? (int) $arguments['quantity'] : 1;

            return $this->cartTools->addByName($conversation, $userId, [
                ['product_name' => $arguments['product_name'], 'quantity' => $quantity],
            ]);
        }
        if (isset($arguments['product_id'], $arguments['quantity'])) {
            if (! is_numeric($arguments['product_id']) || ! is_numeric($arguments['quantity'])) {
                return 'Maaf, ID produk dan jumlah pesanan harus berupa angka.';
            }

            return $this->cartTools->addById(
                $conversation,
                $userId,
                (int) $arguments['product_id'],
                (int) $arguments['quantity'],
            );
        }

        return 'Maaf, parameter tidak lengkap. Mohon berikan items dengan product_name dan quantity.';
    }

    protected function dispatchRemoveFromCart(AiAgentConversation $conversation, array $arguments): string
    {
        if (! isset($arguments['product_name'])) {
            return 'Maaf, mohon sebutkan nama produk yang ingin dihapus dari keranjang.';
        }

        return $this->cartTools->remove($conversation, $arguments['product_name']);
    }

    protected function dispatchGenerateQris(AiAgentConversation $conversation, int $userId, array $arguments): string
    {
        if (! isset($arguments['amount']) || ! is_numeric($arguments['amount'])) {
            return 'Maaf, parameter tidak lengkap atau jumlah pembayaran bukan angka.';
        }

        return $this->paymentTools->generateQris(
            $conversation,
            $userId,
            (float) $arguments['amount'],
            $arguments['description'] ?? null,
        );
    }

    /**
     * Look back at the last few assistant messages to detect a search→search
     * loop. Two recent search-result responses is the trigger to stop.
     */
    protected function detectSearchLoop(AiAgentConversation $conversation): bool
    {
        $lastMessages = array_slice($conversation->messages ?? [], -5);
        $recentSearchCount = 0;
        foreach ($lastMessages as $msg) {
            if (isset($msg['type']) && $msg['type'] === 'ai'
                && (stripos($msg['content'] ?? '', 'HASIL PENCARIAN') !== false
                    || stripos($msg['content'] ?? '', 'produk yang saya temukan') !== false)) {
                $recentSearchCount++;
            }
        }

        return $recentSearchCount >= 2;
    }

    /**
     * Assemble the message envelope for the follow-up LLM call: last 2
     * human/ai messages + the assistant tool_calls envelope + tool results.
     * Long tool results are truncated to keep follow-up calls cheap.
     */
    protected function buildFollowUpMessages(AiAgentConversation $conversation, array $toolCalls, array $toolResults): array
    {
        $messages = array_slice($conversation->messages ?? [], -2);

        $messages[] = [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => $toolCalls,
        ];

        foreach ($toolResults as $tr) {
            $content = $tr['result'];
            if (strlen($content) > 1500) {
                $content = substr($content, 0, 1500)."\n[TRUNCATED]";
            }
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $tr['tool_call_id'],
                'content' => $content,
            ];
        }

        return $messages;
    }

    /**
     * Best-effort canned reply when the LLM returns empty content (e.g.
     * after a search), keyed off the customer's last message + cart state.
     * Tries to be more useful than a flat "I don't understand".
     */
    public function generateContextualFallback(array $toolResults, AiAgentConversation $conversation): string
    {
        $toolNames = array_map(fn ($tr) => $tr['function_name'], $toolResults);
        $hasSearch = ! empty(array_intersect($toolNames, ['search_products', 'search_multiple_products']));

        $lastUserMessage = $this->lastUserMessage($conversation);

        $foundProducts = [];
        foreach ($toolResults as $tr) {
            if (in_array($tr['function_name'], ['search_products', 'search_multiple_products'])) {
                if (preg_match_all('/\d+\.\s*([^\n]+?)\s*-\s*Rp/', $tr['result'], $matches)) {
                    $foundProducts = array_merge($foundProducts, $matches[1]);
                }
            }
        }

        if ($hasSearch && ! empty($foundProducts)) {
            $productList = implode(', ', array_slice($foundProducts, 0, 3));

            if (stripos($lastUserMessage, 'pesan') !== false
                || stripos($lastUserMessage, 'beli') !== false
                || stripos($lastUserMessage, 'order') !== false) {
                return "Saya menemukan produk yang Anda cari: {$productList}. "
                    ."Berapa jumlah yang ingin Anda pesan? Contoh: 'pesan {$foundProducts[0]} 2 porsi'";
            }
            if (stripos($lastUserMessage, 'menu') !== false
                || stripos($lastUserMessage, 'ada apa') !== false
                || stripos($lastUserMessage, 'daftar') !== false) {
                return "Kami punya: {$productList}".(count($foundProducts) > 3 ? ' dan lainnya' : '').'. '
                    .'Mau pesan yang mana?';
            }

            return "Saya menemukan: {$productList}. Ada yang ingin Anda pesan?";
        }

        if ($hasSearch && empty($foundProducts)) {
            return stripos($lastUserMessage, 'pesan') !== false || stripos($lastUserMessage, 'beli') !== false
                ? "Maaf, produk yang Anda cari tidak tersedia. Ketik 'menu' untuk melihat daftar produk kami."
                : "Maaf, tidak ada produk yang sesuai dengan pencarian Anda. Bisa coba kata kunci lain atau ketik 'menu' untuk lihat semua produk.";
        }

        $cart = $conversation->getCart();
        if (! empty($cart) && (stripos($lastUserMessage, 'keranjang') !== false
            || stripos($lastUserMessage, 'cart') !== false
            || stripos($lastUserMessage, 'pesanan') !== false)) {
            return 'Anda punya '.count($cart)." item di keranjang. Ketik 'lihat keranjang' untuk detail atau 'konfirmasi' untuk checkout.";
        }

        if (! empty($cart)) {
            return 'Anda punya pesanan di keranjang. Mau tambah item lagi atau langsung checkout?';
        }

        return 'Maaf, saya kurang mengerti maksud Anda. Bisa dijelaskan lebih detail? '
            ."Contoh: 'lihat menu', 'pesan nasi goreng 2', atau 'lihat keranjang'.";
    }

    /**
     * Companion to generateContextualFallback for the case where there
     * were no tool calls at all (LLM returned empty content directly).
     */
    public function generateSimpleFallback(AiAgentConversation $conversation): string
    {
        $lastUserMessage = $this->lastUserMessage($conversation);
        $cart = $conversation->getCart();

        if (preg_match('/^(halo|hai|hi|hello|hey|assalamualaikum|selamat)/i', $lastUserMessage)) {
            return "Halo! Ada yang bisa saya bantu? Ketik 'menu' untuk lihat produk kami.";
        }

        if (stripos($lastUserMessage, 'menu') !== false
            || stripos($lastUserMessage, 'produk') !== false
            || stripos($lastUserMessage, 'jual apa') !== false) {
            return "Untuk melihat menu lengkap, ketik 'lihat menu'. Atau sebutkan produk yang Anda cari.";
        }

        if (stripos($lastUserMessage, 'pesan') !== false
            || stripos($lastUserMessage, 'beli') !== false
            || stripos($lastUserMessage, 'order') !== false) {
            return ! empty($cart)
                ? 'Anda sudah punya '.count($cart)." item di keranjang. Mau tambah lagi atau checkout? Ketik 'lihat keranjang'."
                : "Silakan sebutkan produk yang ingin Anda pesan. Contoh: 'pesan nasi goreng 2 porsi'.";
        }

        if (stripos($lastUserMessage, 'keranjang') !== false || stripos($lastUserMessage, 'cart') !== false) {
            return ! empty($cart)
                ? "Ketik 'lihat keranjang' untuk melihat detail pesanan Anda."
                : "Keranjang Anda masih kosong. Silakan pesan produk terlebih dahulu.";
        }

        if (preg_match('/(terima kasih|thanks|thank you|makasih)/i', $lastUserMessage)) {
            return 'Sama-sama! Ada lagi yang bisa saya bantu?';
        }

        if (! empty($cart)) {
            return 'Anda punya '.count($cart)." item di keranjang. Ketik 'lihat keranjang' untuk detail atau 'konfirmasi' untuk checkout.";
        }

        return "Maaf, saya kurang mengerti. Coba: 'menu', 'pesan [produk] [jumlah]', atau 'bantuan'.";
    }

    protected function lastUserMessage(AiAgentConversation $conversation): string
    {
        $messages = $conversation->messages ?? [];
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['type'] ?? null) === 'human') {
                return strtolower($messages[$i]['content'] ?? '');
            }
        }

        return '';
    }

    /**
     * Tool definitions ALWAYS attached when ordering is enabled. Descriptions
     * are intentionally terse — the system prompt covers usage guidance, the
     * LLM picks tools by name + schema shape.
     */
    protected function baseDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_all_products',
                    'description' => 'Show menu list to user. Only use when user asks to see menu.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page' => ['type' => 'integer'],
                            'search' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_to_cart',
                    'description' => 'Add items to cart by product name. Returns an error with up to 3 alternative suggestions if the name is not found or is ambiguous (matches more than one product). When that happens, ask the customer to pick from the alternatives instead of guessing.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'items' => [
                                'type' => 'array',
                                'description' => 'Array of items to add. Can add multiple items at once.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'product_name' => ['type' => 'string', 'description' => 'Product name. Use the exact menu name when possible; close matches are accepted but ambiguous ones are rejected.'],
                                        'quantity' => ['type' => 'integer', 'description' => 'Quantity to order, default 1'],
                                    ],
                                    'required' => ['product_name', 'quantity'],
                                ],
                            ],
                        ],
                        'required' => ['items'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'remove_from_cart',
                    'description' => 'Remove specific item from cart by product name',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_name' => ['type' => 'string', 'description' => 'Product name to remove from cart'],
                        ],
                        'required' => ['product_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'clear_cart',
                    'description' => 'Clear all items from cart',
                    'parameters' => ['type' => 'object', 'properties' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_cart_summary',
                    'description' => 'Cart',
                    'parameters' => ['type' => 'object', 'properties' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'confirm_order',
                    'description' => 'Checkout',
                    'parameters' => ['type' => 'object', 'properties' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'set_order_notes',
                    'description' => 'Save special instructions/catatan from customer. Call when user provides notes or says "tidak ada".',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'notes' => ['type' => 'string', 'description' => 'Customer notes, e.g. tidak pedas, tanpa bawang. Use empty string if customer has no notes.'],
                        ],
                        'required' => ['notes'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Additional tools attached only when the merchant has QRIS configured.
     */
    protected function qrisDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_qris',
                    'description' => 'QRIS payment',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'amount' => ['type' => 'number'],
                            'description' => ['type' => 'string'],
                        ],
                        'required' => ['amount'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_payment_status',
                    'description' => 'Check payment',
                    'parameters' => ['type' => 'object', 'properties' => []],
                ],
            ],
        ];
    }
}
