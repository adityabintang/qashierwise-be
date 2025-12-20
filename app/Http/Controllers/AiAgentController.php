<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAiAgentRequest;
use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAgentController extends Controller
{
    protected AiAgentService $aiAgentService;

    public function __construct(AiAgentService $aiAgentService)
    {
        $this->aiAgentService = $aiAgentService;
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
            $conversation->addMessage('user', $request->message);

            // Build system prompt
            $systemPrompt = $aiAgent->buildSystemPrompt($userId);

            // Get tool definitions (make method public for test endpoint)
            $tools = null;
            if ($aiAgent->isOrderEnabled()) {
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
                ];
            }

            // Call LLM
            $response = $this->aiAgentService->callLLM(
                $systemPrompt,
                $conversation->messages ?? [],
                $tools
            );

            // Handle tool calls if present
            $responseContent = '';
            if (isset($response['tool_calls'])) {
                $responseContent = $this->handleTestToolCalls($response['tool_calls'], $userId);
            } else {
                $responseContent = $response['content'] ?? 'No response generated';
            }

            // Add assistant message
            $conversation->addMessage('assistant', $responseContent);

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
     * Handle tool calls for test endpoint.
     */
    protected function handleTestToolCalls(array $toolCalls, int $userId): string
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $functionName = $toolCall['function']['name'] ?? null;
            $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);

            $result = $this->executeTestToolCall($functionName, $arguments, $userId);
            $results[] = $result;
        }

        return implode("\n\n", array_filter($results));
    }

    /**
     * Execute a tool call for test endpoint.
     */
    protected function executeTestToolCall(string $functionName, array $arguments, int $userId): string
    {
        try {
            switch ($functionName) {
                case 'search_products':
                    return $this->searchProducts($userId, $arguments['query'] ?? '');

                case 'get_all_products':
                    return $this->getAllProducts($userId);

                default:
                    return "Fungsi '{$functionName}' tidak dikenali.";
            }
        } catch (\Exception $e) {
            return 'Maaf, terjadi kesalahan saat memproses permintaan Anda.';
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
