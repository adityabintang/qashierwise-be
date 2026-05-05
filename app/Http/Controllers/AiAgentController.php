<?php

namespace App\Http\Controllers;

use App\Enums\UserIntent;
use App\Http\Requests\StoreAiAgentRequest;
use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\QrisTransaction;
use App\Models\Store;
use App\Models\SubMerchant;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgentService;
use App\Services\OrderService;
use App\Services\QrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            $userId = auth()->user()->getEffectiveUserId();

            $whatsappAccount = WhatsAppAccount::where('user_id', $userId)->first();

            if (! $whatsappAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp account not connected',
                ], 404);
            }

            $aiAgent = AiAgent::where('whatsapp_account_id', $whatsappAccount->id)->first();

            $hasSubMerchant = SubMerchant::where('user_id', $userId)->exists();

            if (! $aiAgent) {
                return response()->json([
                    'success' => true,
                    'message' => 'AI Agent not configured yet',
                    'data' => null,
                    'has_sub_merchant' => $hasSubMerchant,
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
                    'reservation_enabled' => $aiAgent->reservation_enabled,
                    'delivery_enabled' => $aiAgent->delivery_enabled,
                    'default_ongkir' => $aiAgent->default_ongkir,
                    'is_active' => $aiAgent->is_active,
                    'settings' => $aiAgent->settings,
                    'created_at' => $aiAgent->created_at,
                    'updated_at' => $aiAgent->updated_at,
                ],
                'has_sub_merchant' => $hasSubMerchant,
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
            $userId = auth()->user()->getEffectiveUserId();

            $whatsappAccount = WhatsAppAccount::where('user_id', $userId)->first();

            if (! $whatsappAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp account not connected. Please connect your WhatsApp Business Account first.',
                ], 404);
            }

            // Validate default_store_id belongs to user
            if ($request->filled('default_store_id')) {
                $storeExists = Store::where('id', $request->default_store_id)
                    ->where('user_id', $userId)
                    ->exists();

                if (! $storeExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid store. Store not found or does not belong to you.',
                    ], 422);
                }
            }

            // Validate sub-merchant exists before enabling QRIS
            if ($request->boolean('qris_enabled', false)) {
                $hasSubMerchant = SubMerchant::where('user_id', $userId)->exists();
                if (! $hasSubMerchant) {
                    return response()->json([
                        'success' => false,
                        'message' => 'QRIS Payment tidak bisa diaktifkan. Silakan buat Sub Merchant terlebih dahulu di halaman Sub Merchant.',
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
                    'reservation_enabled' => $request->boolean('reservation_enabled', false),
                    'delivery_enabled' => $request->boolean('delivery_enabled', false),
                    'default_ongkir' => $request->filled('default_ongkir') ? $request->input('default_ongkir') : 0,
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
                    'reservation_enabled' => $aiAgent->reservation_enabled,
                    'delivery_enabled' => $aiAgent->delivery_enabled,
                    'default_ongkir' => $aiAgent->default_ongkir,
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
            $userId = auth()->user()->getEffectiveUserId();

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
            $userId = auth()->user()->getEffectiveUserId();

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
     * Toggle delivery feature.
     */
    public function toggleDelivery(Request $request): JsonResponse
    {
        try {
            $userId = auth()->user()->getEffectiveUserId();

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

            if (! $aiAgent->order_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot enable delivery. Please enable order feature first.',
                ], 422);
            }

            $aiAgent->delivery_enabled = ! $aiAgent->delivery_enabled;
            $aiAgent->save();

            return response()->json([
                'success' => true,
                'message' => 'Delivery feature '.($aiAgent->delivery_enabled ? 'enabled' : 'disabled'),
                'data' => [
                    'delivery_enabled' => $aiAgent->delivery_enabled,
                    'default_ongkir' => $aiAgent->default_ongkir,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle delivery feature',
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
            $userId = auth()->user()->getEffectiveUserId();

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
            if (! $aiAgent->qris_enabled) {
                $errors = $aiAgent->validateQrisConfiguration();

                if (! empty($errors)) {
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
            $userId = auth()->user()->getEffectiveUserId();

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
            // withoutGlobalScopes() is needed to avoid unique constraint violation:
            // the global scope filters SELECT results, causing firstOrCreate to miss existing records
            $testContact = WhatsAppContact::withoutGlobalScopes()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'wa_id' => 'test_user_'.$userId,
                    'phone_number_id' => $whatsappAccount->phone_number_id,
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

            // Hard guard for test endpoint: block menu/order flow when ordering is disabled
            if (! $aiAgent->isOrderEnabled() && $this->aiAgentService->isOrderMenuIntent($request->message)) {
                Log::info('Test AI Agent blocked before LLM (order disabled)', [
                    'agent_id' => $aiAgent->id,
                    'user_id' => $userId,
                    'message' => $request->message,
                    'reservation_enabled' => $aiAgent->isReservationEnabled(),
                ]);

                $responseContent = $this->aiAgentService->getOrderDisabledMessage($aiAgent);
                $conversation->addMessage('ai', $responseContent);

                // Refresh to get latest messages from database
                $conversation->refresh();

                $conversationHistory = array_map(function ($msg) {
                    return [
                        'role' => $msg['type'] === 'human' ? 'user' : 'assistant',
                        'content' => $msg['content'],
                        'timestamp' => $msg['timestamp'] ?? null,
                    ];
                }, $conversation->messages ?? []);

                return response()->json([
                    'success' => true,
                    'message' => 'Test message processed',
                    'data' => [
                        'user_message' => $request->message,
                        'ai_response' => $responseContent,
                        'conversation_history' => $conversationHistory,
                    ],
                ], 200);
            }

            // Detect user intent for optimization
            $userIntent = UserIntent::detect($request->message);

            // Build system prompt with intent-based optimization
            $systemPrompt = $aiAgent->buildSystemPrompt($userId, $request->message);

            // Conditional tool loading based on intent (saves ~500-800 tokens for simple messages)
            $tools = null;
            if ($this->intentNeedsTools($userIntent)) {
                $tools = $this->getTestToolDefinitions($aiAgent);
            }

            // Log token optimization info
            Log::info('Test endpoint using intent-based optimization', [
                'user_intent' => $userIntent->value,
                'tools_loaded' => $tools !== null,
                'message' => substr($request->message, 0, 50),
            ]);

            // Call LLM
            $response = $this->aiAgentService->callLLM(
                $systemPrompt,
                $conversation->messages ?? [],
                $tools,
                $aiAgent
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

            // Convert messages format from type to role for test compatibility
            $conversationHistory = array_map(function ($msg) {
                return [
                    'role' => $msg['type'] === 'human' ? 'user' : 'assistant',
                    'content' => $msg['content'],
                    'timestamp' => $msg['timestamp'] ?? null,
                ];
            }, $conversation->messages ?? []);

            return response()->json([
                'success' => true,
                'message' => 'Test message processed',
                'data' => [
                    'user_message' => $request->message,
                    'ai_response' => $responseContent,
                    'conversation_history' => $conversationHistory,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Test AI Agent failed', [
                'user_id' => auth()->user()->getEffectiveUserId(),
                'message' => $request->message ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process test message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test AI Agent with streaming SSE response (word-by-word typing effect).
     * Same logic as test(), but streams the response incrementally.
     */
    public function testStream(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        // Resolve everything before entering the stream closure
        try {
            $userId = auth()->user()->getEffectiveUserId();

            $whatsappAccount = WhatsAppAccount::where('user_id', $userId)->first();
            if (! $whatsappAccount) {
                return $this->sseError('WhatsApp account not connected');
            }

            $aiAgent = AiAgent::where('whatsapp_account_id', $whatsappAccount->id)
                ->where('is_active', true)
                ->first();
            if (! $aiAgent) {
                return $this->sseError('AI Agent not configured or not active');
            }

            $testContact = WhatsAppContact::withoutGlobalScopes()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'wa_id' => 'test_user_'.$userId,
                    'phone_number_id' => $whatsappAccount->phone_number_id,
                ],
                ['name' => 'Test Contact']
            );

            $conversation = $this->aiAgentService->getOrCreateConversation(
                $aiAgent->id,
                $testContact->id
            );

            $conversation->addMessage('human', $request->message);

            // Hard guard: block order flow when ordering is disabled
            if (! $aiAgent->isOrderEnabled() && $this->aiAgentService->isOrderMenuIntent($request->message)) {
                $responseContent = $this->aiAgentService->getOrderDisabledMessage($aiAgent);
                $conversation->addMessage('ai', $responseContent);

                return $this->sseStream($responseContent);
            }

            $userIntent = UserIntent::detect($request->message);
            $systemPrompt = $aiAgent->buildSystemPrompt($userId, $request->message);
            $tools = $this->intentNeedsTools($userIntent)
                ? $this->getTestToolDefinitions($aiAgent)
                : null;

            $response = $this->aiAgentService->callLLM(
                $systemPrompt,
                $conversation->messages ?? [],
                $tools,
                $aiAgent
            );

            $responseContent = isset($response['tool_calls'])
                ? $this->handleTestToolCalls($response['tool_calls'], $userId, $conversation, $aiAgent)
                : ($response['content'] ?? 'No response generated');

            $conversation->addMessage('ai', $responseContent);

        } catch (\Exception $e) {
            Log::error('Test AI Agent stream failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->sseError('Failed to process message: '.$e->getMessage());
        }

        return $this->sseStream($responseContent);
    }

    /** Stream $text word-by-word as Server-Sent Events. */
    private function sseStream(string $text): StreamedResponse
    {
        return response()->stream(function () use ($text) {
            // Disable output buffering so chunks reach the browser immediately
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Split into words, preserving the space after each word
            $words = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            foreach ($words as $word) {
                echo 'data: '.json_encode(['type' => 'token', 'content' => $word])."\n\n";
                flush();
                usleep(30000); // 30 ms between tokens — adjust for feel
            }

            echo 'data: '.json_encode(['type' => 'done'])."\n\n";
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',  // disable nginx buffering
            'Connection'        => 'keep-alive',
        ]);
    }

    /** Return an immediate SSE stream that sends a single error event. */
    private function sseError(string $message): StreamedResponse
    {
        return response()->stream(function () use ($message) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            echo 'data: '.json_encode(['type' => 'error', 'message' => $message])."\n\n";
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * Check if user intent requires tools to be loaded.
     * Greeting, off-topic, and business info intents don't need tools.
     * This saves ~500-800 tokens per request for simple messages.
     */
    protected function intentNeedsTools(UserIntent $intent): bool
    {
        $noToolIntents = [
            UserIntent::GREETING,
            UserIntent::OFF_TOPIC,
            UserIntent::BUSINESS_INFO,
        ];

        return ! in_array($intent, $noToolIntents);
    }

    /**
     * Get tool definitions for test endpoint.
     * OPTIMIZED: Minimal tools (~150 tokens) to keep total under 1000
     */
    protected function getTestToolDefinitions(AiAgent $aiAgent): ?array
    {
        if (! $aiAgent->isOrderEnabled()) {
            return null;
        }

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_all_products',
                    'description' => 'Menu list',
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
                    'description' => 'Order items by name',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'product_name' => ['type' => 'string'],
                                        'quantity' => ['type' => 'integer'],
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
        ];

        // Add QRIS tools if enabled (minimal)
        if ($aiAgent->isQrisEnabled()) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_qris',
                    'description' => 'QRIS payment',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'amount' => ['type' => 'number'],
                        ],
                        'required' => ['amount'],
                    ],
                ],
            ];

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'check_payment_status',
                    'description' => 'Check payment',
                    'parameters' => ['type' => 'object', 'properties' => []],
                ],
            ];
        }

        // Add delivery tools if enabled
        if ($aiAgent->isDeliveryEnabled()) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'set_delivery_type',
                    'description' => 'Set delivery or pickup',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'delivery_type' => [
                                'type' => 'string',
                                'enum' => ['pickup', 'delivery'],
                            ],
                            'address' => ['type' => 'string'],
                        ],
                        'required' => ['delivery_type'],
                    ],
                ],
            ];

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'set_order_notes',
                    'description' => 'Save special instructions/catatan. Call when user provides notes or says "tidak ada".',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'notes' => ['type' => 'string', 'description' => 'Customer notes. Use empty string if no notes.'],
                        ],
                        'required' => ['notes'],
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

            // Ensure arguments is always an array
            if (! is_array($arguments)) {
                $arguments = [];
            }

            Log::info('Executing test tool call', [
                'function' => $functionName,
                'arguments' => $arguments,
            ]);

            $result = $this->executeTestToolCall($functionName, $arguments, $userId, $conversation, $aiAgent);

            // For search calls, don't show raw results to user - just log them
            if (in_array($functionName, ['search_products', 'search_multiple_products'])) {
                Log::info('Search result (internal)', ['result' => $result]);

                // Don't add to results - search is internal only
                continue;
            }

            if (! empty($result)) {
                $results[] = $result;
            }
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
                    if (! isset($arguments['query'])) {
                        return 'Maaf, parameter pencarian tidak lengkap. Mohon berikan kata kunci pencarian.';
                    }

                    return $this->searchProducts($userId, $arguments['query']);

                case 'search_multiple_products':
                    if (! isset($arguments['queries']) || ! is_array($arguments['queries'])) {
                        return 'Maaf, parameter pencarian tidak lengkap. Mohon berikan array kata kunci.';
                    }

                    return $this->searchMultipleProducts($userId, $arguments['queries']);

                case 'get_all_products':
                    $page = isset($arguments['page']) ? (int) $arguments['page'] : 1;

                    return $this->getAllProducts($userId, $page);

                case 'get_product_details':
                    if (! isset($arguments['product_id'])) {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan ID produk.';
                    }
                    if (! is_numeric($arguments['product_id'])) {
                        return 'Maaf, ID produk harus berupa angka.';
                    }

                    return $this->getProductDetails($userId, (int) $arguments['product_id']);

                case 'add_to_cart':
                    // Support items array with product_name (new format)
                    if (isset($arguments['items']) && is_array($arguments['items'])) {
                        return $this->addItemsByName($conversation, $userId, $arguments['items']);
                    }
                    // Support products array with product_id (legacy)
                    elseif (isset($arguments['products']) && is_array($arguments['products'])) {
                        return $this->addMultipleToCart($conversation, $userId, $arguments['products']);
                    }
                    // Support single product_name
                    elseif (isset($arguments['product_name'])) {
                        if (! isset($arguments['quantity'])) {
                            return 'Maaf, parameter tidak lengkap. Mohon berikan jumlah pesanan.';
                        }

                        return $this->addToCartByName(
                            $conversation,
                            $userId,
                            $arguments['product_name'],
                            (int) $arguments['quantity']
                        );
                    }
                    // Support single product_id (legacy)
                    elseif (isset($arguments['product_id'])) {
                        if (! isset($arguments['quantity'])) {
                            return 'Maaf, parameter tidak lengkap. Mohon berikan jumlah pesanan.';
                        }

                        return $this->addToCart(
                            $conversation,
                            $userId,
                            (int) $arguments['product_id'],
                            (int) $arguments['quantity']
                        );
                    } else {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan items atau nama produk.';
                    }

                case 'get_cart_summary':
                    return $this->getCartSummary($conversation);

                case 'clear_cart':
                    return $this->clearCart($conversation);

                case 'remove_from_cart':
                    if (! isset($arguments['product_name'])) {
                        return 'Maaf, mohon sebutkan nama produk yang ingin dihapus.';
                    }

                    return $this->removeFromCart($conversation, $arguments['product_name']);

                case 'confirm_order':
                    return $this->confirmOrder($conversation, $aiAgent, $userId);

                case 'set_delivery_type':
                    if (! isset($arguments['delivery_type'])) {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan tipe pengiriman (pickup/delivery).';
                    }
                    $validTypes = [Order::DELIVERY_TYPE_PICKUP, Order::DELIVERY_TYPE_DELIVERY];
                    if (! in_array($arguments['delivery_type'], $validTypes)) {
                        return 'Maaf, tipe pengiriman tidak valid. Gunakan "pickup" atau "delivery".';
                    }

                    return $this->setDeliveryType(
                        $conversation,
                        $aiAgent,
                        $arguments['delivery_type'],
                        $arguments['address'] ?? null
                    );

                case 'set_order_notes':
                    return $this->setOrderNotes($conversation, $arguments['notes'] ?? '');

                case 'generate_qris':
                    if (! isset($arguments['amount'])) {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan jumlah pembayaran.';
                    }
                    if (! is_numeric($arguments['amount'])) {
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
            if (! $aiAgent->isQrisEnabled()) {
                $errors = $aiAgent->validateQrisConfiguration();

                return 'Maaf, pembayaran QRIS belum tersedia. '.implode(' ', $errors);
            }

            // Get SubMerchant
            $subMerchant = $aiAgent->getSubMerchant();
            if (! $subMerchant) {
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
            $formattedAmount = 'Rp '.number_format($amount, 0, ',', '.');
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
            return 'Maaf, terjadi kesalahan saat membuat kode pembayaran: '.$e->getMessage();
        }
    }

    /**
     * Check payment status for test endpoint.
     */
    protected function checkTestPaymentStatus(AiAgentConversation $conversation): string
    {
        try {
            $qrisTransaction = $conversation->getCurrentQrisTransaction();

            // currentQrisTransaction is null when clearPaymentContext() was already called
            // (e.g. after webhook fires). Fall back to last_qris_transaction_id in order_context.
            if (! $qrisTransaction) {
                $lastId = $conversation->order_context['last_qris_transaction_id'] ?? null;
                if ($lastId) {
                    $qrisTransaction = QrisTransaction::find($lastId);
                }
            }

            if (! $qrisTransaction) {
                return 'Tidak ada pembayaran yang sedang diproses. Silakan buat pesanan terlebih dahulu.';
            }

            // Refresh from database to get latest status
            $qrisTransaction->refresh();

            $formattedAmount = 'Rp '.number_format($qrisTransaction->amount, 0, ',', '.');

            switch ($qrisTransaction->status) {
                case QrisTransaction::STATUS_SETTLEMENT:
                    // Build confirmation message with delivery info
                    $response = "✅ Pembayaran Berhasil!\n\n";
                    $response .= "Jumlah: {$formattedAmount}\n";
                    $response .= "No. Transaksi: {$qrisTransaction->order_id}\n\n";

                    // Add delivery-specific confirmation
                    $deliveryType = $conversation->order_context['delivery_type'] ?? null;
                    if ($deliveryType === Order::DELIVERY_TYPE_DELIVERY) {
                        $address = $conversation->order_context['delivery_address'] ?? null;
                        $response .= "🚚 Pesanan Anda sedang disiapkan dan akan segera diantar";
                        if ($address) {
                            $response .= " ke:\n📍 {$address}";
                        }
                        $response .= "\n\n";
                        $response .= "Mohon siapkan diri untuk menerima pesanan. Terima kasih! 🙏";
                    } else {
                        $response .= "Pesanan Anda sedang diproses. Terima kasih! 🙏";
                    }

                    // Clear payment context so it's not double-counted
                    $conversation->clearPaymentContext();

                    return $response;

                case QrisTransaction::STATUS_PENDING:
                    if ($qrisTransaction->isExpired()) {
                        return '⏰ Kode pembayaran sudah kadaluarsa. Ketik \'buat qris baru\' untuk melanjutkan.';
                    }
                    $remainingMinutes = ceil($qrisTransaction->getRemainingTimeInSeconds() / 60);

                    return "⏳ Pembayaran Menunggu\n\n".
                           "Jumlah: {$formattedAmount}\n".
                           "Sisa waktu: {$remainingMinutes} menit\n\n".
                           'Silakan selesaikan pembayaran melalui link yang sudah diberikan.';

                case QrisTransaction::STATUS_EXPIRE:
                    return '⏰ Kode pembayaran sudah kadaluarsa. Ketik \'buat qris baru\' untuk mendapatkan kode baru.';

                case QrisTransaction::STATUS_CANCEL:
                    return '❌ Pembayaran dibatalkan. Silakan buat pesanan baru jika ingin melanjutkan.';

                default:
                    return "Status pembayaran: {$qrisTransaction->status}";
            }

        } catch (\Exception $e) {
            return 'Maaf, terjadi kesalahan saat mengecek status pembayaran. Silakan coba lagi.';
        }
    }

    /**
     * Search products by query.
     */
    protected function searchProducts(int $userId, string $query): string
    {
        $productsQuery = Product::where('user_id', $userId)
            ->where('is_active', true);

        // If query is empty or generic like "menu", get all products
        if (empty($query) || in_array(strtolower($query), ['menu', 'semua', 'all', 'produk', 'daftar'])) {
            return $this->getAllProducts($userId);
        }

        // Clean and normalize query for case-insensitive search
        $cleanQuery = trim(strtolower($query));
        $keywords = explode(' ', $cleanQuery);

        $products = $productsQuery
            ->where(function ($q) use ($cleanQuery, $keywords) {
                // Case-insensitive search using LOWER()
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$cleanQuery}%"])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$cleanQuery}%"]);

                // Also match if ANY keyword is present (more flexible)
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) >= 2) {
                        $q->orWhereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"]);
                    }
                }
            })
            ->limit(10)
            ->get(['id', 'name', 'price', 'stock_quantity', 'description']);

        if ($products->isEmpty()) {
            return "Maaf, tidak ada produk yang ditemukan dengan kata kunci '{$query}'.";
        }

        $response = "Berikut produk yang saya temukan:\n\n";
        foreach ($products as $product) {
            $response .= "🔹 {$product->name} [ID:{$product->id}]\n";
            $response .= '   Harga: Rp '.number_format($product->price, 0, ',', '.')."\n";
            if ($product->stock_quantity !== null) {
                $response .= "   Stok: {$product->stock_quantity}\n";
            }
            $response .= "\n";
        }

        return $response;
    }

    /**
     * Search multiple products by multiple queries at once.
     */
    protected function searchMultipleProducts(int $userId, array $queries): string
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
                ->get(['id', 'name', 'price', 'stock_quantity']);

            if ($products->isEmpty()) {
                $notFound[] = $query;
            } else {
                foreach ($products as $product) {
                    if (! isset($allResults[$product->id])) {
                        $allResults[$product->id] = [
                            'id' => $product->id,
                            'name' => $product->name,
                            'price' => $product->price,
                            'stock' => $product->stock_quantity,
                        ];
                    }
                }
            }
        }

        if (empty($allResults) && ! empty($notFound)) {
            return 'Maaf, tidak ada produk yang ditemukan untuk: '.implode(', ', $notFound);
        }

        $response = "Berikut produk yang saya temukan:\n\n";
        foreach ($allResults as $product) {
            $response .= "🔹 {$product['name']} [ID:{$product['id']}]\n";
            $response .= '   Harga: Rp '.number_format($product['price'], 0, ',', '.')."\n";
            $response .= "   Stok: {$product['stock']}\n\n";
        }

        if (! empty($notFound)) {
            $response .= '⚠️ Tidak ditemukan: '.implode(', ', $notFound)."\n\n";
        }

        $response .= '**INSTRUKSI**: Gunakan ID di atas untuk add_to_cart.';

        return $response;
    }

    /**
     * Get all active products with pagination (20 per page), grouped by category.
     * Formatted directly for user display (no internal instructions).
     */
    protected function getAllProducts(int $userId, int $page = 1): string
    {
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // Get total count
        $totalProducts = Product::where('user_id', $userId)
            ->where('is_active', true)
            ->count();

        if ($totalProducts === 0) {
            return 'Maaf, belum ada menu tersedia saat ini.';
        }

        $totalPages = ceil($totalProducts / $perPage);

        // Get products with category
        $products = Product::where('user_id', $userId)
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderBy('category_id', 'asc')
            ->orderBy('name', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get(['id', 'name', 'price', 'stock_quantity', 'category_id']);

        if ($products->isEmpty()) {
            if ($page > 1) {
                return 'Tidak ada menu lagi di halaman ini.';
            }

            return 'Maaf, belum ada menu tersedia saat ini.';
        }

        // Group by category
        $groupedProducts = $products->groupBy(function ($product) {
            return $product->category?->name ?? 'Lainnya';
        });

        // Format directly for user (no internal instructions)
        $lines = [];
        $lines[] = "📋 **DAFTAR MENU** (Halaman {$page}/{$totalPages})";
        $lines[] = str_repeat('─', 30);

        foreach ($groupedProducts as $categoryName => $categoryProducts) {
            $lines[] = "\n🏷️ **{$categoryName}**";
            foreach ($categoryProducts as $product) {
                $price = number_format($product->price, 0, ',', '.');
                $stock = $product->stock_quantity > 0 ? '' : ' _(Habis)_';
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
     * Get product details by ID.
     */
    protected function getProductDetails(int $userId, int $productId): string
    {
        if ($productId <= 0) {
            return 'Maaf, ID produk tidak valid.';
        }

        $product = Product::where('id', $productId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first(['id', 'name', 'price', 'stock_quantity', 'description', 'sku']);

        if (! $product) {
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
        $product = Product::where('id', $productId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first(['id', 'name', 'price', 'stock_quantity']);

        if (! $product) {
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

        return "✅ Berhasil menambahkan ke keranjang!\n\n".
               "📦 {$product->name}\n".
               "💰 {$formattedPrice} x {$quantity} = {$formattedSubtotal}\n\n".
               "Ketik 'lihat keranjang' untuk melihat ringkasan pesanan.";
    }

    /**
     * Add multiple products to cart at once.
     */
    protected function addMultipleToCart(
        AiAgentConversation $conversation,
        int $userId,
        array $products
    ): string {
        if (empty($products)) {
            return 'Maaf, tidak ada produk yang ditambahkan.';
        }

        $cart = $conversation->getCart();
        $addedProducts = [];
        $errors = [];

        foreach ($products as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? 1;

            if (! $productId || ! is_numeric($productId)) {
                continue;
            }

            $productId = (int) $productId;
            $quantity = (int) $quantity;

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $product = Product::where('id', $productId)
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->first(['id', 'name', 'price', 'stock_quantity']);

            if (! $product) {
                $errors[] = "Produk ID {$productId} tidak ditemukan";

                continue;
            }

            if ($product->stock_quantity !== null && $product->stock_quantity < $quantity) {
                $errors[] = "{$product->name}: stok tidak mencukupi";

                continue;
            }

            // Add to cart
            $found = false;
            foreach ($cart as &$cartItem) {
                if ($cartItem['product_id'] == $productId) {
                    $cartItem['quantity'] += $quantity;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $cart[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => (float) $product->price,
                    'quantity' => $quantity,
                ];
            }

            $addedProducts[] = "{$product->name} x{$quantity}";
        }

        if (! empty($addedProducts)) {
            $conversation->updateCart($cart);
        }

        if (empty($addedProducts) && ! empty($errors)) {
            return "❌ Gagal menambahkan produk:\n".implode("\n", $errors);
        }

        $response = "✅ Berhasil menambahkan ke keranjang!\n\n";
        foreach ($addedProducts as $p) {
            $response .= "📦 {$p}\n";
        }

        if (! empty($errors)) {
            $response .= "\n⚠️ ".implode(', ', $errors);
        }

        $response .= "\nKetik 'lihat keranjang' untuk melihat ringkasan pesanan.";

        return $response;
    }

    /**
     * Add items to cart by product name (search and add in one step).
     */
    protected function addItemsByName(
        AiAgentConversation $conversation,
        int $userId,
        array $items
    ): string {
        if (empty($items)) {
            return 'Maaf, tidak ada produk yang ditambahkan.';
        }

        $cart = $conversation->getCart();
        $addedProducts = [];
        $errors = [];
        $totalAdded = 0;

        foreach ($items as $item) {
            $productName = $item['product_name'] ?? null;
            $quantity = $item['quantity'] ?? 1;

            if (empty($productName)) {
                continue;
            }

            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                $quantity = 1;
            }

            // Search product by name (case-insensitive)
            $cleanName = trim(strtolower($productName));

            $product = Product::where('user_id', $userId)
                ->where('is_active', true)
                ->where(function ($q) use ($cleanName) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$cleanName}%"]);
                })
                ->first(['id', 'name', 'price', 'stock_quantity']);

            if (! $product) {
                $errors[] = "'{$productName}' tidak ditemukan";

                continue;
            }

            if ($product->stock_quantity !== null && $product->stock_quantity < $quantity) {
                $errors[] = "{$product->name}: stok tidak mencukupi";

                continue;
            }

            // Add to cart
            $found = false;
            foreach ($cart as &$cartItem) {
                if ($cartItem['product_id'] == $product->id) {
                    $cartItem['quantity'] += $quantity;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $cart[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => (float) $product->price,
                    'quantity' => $quantity,
                ];
            }

            $subtotal = $product->price * $quantity;
            $totalAdded += $subtotal;
            $addedProducts[] = [
                'name' => $product->name,
                'quantity' => $quantity,
                'price' => $product->price,
                'subtotal' => $subtotal,
            ];
        }

        if (! empty($addedProducts)) {
            $conversation->updateCart($cart);
        }

        if (empty($addedProducts) && ! empty($errors)) {
            return "❌ Gagal menambahkan produk:\n".implode("\n", $errors);
        }

        $response = "✅ Berhasil menambahkan ke keranjang!\n\n";
        foreach ($addedProducts as $p) {
            $formattedPrice = 'Rp '.number_format($p['price'], 0, ',', '.');
            $formattedSubtotal = 'Rp '.number_format($p['subtotal'], 0, ',', '.');
            $response .= "📦 {$p['name']} x{$p['quantity']}\n";
            $response .= "   {$formattedPrice} × {$p['quantity']} = {$formattedSubtotal}\n";
        }

        if (! empty($errors)) {
            $response .= "\n⚠️ ".implode(', ', $errors);
        }

        // Show total added
        $response .= "\n─────────────────\n";
        $response .= '💰 Subtotal: Rp '.number_format($totalAdded, 0, ',', '.')."\n\n";
        $response .= "Ketik 'lihat keranjang' untuk melihat ringkasan pesanan atau 'konfirmasi' untuk checkout.";

        return $response;
    }

    /**
     * Add product to cart by name (search and add).
     */
    protected function addToCartByName(
        AiAgentConversation $conversation,
        int $userId,
        string $productName,
        int $quantity
    ): string {
        if (empty(trim($productName))) {
            return 'Maaf, nama produk tidak boleh kosong.';
        }

        if ($quantity <= 0) {
            return 'Maaf, jumlah pesanan harus lebih dari 0.';
        }

        // Search product by name (case-insensitive)
        $cleanName = trim(strtolower($productName));

        $product = Product::where('user_id', $userId)
            ->where('is_active', true)
            ->where(function ($q) use ($cleanName) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$cleanName}%"]);
            })
            ->first(['id', 'name', 'price', 'stock_quantity']);

        if (! $product) {
            return "Maaf, produk '{$productName}' tidak ditemukan. Ketik 'menu' untuk melihat daftar produk.";
        }

        // Check stock availability
        if ($product->stock_quantity !== null && $product->stock_quantity < $quantity) {
            return "Maaf, stok {$product->name} tidak mencukupi. Stok tersedia: {$product->stock_quantity}";
        }

        // Get current cart
        $cart = $conversation->getCart();

        // Check if product already in cart
        $existingIndex = null;
        foreach ($cart as $index => $item) {
            if ($item['product_id'] === $product->id) {
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

        return "✅ Berhasil menambahkan ke keranjang!\n\n".
               "📦 {$product->name}\n".
               "💰 {$formattedPrice} x {$quantity} = {$formattedSubtotal}\n\n".
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

            $response .= ($index + 1).". {$item['product_name']}\n";
            $response .= '   💰 Rp '.number_format($item['price'], 0, ',', '.').
                        " x {$item['quantity']} = Rp ".
                        number_format($itemSubtotal, 0, ',', '.')."\n\n";
        }

        $taxAmount = round($subtotal * 0.11, 2);
        $ongkir = $conversation->getOngkir();
        $total = round($subtotal + $taxAmount + $ongkir, 2);

        $response .= "━━━━━━━━━━━━━━━━━━━━\n";
        $response .= 'Subtotal: Rp '.number_format($subtotal, 0, ',', '.')."\n";
        $response .= 'Pajak (11%): Rp '.number_format($taxAmount, 0, ',', '.')."\n";
        if ($ongkir > 0) {
            $response .= '🚚 Ongkir: Rp '.number_format($ongkir, 0, ',', '.')."\n";
        }
        $response .= '💰 Total: Rp '.number_format($total, 0, ',', '.')."\n\n";
        $response .= "📝 Anda masih bisa:\n";
        $response .= "• Tambah pesanan lagi\n";
        $response .= "• Ketik 'hapus [nama produk]' untuk menghapus item\n";
        $response .= "• Ketik 'batalkan pesanan' untuk mengosongkan keranjang\n";
        $response .= "• Ketik 'konfirmasi' untuk checkout";

        return $response;
    }

    /**
     * Clear all items from cart.
     */
    protected function clearCart(AiAgentConversation $conversation): string
    {
        $cart = $conversation->getCart();

        if (empty($cart)) {
            return '🛒 Keranjang sudah kosong.';
        }

        $conversation->updateCart([]);

        return "🗑️ Keranjang berhasil dikosongkan.\n\nSilakan mulai pesan lagi jika berubah pikiran! 😊";
    }

    /**
     * Remove a product from cart by name.
     */
    protected function removeFromCart(AiAgentConversation $conversation, string $productName): string
    {
        $cart = $conversation->getCart();

        if (empty($cart)) {
            return '🛒 Keranjang sudah kosong.';
        }

        $cleanName = trim(strtolower($productName));
        $removedItem = null;
        $newCart = [];

        foreach ($cart as $item) {
            if (stripos($item['product_name'], $cleanName) !== false) {
                $removedItem = $item;
            } else {
                $newCart[] = $item;
            }
        }

        if (! $removedItem) {
            return "❌ Produk '{$productName}' tidak ditemukan di keranjang.";
        }

        $conversation->updateCart($newCart);

        $response = "✅ {$removedItem['product_name']} berhasil dihapus dari keranjang.\n\n";

        if (empty($newCart)) {
            $response .= '🛒 Keranjang sekarang kosong.';
        } else {
            $response .= "Ketik 'lihat keranjang' untuk melihat sisa pesanan.";
        }

        return $response;
    }

    /**
     * Set delivery type for test endpoint.
     */
    protected function setDeliveryType(
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $deliveryType,
        ?string $address = null
    ): string {
        if (! $aiAgent->isDeliveryEnabled()) {
            return 'Maaf, fitur delivery belum diaktifkan.';
        }

        $conversation->setDeliveryType($deliveryType);

        $ongkir = 0;
        if ($deliveryType === Order::DELIVERY_TYPE_DELIVERY) {
            $ongkir = (float) ($aiAgent->default_ongkir ?? 0);
            if ($address) {
                $conversation->setDeliveryAddress($address);
            }
        }
        $conversation->setOngkir($ongkir);

        if ($deliveryType === Order::DELIVERY_TYPE_DELIVERY) {
            $formattedOngkir = 'Rp '.number_format($ongkir, 0, ',', '.');
            $response = "🚚 Delivery dipilih.\n";
            if ($address) {
                $response .= "📍 Alamat: {$address}\n";
            }
            $response .= "💰 Ongkir: {$formattedOngkir}\n";

            if (! $address) {
                $response .= "\nSilakan kirim alamat pengiriman Anda.";

                return $response;
            }

            $response .= "\nAda catatan khusus untuk pesanan? (contoh: tidak pedas, tanpa bawang)\nKetik 'tidak ada' jika tidak ada catatan.";

            return $response;
        }

        $response = "🏪 Pickup dipilih. Ongkir: Rp 0.\n\n";
        $response .= "Ada catatan khusus untuk pesanan? (contoh: tidak pedas, tanpa bawang)\nKetik 'tidak ada' jika tidak ada catatan.";

        return $response;
    }

    protected function setOrderNotes(AiAgentConversation $conversation, string $notes): string
    {
        $notes = trim($notes);

        if ($notes === '' || strtolower($notes) === 'tidak ada') {
            $conversation->setDeliveryNotes(null);

            return "✅ Tidak ada catatan khusus.\n\nKetik 'konfirmasi' untuk melanjutkan checkout.";
        }

        $conversation->setDeliveryNotes($notes);

        return "📝 Catatan tersimpan: {$notes}\n\nKetik 'konfirmasi' untuk melanjutkan checkout.";
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
        if (! $aiAgent->default_store_id) {
            Log::warning('Order creation failed - no default store', [
                'user_id' => $userId,
                'ai_agent_id' => $aiAgent->id,
            ]);

            return 'Maaf, toko default belum dikonfigurasi.';
        }

        try {
            return DB::transaction(function () use ($conversation, $aiAgent, $userId, $cart) {
                $contact = $conversation->whatsappContact;

                $deliveryType = $conversation->getDeliveryType();
                $deliveryAddress = $conversation->getDeliveryAddress();
                $deliveryNotes = $conversation->getDeliveryNotes();
                $ongkir = $conversation->getOngkir();

                Log::info('Creating order via test endpoint', [
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
                    'customer_name' => $contact->name ?? 'Test Customer',
                    'customer_phone' => $contact->wa_id ?? 'test',
                    'delivery_type' => $deliveryType ?? Order::DELIVERY_TYPE_PICKUP,
                    'alamat' => $deliveryAddress,
                    'ongkir' => $ongkir,
                    'catatan' => $deliveryNotes,
                ]);

                Log::info('Order created', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);

                // Add order items using OrderService
                foreach ($cart as $item) {
                    $product = Product::find($item['product_id']);
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

                // Clear delivery context
                $conversation->clearDeliveryContext();

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
                            Payment::create([
                                'order_id' => $order->id,
                                'qris_transaction_id' => $qrisTransaction->id,
                                'method' => Payment::METHOD_QRIS,
                                'amount' => $order->total,
                                'status' => Payment::STATUS_PENDING,
                            ]);

                            // Store QRIS transaction in conversation
                            $conversation->setCurrentQrisTransaction($qrisTransaction->id);

                            Log::info('QRIS generated successfully', [
                                'qris_transaction_id' => $qrisTransaction->id,
                                'order_id' => $qrisTransaction->order_id,
                            ]);

                            // Send order confirmation with QRIS
                            $expiryTime = $qrisTransaction->expires_at->format('H:i');
                            $formattedTotal = 'Rp '.number_format($order->total, 0, ',', '.');

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
                $formattedTotal = 'Rp '.number_format($order->total, 0, ',', '.');

                return "✅ Pesanan Berhasil Dibuat!\n\n".
                       "📋 No. Pesanan: {$order->order_number}\n".
                       "💰 Total: {$formattedTotal}\n\n".
                       "Pesanan Anda sedang diproses.\n".
                       "Silakan tunjukkan pesan ini ke kasir untuk melakukan pembayaran.\n\n".
                       'Terima kasih! 🙏';
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
            $userId = auth()->user()->getEffectiveUserId();

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

    /**
     * Clear test conversation history.
     */
    public function clearTestConversation(Request $request): JsonResponse
    {
        try {
            $userId = auth()->user()->getEffectiveUserId();

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

            // Find test contact for this user
            $testContact = WhatsAppContact::where('user_id', $userId)
                ->where('wa_id', 'test_user_'.$userId)
                ->first();

            if ($testContact) {
                // Delete conversation for test contact
                AiAgentConversation::where('ai_agent_id', $aiAgent->id)
                    ->where('whatsapp_contact_id', $testContact->id)
                    ->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Test conversation history cleared',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear test conversation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
