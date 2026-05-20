<?php

namespace App\Services;

use App\Enums\UserIntent;
use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\QrisTransaction;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;
use Sbsaga\Toon\Facades\Toon;

class AiAgentService
{
    protected WhatsAppAccountService $whatsappAccountService;

    protected OrderService $orderService;

    protected QrisService $qrisService;

    protected ConversationSummarizer $conversationSummarizer;

    protected IntentTracker $intentTracker;

    protected ConversationGuard $conversationGuard;

    protected CatalogOrderFlowService $catalogOrderFlow;

    public function __construct(
        WhatsAppAccountService $whatsappAccountService,
        OrderService $orderService,
        QrisService $qrisService,
        ConversationSummarizer $conversationSummarizer,
        IntentTracker $intentTracker,
        ConversationGuard $conversationGuard,
        CatalogOrderFlowService $catalogOrderFlow
    ) {
        $this->whatsappAccountService = $whatsappAccountService;
        $this->orderService = $orderService;
        $this->qrisService = $qrisService;
        $this->conversationSummarizer = $conversationSummarizer;
        $this->intentTracker = $intentTracker;
        $this->conversationGuard = $conversationGuard;
        $this->catalogOrderFlow = $catalogOrderFlow;
    }

    /**
     * Process incoming message and generate AI response.
     */
    public function processMessage(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        string $messageText
    ): void {
        // DEBUG: Marker to verify code version
        Log::info('AiAgentService::processMessage called [v2-buffer]', [
            'contact_wa_id' => $contact->wa_id,
            'message_preview' => substr($messageText, 0, 30),
        ]);

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

            // Catalog flow takeover: when a catalog-driven flow is in progress,
            // route text input through CatalogOrderFlowService so the LLM doesn't
            // hijack the state machine. The webhook already routes for delivery
            // info; this is defense-in-depth and also nudges the user when text
            // arrives while we're waiting on a button.
            if ($conversation->getFlowState() !== null) {
                // Universal escape hatch: typing "batal" / "cancel" / "stop"
                // at ANY point in the catalog flow resets state and frees the
                // customer. Previously the bot told them to "kirim batal" but
                // no handler was wired up — leaving them stuck.
                if (preg_match('/^\s*(batal|cancel|stop|berhenti|gajadi|gak\s+jadi|tidak\s+jadi)\.?\s*$/iu', $messageText)) {
                    Log::info('Catalog flow cancelled by customer', [
                        'ai_agent_id'    => $aiAgent->id,
                        'contact_wa_id'  => $contact->wa_id,
                        'previous_state' => $conversation->getFlowState(),
                    ]);
                    $conversation->clearFlowState();
                    $conversation->clearDeliveryContext();
                    $conversation->clearCatalogItems();
                    $conversation->clearPendingOrder();
                    $this->catalogOrderFlow->sendCancelledReply($account, $contact, $aiAgent);
                    return;
                }

                $handled = $this->catalogOrderFlow->handleDeliveryInfoText(
                    $account,
                    $contact,
                    $conversation,
                    $aiAgent,
                    $messageText
                );

                if (! $handled) {
                    $this->catalogOrderFlow->sendStuckPrompt($account, $contact);
                }

                return;
            }

            // Catalog short-circuit: when AI Agent has a catalog and the user
            // asks for the menu or wants to order, send the WhatsApp Catalog UI
            // directly instead of having the LLM write a text menu.
            if ($aiAgent->hasCatalog() && $aiAgent->isOrderEnabled()) {
                $detected = UserIntent::detect($messageText);
                if (in_array($detected, [UserIntent::VIEW_MENU, UserIntent::ORDER, UserIntent::NEXT_MENU_PAGE, UserIntent::SEARCH_PRODUCT], true)) {
                    $conversation->addMessage('human', $messageText);
                    $conversation->addMessage('ai', 'Mengirim katalog produk…');
                    $this->catalogOrderFlow->sendCatalog($account, $contact, $aiAgent);

                    return;
                }

                // Greeting short-circuit: skip LLM and reply with a quick-reply
                // button so the customer can open the catalog with one tap
                // instead of having to type "menu". Token-saving + better UX.
                if ($detected === UserIntent::GREETING) {
                    $conversation->addMessage('human', $messageText);
                    $greeting = $this->buildGreeting($aiAgent);
                    $this->catalogOrderFlow->sendGreetingWithMenuButton($account, $contact, $aiAgent, $greeting);
                    $conversation->addMessage('ai', $greeting);

                    return;
                }

                // Unknown / off-topic short-circuit: deterministic reply with a
                // Lihat Menu button so the customer never has to type "menu".
                // Saves tokens AND keeps UX consistent (every fallback has the
                // same CTA button).
                if (in_array($detected, [UserIntent::UNKNOWN, UserIntent::OFF_TOPIC], true)) {
                    $conversation->addMessage('human', $messageText);
                    $reply = $detected === UserIntent::OFF_TOPIC
                        ? 'Maaf, saya hanya melayani pemesanan. Tap *Lihat Menu* untuk mulai memesan.'
                        : 'Maaf, saya kurang paham pesan Anda. Tap *Lihat Menu* untuk melihat daftar menu, atau sebutkan nama menu yang ingin dipesan.';
                    $this->catalogOrderFlow->sendFallbackWithMenuButton($account, $contact, $aiAgent, $reply);
                    $conversation->addMessage('ai', $reply);

                    return;
                }
            }

            // Check if there's a pending order confirmation
            $pendingOrder = $conversation->getPendingOrder();
            if ($pendingOrder && $this->isConfirmation($messageText)) {
                Log::info('User confirmed order, calling confirmAndCreateOrder', [
                    'ai_agent_id' => $aiAgent->id,
                    'qris_enabled' => $aiAgent->qris_enabled,
                    'contact_wa_id' => $contact->wa_id,
                ]);

                $this->confirmAndCreateOrder($conversation, $account, $contact, $aiAgent);

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

            // Check if user wants next menu page (deterministic pagination)
            if ($aiAgent->isOrderEnabled() && $this->isNextMenuPageIntent($messageText)) {
                $this->handleNextMenuPage($conversation, $account, $contact, $aiAgent);

                return;
            }

            // Add user message to conversation
            $conversation->addMessage('human', $messageText);

            // Hard guard: when ordering is disabled, block any menu/order flow immediately
            if (! $aiAgent->isOrderEnabled() && $this->isOrderMenuIntent($messageText)) {
                Log::info('AI Agent request blocked before LLM (order disabled)', [
                    'agent_id' => $aiAgent->id,
                    'contact_id' => $contact->id,
                    'message' => $messageText,
                    'reservation_enabled' => $aiAgent->isReservationEnabled(),
                ]);

                $assistantMessage = $this->getOrderDisabledMessage($aiAgent);
                $conversation->addMessage('ai', $assistantMessage);
                $this->sendReply($account, $contact->wa_id, $assistantMessage);

                return;
            }

            $userIntent = UserIntent::detect($messageText);
            $guardedReply = $this->conversationGuard->resolve($conversation, $aiAgent, $messageText, $userIntent);
            if ($guardedReply !== null) {
                Log::info('Conversation guard handled response without LLM', [
                    'conversation_id' => $conversation->id,
                    'intent' => $userIntent->value,
                ]);

                $conversation->addMessage('ai', $guardedReply);
                $this->sendReply($account, $contact->wa_id, $guardedReply);

                return;
            }

            // Check if summarization is needed
            $shouldSummarize = $this->conversationSummarizer->shouldSummarize($conversation);

            // DISABLED: Intent change detection (too slow - adds extra LLM call)
            // Intent changes will be detected naturally during regular summarization
            // if (!$shouldSummarize && $conversation->hasSummary()) {
            //     try {
            //         $currentSummary = $conversation->getSummary();
            //         $currentIntent = $currentSummary['intent'] ?? null;
            //
            //         if ($currentIntent) {
            //             // Get the last few messages to detect intent change
            //             $recentMessages = $conversation->getRecentMessages(3);
            //
            //             // Generate a quick summary to check intent
            //             $quickSummary = $this->conversationSummarizer->generateSummary($recentMessages);
            //
            //             if ($quickSummary && isset($quickSummary['intent'])) {
            //                 $newIntent = $quickSummary['intent'];
            //
            //                 // Check if intent has changed
            //                 if ($this->intentTracker->hasIntentChanged($conversation, $newIntent)) {
            //                     Log::info('Intent change detected, triggering summarization', [
            //                         'conversation_id' => $conversation->id,
            //                         'old_intent' => $currentIntent,
            //                         'new_intent' => $newIntent,
            //                     ]);
            //
            //                     $shouldSummarize = true;
            //                 }
            //             }
            //         }
            //     } catch (\Exception $e) {
            //         Log::warning('Intent change detection failed', [
            //             'conversation_id' => $conversation->id,
            //             'error' => $e->getMessage(),
            //         ]);
            //         // Continue without intent-based summarization
            //     }
            // }

            if ($shouldSummarize) {
                try {
                    Log::info('Triggering conversation summarization', [
                        'conversation_id' => $conversation->id,
                        'message_count' => count($conversation->messages ?? []),
                    ]);

                    // Generate summary
                    $summary = $this->conversationSummarizer->generateSummary($conversation->messages ?? []);

                    if ($summary) {
                        // Store summary
                        $this->conversationSummarizer->storeSummary($conversation, $summary);

                        // Update intent tracker
                        if (isset($summary['intent'])) {
                            $this->intentTracker->updateIntent($conversation, $summary['intent']);
                        }

                        Log::info('Summary generated and stored successfully', [
                            'conversation_id' => $conversation->id,
                            'intent' => $summary['intent'] ?? 'unknown',
                        ]);
                    } else {
                        Log::warning('Summary generation returned null, continuing without summary', [
                            'conversation_id' => $conversation->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Summarization failed, continuing with full message history', [
                        'conversation_id' => $conversation->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue without summary - system will use full message history
                }
            }

            // Build system prompt with intent-based optimization
            $systemPrompt = $aiAgent->buildSystemPrompt($account->user_id, $messageText);

            // Get conversation context (summary + recent messages OR full messages)
            $messages = $this->conversationSummarizer->getContextForLLM($conversation);

            // Log context usage for monitoring
            $hasSummary = $conversation->hasSummary();
            Log::info('Using conversation context for LLM', [
                'conversation_id' => $conversation->id,
                'using_summary' => $hasSummary,
                'context_message_count' => count($messages),
                'total_message_count' => count($conversation->messages ?? []),
                'user_intent' => $userIntent->value,
            ]);

            // Conditional tool loading based on intent (saves ~500-800 tokens for simple messages)
            $tools = null;
            if ($this->intentNeedsTools($userIntent)) {
                $tools = $this->getToolDefinitionsForAgent($aiAgent);
            }

            $response = $this->callLLM($systemPrompt, $messages, $tools, $aiAgent);

            // Handle tool calls if present
            if (isset($response['tool_calls'])) {
                $this->handleToolCalls(
                    $response['tool_calls'],
                    $conversation,
                    $account,
                    $contact,
                    $aiAgent,
                    1  // Start with iteration 1
                );

                return;
            }

            // Send AI response
            $assistantMessage = $response['content'] ?? '';

            // If content is empty, generate contextual fallback
            if (empty(trim($assistantMessage))) {
                Log::warning('LLM returned empty content in main response', [
                    'conversation_id' => $conversation->id,
                    'account_id' => $account->id,
                    'raw_content' => $assistantMessage,
                    'last_user_message' => $messageText,
                ]);

                // Generate contextual fallback based on conversation
                $assistantMessage = $this->generateSimpleFallback($conversation);
            }

            $conversation->addMessage('ai', $assistantMessage);
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
     *
     * @param  string  $systemPrompt  The system prompt to use
     * @param  array  $messages  The conversation messages
     * @param  array|null  $tools  The tool definitions for function calling
     * @param  AiAgent|null  $aiAgent  The AI agent instance (for caching support)
     */
    public function callLLM(string $systemPrompt, array $messages, ?array $tools = null, ?AiAgent $aiAgent = null): array
    {
        $config = config('services.byteplus_ark');
        $url = $config['base_url'].'/chat/completions';

        // Build messages array
        $llmMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($messages as $msg) {
            // Map type (human/ai) to role (user/assistant) for LLM API
            $role = match ($msg['type'] ?? $msg['role'] ?? 'user') {
                'human' => 'user',
                'ai' => 'assistant',
                default => $msg['type'] ?? $msg['role'] ?? 'user'
            };

            $llmMessages[] = [
                'role' => $role,
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

        // Add prompt caching metadata if enabled
        if ($aiAgent && $aiAgent->enable_prompt_caching) {
            Log::info('Prompt caching enabled for AI agent', [
                'agent_id' => $aiAgent->id,
                'agent_name' => $aiAgent->bot_name,
            ]);

            // BytePlus ARK may support caching through custom parameters
            // Note: This depends on the provider's API capabilities
            $payload['metadata'] = [
                'prompt_caching_enabled' => true,
            ];
        }

        // Make API call with retry logic (3 attempts with exponential backoff)
        $attempts = 0;
        $maxAttempts = 3;
        $backoff = [10, 30, 60]; // seconds
        $startTime = microtime(true); // Track response time

        while ($attempts < $maxAttempts) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$config['api_key'],
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post($url, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

                    // Log the full response for debugging
                    Log::debug('LLM API Response', [
                        'data' => $data,
                    ]);

                    // Extract response content or tool calls
                    $choice = $data['choices'][0] ?? null;
                    if (! $choice) {
                        Log::error('Invalid LLM response format - no choices', [
                            'response_data' => $data,
                        ]);
                        throw new \Exception('Invalid LLM response format: no choices in response');
                    }

                    // Check if message exists
                    if (! isset($choice['message'])) {
                        Log::error('Invalid LLM response format - no message', [
                            'choice' => $choice,
                        ]);
                        throw new \Exception('Invalid LLM response format: no message in choice');
                    }

                    if (isset($choice['message']['tool_calls'])) {
                        Log::info('LLM returned tool calls', [
                            'tool_calls_count' => count($choice['message']['tool_calls']),
                            'tool_calls' => array_map(function ($tc) {
                                return [
                                    'id' => $tc['id'] ?? null,
                                    'function' => $tc['function']['name'] ?? 'unknown',
                                    'arguments' => $tc['function']['arguments'] ?? '{}',
                                ];
                            }, $choice['message']['tool_calls']),
                        ]);

                        // Track analytics for tool call response
                        if ($aiAgent) {
                            $promptType = $aiAgent->enable_prompt_caching ? 'cached' :
                                         ($aiAgent->use_optimized_prompt ? 'optimized' : 'full');
                            $tokensUsed = $data['usage']['total_tokens'] ?? 0;
                            $promptTokens = $data['usage']['prompt_tokens'] ?? null;
                            $completionTokens = $data['usage']['completion_tokens'] ?? null;
                            $cacheHit = $aiAgent->enable_prompt_caching;

                            AiPromptAnalytics::trackTokenUsage(
                                $aiAgent->id,
                                $tokensUsed,
                                $promptType,
                                $cacheHit,
                                $responseTimeMs,
                                $promptTokens,
                                $completionTokens
                            );
                        }

                        return [
                            'tool_calls' => $choice['message']['tool_calls'],
                        ];
                    }

                    // Get content from LLM response
                    $content = $choice['message']['content'] ?? '';

                    // Log if content is empty
                    if (empty(trim($content))) {
                        Log::warning('LLM returned empty content', [
                            'choice' => $choice,
                            'full_response' => $data,
                        ]);
                    }

                    // Track analytics for content response
                    if ($aiAgent) {
                        $promptType = $aiAgent->enable_prompt_caching ? 'cached' :
                                     ($aiAgent->use_optimized_prompt ? 'optimized' : 'full');
                        $tokensUsed = $data['usage']['total_tokens'] ?? 0;
                        $promptTokens = $data['usage']['prompt_tokens'] ?? null;
                        $completionTokens = $data['usage']['completion_tokens'] ?? null;
                        $cacheHit = $aiAgent->enable_prompt_caching;

                        AiPromptAnalytics::trackTokenUsage(
                            $aiAgent->id,
                            $tokensUsed,
                            $promptType,
                            $cacheHit,
                            $responseTimeMs,
                            $promptTokens,
                            $completionTokens
                        );
                    }

                    return [
                        'content' => $content,
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
     * MINIMAL: Only essential tools (~200 tokens)
     * Removed: search_products, search_multiple_products (merged into get_all_products)
     */
    protected function getToolDefinitions(): array
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
                    'description' => 'DIRECTLY add items to cart by product name. Auto-searches product. Use this IMMEDIATELY when user wants to order - NO need to search first!',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'items' => [
                                'type' => 'array',
                                'description' => 'Array of items to add. Can add multiple items at once.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'product_name' => ['type' => 'string', 'description' => 'Product name (partial match OK)'],
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
                    'name' => 'set_delivery_type',
                    'description' => 'Set delivery method. Ask user Pickup or Delivery first. If delivery, also ask address.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'delivery_type' => ['type' => 'string', 'description' => 'pickup or delivery'],
                            'address' => ['type' => 'string', 'description' => 'Delivery address (only for delivery)'],
                        ],
                        'required' => ['delivery_type'],
                    ],
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
     * Check if user intent requires tools to be loaded.
     * Greeting, off-topic, and business info intents don't need tools.
     * This saves ~500-800 tokens per request for simple messages.
     */
    protected function intentNeedsTools(UserIntent $intent): bool
    {
        // These intents don't need product/order tools.
        // BUSINESS_INFO is intentionally excluded from this list: "alamat" in a delivery
        // address message gets misclassified as BUSINESS_INFO, and without tools the LLM
        // can never call set_delivery_type, causing confirm_order to loop forever.
        $noToolIntents = [
            UserIntent::GREETING,
            UserIntent::OFF_TOPIC,
        ];

        return ! in_array($intent, $noToolIntents);
    }

    /**
     * Get tool definitions based on AI Agent configuration.
     */
    protected function getToolDefinitionsForAgent(AiAgent $aiAgent): ?array
    {
        if (! $aiAgent->isOrderEnabled()) {
            return null;
        }

        $tools = $this->getToolDefinitions();

        // Add QRIS tools if enabled
        if ($aiAgent->isQrisEnabled()) {
            $tools = array_merge($tools, $this->getQrisToolDefinitions());
        }

        return $tools;
    }

    /**
     * Check if user message is asking about menu/order/cart flow.
     */
    public function isOrderMenuIntent(string $messageText): bool
    {
        $text = strtolower(trim($messageText));

        $keywords = [
            'menu',
            'produk',
            'daftar',
            'pesan',
            'beli',
            'order',
            'keranjang',
            'cart',
            'checkout',
            'konfirmasi pesanan',
            'jual apa',
            'ada apa aja',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function isNextMenuPageIntent(string $messageText): bool
    {
        $text = strtolower(trim($messageText));
        $keywords = [
            'menu lainnya', 'menu selanjutnya', 'menu berikutnya',
            'menu lagi', 'lihat lagi', 'lihat selanjutnya',
            'masih ada lagi', 'masih ada yang lain',
            'ada lagi', 'ada yang lain', 'lainnya',
            'lebih banyak', 'selanjutnya', 'next menu',
            'page berikutnya', 'halaman berikutnya',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Standard response when ordering feature is disabled.
     */
    public function getOrderDisabledMessage(AiAgent $aiAgent): string
    {
        if ($aiAgent->isReservationEnabled()) {
            $reservationUrl = $aiAgent->getReservationFormUrl();
            if ($reservationUrl) {
                return "Maaf, fitur order/menu via chat sedang nonaktif. Untuk reservasi, silakan isi form: {$reservationUrl}";
            }

            return 'Maaf, fitur order/menu via chat sedang nonaktif. Namun reservasi masih tersedia.';
        }

        return 'Maaf, fitur order/menu via chat sedang nonaktif saat ini.';
    }

    /**
     * Get QRIS-specific tool definitions (optimized).
     */
    protected function getQrisToolDefinitions(): array
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

    /**
     * Handle tool calls from LLM.
     */
    protected function handleToolCalls(
        array $toolCalls,
        AiAgentConversation $conversation,
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent,
        int $iteration = 1
    ): void {
        // Prevent infinite loops - max 3 iterations
        if ($iteration > 3) {
            Log::error('Tool call iteration limit reached, stopping to prevent infinite loop', [
                'conversation_id' => $conversation->id,
                'iteration' => $iteration,
            ]);

            $errorMessage = "Maaf, saya mengalami kesulitan memproses pesanan Anda. Silakan coba lagi dengan format yang lebih sederhana.\n\n";
            $errorMessage .= "Contoh: 'pesan dimsum 2 porsi'";

            $conversation->addMessage('ai', $errorMessage);
            $this->sendReply($account, $contact->wa_id, $errorMessage);

            return;
        }

        // Log how many tool calls received
        Log::info('Handling tool calls from LLM', [
            'iteration' => $iteration,
            'tool_calls_count' => count($toolCalls),
            'tool_calls' => array_map(function ($tc) {
                return [
                    'function' => $tc['function']['name'] ?? 'unknown',
                    'arguments' => $tc['function']['arguments'] ?? '{}',
                ];
            }, $toolCalls),
        ]);

        $toolResults = [];
        $hasSearchCall = false;
        $hasFinalAction = false; // add_to_cart, confirm_order, get_cart_summary, etc.

        foreach ($toolCalls as $toolCall) {
            $functionName = $toolCall['function']['name'] ?? null;
            $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);

            // Ensure arguments is always an array
            if (! is_array($arguments)) {
                $arguments = [];
            }

            $toolCallId = $toolCall['id'] ?? uniqid();

            Log::info('Executing tool call', [
                'function' => $functionName,
                'arguments' => $arguments,
            ]);

            $result = $this->executeToolCall($functionName, $arguments, $account->user_id, $conversation, $aiAgent);

            // Track what type of calls we have
            // search_products/search_multiple_products are internal - need LLM to format for user
            // get_all_products returns user-friendly text directly
            if (in_array($functionName, ['search_products', 'search_multiple_products'])) {
                $hasSearchCall = true;
            }
            if (in_array($functionName, ['add_to_cart', 'confirm_order', 'get_cart_summary', 'generate_qris', 'check_payment_status', 'remove_from_cart', 'clear_cart', 'set_delivery_type', 'set_order_notes', 'get_all_products'])) {
                $hasFinalAction = true;
            }

            $toolResults[] = [
                'tool_call_id' => $toolCallId,
                'function_name' => $functionName,
                'result' => $result,
            ];
        }

        // If we only have search calls (no final action), we need to call LLM again
        // to let it process the search results and call add_to_cart
        // BUT: Only do this ONCE to prevent infinite loops
        if ($hasSearchCall && ! $hasFinalAction) {
            // Check if we've already done a follow-up call in this conversation turn
            $lastMessages = array_slice($conversation->messages ?? [], -5);
            $recentSearchCount = 0;
            foreach ($lastMessages as $msg) {
                if (isset($msg['type']) && $msg['type'] === 'ai' &&
                    (stripos($msg['content'] ?? '', 'HASIL PENCARIAN') !== false ||
                     stripos($msg['content'] ?? '', 'produk yang saya temukan') !== false)) {
                    $recentSearchCount++;
                }
            }

            // If we've already shown search results recently, don't loop - just show to user
            if ($recentSearchCount >= 2) {
                Log::warning('Preventing search loop - already searched multiple times recently', [
                    'conversation_id' => $conversation->id,
                    'recent_search_count' => $recentSearchCount,
                ]);

                // Send a helpful message to user
                $responseMessage = "Maaf, saya mengalami kesulitan memproses pesanan Anda. Silakan coba lagi dengan format:\n\n";
                $responseMessage .= "Contoh: 'pesan dimsum 2 porsi'\n";
                $responseMessage .= "Atau: 'pesan dimsum 1 dan teh jumbo 2'";

                $conversation->addMessage('ai', $responseMessage);
                $this->sendReply($account, $contact->wa_id, $responseMessage);

                return;
            }

            Log::info('Search completed, calling LLM again to process results and add to cart');

            // OPTIMIZED: Only use last 2 messages + tool results to minimize tokens
            // This keeps follow-up calls under 1000 tokens
            $recentMessages = array_slice($conversation->messages ?? [], -2);
            $messages = $recentMessages;

            // Add assistant message with tool calls
            $messages[] = [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => $toolCalls,
            ];

            // Add tool results (COMPACT: truncate large results)
            foreach ($toolResults as $tr) {
                $resultContent = $tr['result'];
                // Truncate very large tool results to save tokens
                if (strlen($resultContent) > 1500) {
                    $resultContent = substr($resultContent, 0, 1500)."\n[TRUNCATED]";
                }
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tr['tool_call_id'],
                    'content' => $resultContent,
                ];
            }

            // Call LLM again to continue processing
            $systemPrompt = $aiAgent->buildSystemPrompt($account->user_id);
            $tools = $this->getToolDefinitionsForAgent($aiAgent);

            try {
                $response = $this->callLLMWithToolResults($systemPrompt, $messages, $tools);

                // If LLM returns more tool calls, handle them (with iteration counter)
                if (isset($response['tool_calls'])) {
                    $this->handleToolCalls(
                        $response['tool_calls'],
                        $conversation,
                        $account,
                        $contact,
                        $aiAgent,
                        $iteration + 1
                    );

                    return;
                }

                // Otherwise send the response
                $assistantMessage = $response['content'] ?? '';

                // Check if content is empty or only whitespace
                if (empty(trim($assistantMessage))) {
                    // LLM returned empty content after processing search results
                    Log::warning('LLM returned empty content after search', [
                        'conversation_id' => $conversation->id,
                        'tool_results' => array_map(fn ($tr) => $tr['function_name'], $toolResults),
                        'raw_content' => $assistantMessage,
                    ]);

                    // Generate contextual fallback based on search results
                    $assistantMessage = $this->generateContextualFallback($toolResults, $conversation);
                }

                $conversation->addMessage('ai', $assistantMessage);
                $this->sendReply($account, $contact->wa_id, $assistantMessage);

                return;
            } catch (\Exception $e) {
                Log::error('Error in follow-up LLM call', ['error' => $e->getMessage()]);
                // Fall through to send search results if follow-up fails
            }
        }

        // For final actions or if follow-up failed, send results to user
        $userFacingResults = [];
        foreach ($toolResults as $tr) {
            // Don't show raw search results to user - they're internal and need LLM formatting
            // get_all_products now returns user-friendly text directly
            if (! in_array($tr['function_name'], ['search_products', 'search_multiple_products'])) {
                $result = $tr['result'];
                // Strip EMPTY sentinel prefix from getAllProducts empty-page responses
                if (str_starts_with($result, 'EMPTY\n')) {
                    $result = substr($result, 6);
                }
                $userFacingResults[] = $result;
            }
        }

        // Send combined result to user
        $responseMessage = implode("\n\n", array_filter($userFacingResults));

        // Check if response is empty or only whitespace
        if (empty(trim($responseMessage))) {
            // No user-facing results to send (e.g., only search was performed)
            Log::warning('No user-facing results from tool calls', [
                'conversation_id' => $conversation->id,
                'tool_calls' => array_map(fn ($tr) => $tr['function_name'], $toolResults),
            ]);

            // Generate contextual fallback based on tool results
            $responseMessage = $this->generateContextualFallback($toolResults, $conversation);
        }

        $conversation->addMessage('ai', $responseMessage);
        $this->sendReply($account, $contact->wa_id, $responseMessage);
    }

    /**
     * Call LLM with tool results to continue the conversation.
     */
    protected function callLLMWithToolResults(string $systemPrompt, array $messages, ?array $tools = null): array
    {
        $config = config('services.byteplus_ark');
        $url = $config['base_url'].'/chat/completions';

        // Build messages array with proper format
        $llmMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($messages as $msg) {
            if (isset($msg['role']) && $msg['role'] === 'tool') {
                // Tool result message
                $llmMessages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $msg['tool_call_id'],
                    'content' => $msg['content'],
                ];
            } elseif (isset($msg['tool_calls'])) {
                // Assistant message with tool calls
                $llmMessages[] = [
                    'role' => 'assistant',
                    'content' => $msg['content'],
                    'tool_calls' => $msg['tool_calls'],
                ];
            } else {
                // Regular message
                $role = match ($msg['type'] ?? $msg['role'] ?? 'user') {
                    'human' => 'user',
                    'ai' => 'assistant',
                    default => $msg['type'] ?? $msg['role'] ?? 'user'
                };

                $llmMessages[] = [
                    'role' => $role,
                    'content' => $msg['content'],
                ];
            }
        }

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

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$config['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url, $payload);

        if ($response->successful()) {
            $data = $response->json();
            $choice = $data['choices'][0] ?? null;

            if ($choice && isset($choice['message']['tool_calls'])) {
                return ['tool_calls' => $choice['message']['tool_calls']];
            }

            return ['content' => $choice['message']['content'] ?? ''];
        }

        throw new \Exception('LLM API call failed: '.$response->body());
    }

    /**
     * Generate contextual fallback message based on tool results and conversation context.
     */
    protected function generateContextualFallback(array $toolResults, AiAgentConversation $conversation): string
    {
        // Analyze tool results to understand what was attempted
        $toolNames = array_map(fn ($tr) => $tr['function_name'], $toolResults);
        $hasSearch = ! empty(array_intersect($toolNames, ['search_products', 'search_multiple_products']));

        // Get last user message for context
        $messages = $conversation->messages ?? [];
        $lastUserMessage = '';
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (isset($messages[$i]['type']) && $messages[$i]['type'] === 'human') {
                $lastUserMessage = strtolower($messages[$i]['content'] ?? '');
                break;
            }
        }

        // Parse search results to extract product information
        $foundProducts = [];
        foreach ($toolResults as $tr) {
            if (in_array($tr['function_name'], ['search_products', 'search_multiple_products'])) {
                $result = $tr['result'];
                // Extract product names from result string
                if (preg_match_all('/\d+\.\s*([^\n]+?)\s*-\s*Rp/', $result, $matches)) {
                    $foundProducts = array_merge($foundProducts, $matches[1]);
                }
            }
        }

        // Generate contextual response based on user intent and search results
        if ($hasSearch && ! empty($foundProducts)) {
            // User was searching/ordering and we found products
            $productList = implode(', ', array_slice($foundProducts, 0, 3));

            if (stripos($lastUserMessage, 'pesan') !== false ||
                stripos($lastUserMessage, 'beli') !== false ||
                stripos($lastUserMessage, 'order') !== false) {
                // User wants to order
                return "Saya menemukan produk yang Anda cari: {$productList}. ".
                       "Berapa jumlah yang ingin Anda pesan? Contoh: 'pesan {$foundProducts[0]} 2 porsi'";
            } elseif (stripos($lastUserMessage, 'menu') !== false ||
                      stripos($lastUserMessage, 'ada apa') !== false ||
                      stripos($lastUserMessage, 'daftar') !== false) {
                // User wants to browse menu
                return "Kami punya: {$productList}".(count($foundProducts) > 3 ? ' dan lainnya' : '').'. '.
                       'Mau pesan yang mana?';
            } else {
                // General search
                return "Saya menemukan: {$productList}. Ada yang ingin Anda pesan?";
            }
        } elseif ($hasSearch && empty($foundProducts)) {
            // Search was performed but no products found
            if (stripos($lastUserMessage, 'pesan') !== false ||
                stripos($lastUserMessage, 'beli') !== false) {
                return "Maaf, produk yang Anda cari tidak tersedia. Ketik 'menu' untuk melihat daftar produk kami.";
            } else {
                return "Maaf, tidak ada produk yang sesuai dengan pencarian Anda. Bisa coba kata kunci lain atau ketik 'menu' untuk lihat semua produk.";
            }
        }

        // Check cart context
        $cart = $conversation->getCart();
        if (! empty($cart)) {
            if (stripos($lastUserMessage, 'keranjang') !== false ||
                stripos($lastUserMessage, 'cart') !== false ||
                stripos($lastUserMessage, 'pesanan') !== false) {
                return 'Anda punya '.count($cart)." item di keranjang. Ketik 'lihat keranjang' untuk detail atau 'konfirmasi' untuk checkout.";
            }
        }

        // Check if user is asking about payment
        if (stripos($lastUserMessage, 'bayar') !== false ||
            stripos($lastUserMessage, 'qris') !== false ||
            stripos($lastUserMessage, 'payment') !== false) {
            $currentOrder = $conversation->getCurrentOrder();
            if ($currentOrder) {
                return 'Pesanan Anda sudah dibuat. Silakan lakukan pembayaran untuk melanjutkan.';
            } else {
                return 'Belum ada pesanan yang perlu dibayar. Silakan buat pesanan terlebih dahulu.';
            }
        }

        // Check if user is asking general questions
        if (stripos($lastUserMessage, 'jam') !== false ||
            stripos($lastUserMessage, 'buka') !== false ||
            stripos($lastUserMessage, 'tutup') !== false) {
            return 'Untuk informasi jam operasional, silakan hubungi kami langsung. Ada yang bisa saya bantu untuk pemesanan?';
        }

        if (stripos($lastUserMessage, 'lokasi') !== false ||
            stripos($lastUserMessage, 'alamat') !== false ||
            stripos($lastUserMessage, 'dimana') !== false) {
            return 'Untuk informasi lokasi, silakan hubungi kami langsung. Mau pesan sesuatu?';
        }

        // Generic fallback based on conversation state
        if (! empty($cart)) {
            return 'Anda punya pesanan di keranjang. Mau tambah item lagi atau langsung checkout?';
        }

        // Default fallback - encourage user to be more specific
        return 'Maaf, saya kurang mengerti maksud Anda. Bisa dijelaskan lebih detail? '.
               "Contoh: 'lihat menu', 'pesan nasi goreng 2', atau 'lihat keranjang'.";
    }

    /**
     * Generate simple contextual fallback for main response (without tool results).
     */
    protected function generateSimpleFallback(AiAgentConversation $conversation): string
    {
        // Get last user message for context
        $messages = $conversation->messages ?? [];
        $lastUserMessage = '';
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (isset($messages[$i]['type']) && $messages[$i]['type'] === 'human') {
                $lastUserMessage = strtolower($messages[$i]['content'] ?? '');
                break;
            }
        }

        // Check conversation state
        $cart = $conversation->getCart();
        $currentOrder = $conversation->getCurrentOrder();

        // Greeting detection
        if (preg_match('/^(halo|hai|hi|hello|hey|assalamualaikum|selamat)/i', $lastUserMessage)) {
            return "Halo! Ada yang bisa saya bantu? Ketik 'menu' untuk lihat produk kami.";
        }

        // Menu/product inquiry
        if (stripos($lastUserMessage, 'menu') !== false ||
            stripos($lastUserMessage, 'produk') !== false ||
            stripos($lastUserMessage, 'ada apa') !== false ||
            stripos($lastUserMessage, 'jual apa') !== false) {
            return "Untuk melihat menu lengkap, ketik 'lihat menu' atau 'daftar produk'. Atau sebutkan produk yang Anda cari.";
        }

        // Order intent
        if (stripos($lastUserMessage, 'pesan') !== false ||
            stripos($lastUserMessage, 'beli') !== false ||
            stripos($lastUserMessage, 'order') !== false ||
            stripos($lastUserMessage, 'mau') !== false) {
            if (! empty($cart)) {
                return 'Anda sudah punya '.count($cart)." item di keranjang. Mau tambah lagi atau langsung checkout? Ketik 'lihat keranjang' untuk detail.";
            } else {
                return "Silakan sebutkan produk yang ingin Anda pesan. Contoh: 'pesan nasi goreng 2 porsi' atau ketik 'menu' untuk lihat daftar produk.";
            }
        }

        // Cart inquiry
        if (stripos($lastUserMessage, 'keranjang') !== false ||
            stripos($lastUserMessage, 'cart') !== false ||
            stripos($lastUserMessage, 'pesanan') !== false) {
            if (! empty($cart)) {
                return "Ketik 'lihat keranjang' untuk melihat detail pesanan Anda.";
            } else {
                return "Keranjang Anda masih kosong. Silakan pesan produk terlebih dahulu. Ketik 'menu' untuk lihat produk.";
            }
        }

        // Payment inquiry
        if (stripos($lastUserMessage, 'bayar') !== false ||
            stripos($lastUserMessage, 'qris') !== false ||
            stripos($lastUserMessage, 'payment') !== false ||
            stripos($lastUserMessage, 'transfer') !== false) {
            if ($currentOrder) {
                return 'Untuk melakukan pembayaran, silakan konfirmasi pesanan Anda terlebih dahulu.';
            } else {
                return 'Belum ada pesanan yang perlu dibayar. Silakan buat pesanan terlebih dahulu.';
            }
        }

        // Help/info inquiry
        if (stripos($lastUserMessage, 'bantuan') !== false ||
            stripos($lastUserMessage, 'help') !== false ||
            stripos($lastUserMessage, 'cara') !== false) {
            return "Saya bisa bantu Anda:\n".
                   "• Lihat menu: ketik 'menu' atau 'daftar produk'\n".
                   "• Pesan: ketik 'pesan [nama produk] [jumlah]'\n".
                   "• Lihat keranjang: ketik 'lihat keranjang'\n".
                   "• Checkout: ketik 'konfirmasi pesanan'\n\n".
                   'Ada yang bisa saya bantu?';
        }

        // Thank you
        if (preg_match('/(terima kasih|thanks|thank you|makasih)/i', $lastUserMessage)) {
            return 'Sama-sama! Ada lagi yang bisa saya bantu?';
        }

        // Cancel/stop
        if (preg_match('/(batal|cancel|stop|tidak jadi)/i', $lastUserMessage)) {
            if (! empty($cart)) {
                return "Mau batalkan pesanan? Ketik 'hapus keranjang' untuk mengosongkan keranjang.";
            } else {
                return 'Baik, tidak jadi. Ada yang bisa saya bantu lagi?';
            }
        }

        // Default - based on conversation state
        if (! empty($cart)) {
            return 'Anda punya '.count($cart)." item di keranjang. Ketik 'lihat keranjang' untuk detail atau 'konfirmasi' untuk checkout. Atau mau pesan yang lain?";
        }

        // Ultimate fallback
        return "Maaf, saya kurang mengerti. Bisa dijelaskan lebih detail atau coba:\n".
               "• Ketik 'menu' untuk lihat produk\n".
               "• Ketik 'pesan [produk] [jumlah]' untuk memesan\n".
               "• Ketik 'bantuan' untuk info lebih lanjut";
    }

    /**
     * Execute a tool call.
     */
    protected function executeToolCall(
        string $functionName,
        array $arguments,
        int $userId,
        AiAgentConversation $conversation,
        ?AiAgent $aiAgent = null
    ): string {
        // Check if TOON format should be used for tool results
        $useToon = $aiAgent?->use_toon_format ?? false;

        try {
            // Validate parameters based on function requirements
            switch ($functionName) {
                case 'get_all_products':
                    $page = isset($arguments['page']) ? (int) $arguments['page'] : 1;
                    $search = $arguments['search'] ?? null;

                    $result = $this->getAllProducts($userId, $useToon, $page, $search);

                    if ($conversation && ! str_starts_with($result, 'EMPTY')) {
                        $conversation->setCurrentMenuPage($page);
                    }

                    return $result;

                case 'search_products':
                    if (! isset($arguments['query'])) {
                        return 'Maaf, parameter pencarian tidak lengkap. Mohon berikan kata kunci pencarian.';
                    }

                    return $this->searchProducts($userId, $arguments['query'], $useToon);

                case 'search_multiple_products':
                    if (! isset($arguments['queries']) || ! is_array($arguments['queries'])) {
                        return 'Maaf, parameter pencarian tidak lengkap. Mohon berikan array kata kunci pencarian.';
                    }

                    return $this->searchMultipleProducts($userId, $arguments['queries'], $useToon);

                case 'get_product_details':
                    if (! isset($arguments['product_id'])) {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan ID produk.';
                    }
                    if (! is_numeric($arguments['product_id'])) {
                        return 'Maaf, ID produk harus berupa angka.';
                    }

                    return $this->getProductDetails($userId, (int) $arguments['product_id']);

                case 'add_to_cart':
                    // Support items array with product_name (preferred - no ID needed)
                    if (isset($arguments['items']) && is_array($arguments['items'])) {
                        return $this->addItemsByName($conversation, $userId, $arguments['items']);
                    }
                    // Support products array with product_id (legacy)
                    elseif (isset($arguments['products']) && is_array($arguments['products'])) {
                        return $this->addMultipleToCart($conversation, $userId, $arguments['products']);
                    }
                    // Support single product_name
                    elseif (isset($arguments['product_name'])) {
                        $quantity = isset($arguments['quantity']) ? (int) $arguments['quantity'] : 1;

                        return $this->addItemsByName($conversation, $userId, [
                            ['product_name' => $arguments['product_name'], 'quantity' => $quantity],
                        ]);
                    }
                    // Support single product_id (legacy backward compatibility)
                    elseif (isset($arguments['product_id']) && isset($arguments['quantity'])) {
                        if (! is_numeric($arguments['product_id'])) {
                            return 'Maaf, ID produk harus berupa angka.';
                        }
                        if (! is_numeric($arguments['quantity'])) {
                            return 'Maaf, jumlah pesanan harus berupa angka.';
                        }

                        return $this->addToCart(
                            $conversation,
                            $userId,
                            (int) $arguments['product_id'],
                            (int) $arguments['quantity']
                        );
                    } else {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan items dengan product_name dan quantity.';
                    }

                case 'get_cart_summary':
                    return $this->getCartSummary($conversation, $userId, $aiAgent);

                case 'confirm_order':
                    return $this->prepareOrderConfirmation($conversation, $userId, $aiAgent);

                case 'set_delivery_type':
                    return $this->setDeliveryType($conversation, $arguments, $aiAgent);

                case 'set_order_notes':
                    return $this->setOrderNotes($conversation, $arguments);

                case 'remove_from_cart':
                    if (! isset($arguments['product_name'])) {
                        return 'Maaf, mohon sebutkan nama produk yang ingin dihapus dari keranjang.';
                    }

                    return $this->removeFromCart($conversation, $arguments['product_name']);

                case 'clear_cart':
                    return $this->clearCart($conversation);

                case 'generate_qris':
                    if (! isset($arguments['amount'])) {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan jumlah pembayaran.';
                    }
                    if (! is_numeric($arguments['amount'])) {
                        return 'Maaf, jumlah pembayaran harus berupa angka.';
                    }

                    return $this->generateQrisForOrder(
                        $conversation,
                        $userId,
                        (float) $arguments['amount'],
                        $arguments['description'] ?? null
                    );

                case 'check_payment_status':
                    return $this->checkPaymentStatus($conversation);

                default:
                    return "Fungsi '{$functionName}' tidak dikenali.";
            }
        } catch (\Exception $e) {
            Log::error("Tool call error: {$functionName}", [
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
     * Search products by query.
     */
    protected function searchProducts(int $userId, string $query, bool $useToon = false): string
    {
        // Validate query parameter
        if (empty(trim($query))) {
            return 'Mohon berikan kata kunci pencarian produk.';
        }

        // Clean and normalize query for better matching (lowercase for case-insensitive search)
        $cleanQuery = trim(strtolower($query));

        // Split query into words for flexible matching
        $keywords = explode(' ', $cleanQuery);

        $products = Product::where('user_id', $userId)
            ->where('is_active', true)
            ->with('category:id,name')
            ->where(function ($q) use ($cleanQuery, $keywords) {
                // Case-insensitive search using LOWER()
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$cleanQuery}%"])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$cleanQuery}%"]);

                // Also match if ANY keyword is present (more flexible)
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) >= 2) { // Use keywords with 2+ chars
                        $q->orWhereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"]);
                    }
                }
            })
            ->orderBy('category_id', 'asc')
            ->orderBy('name', 'asc')
            ->limit(10)
            ->get(['id', 'name', 'price', 'stock_quantity', 'description', 'category_id']);

        if ($products->isEmpty()) {
            // CRITICAL: Return clear message that product was NOT FOUND
            return "NOT_FOUND\nquery:{$query}\naction:katakan tidak tersedia, jangan sebutkan produk ini";
        }

        // Group by category for better display
        $groupedProducts = $products->groupBy(function ($product) {
            return $product->category?->name ?? 'Lainnya';
        });

        // Use TOON format if enabled (saves ~67% tokens with sbsaga/toon)
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

        // ULTRA-COMPACT format to minimize tokens
        $items = [];
        foreach ($groupedProducts as $categoryProducts) {
            foreach ($categoryProducts as $product) {
                $items[] = "{$product->name}[{$product->id}]Rp".number_format($product->price, 0, ',', '.');
            }
        }

        return "FOUND:{$query}|".implode('|', $items).'|ACT:add_to_cart(id,qty)';
    }

    /**
     * Search multiple products by multiple queries at once.
     * This is useful when user orders multiple products in one message.
     */
    protected function searchMultipleProducts(int $userId, array $queries, bool $useToon = false): string
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
            } else {
                foreach ($products as $product) {
                    // Avoid duplicates
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
        }

        // CRITICAL: Handle case when NO products found at all
        if (empty($allResults) && ! empty($notFound)) {
            return "NOT_FOUND\nqueries:".implode(',', $notFound)."\naction:katakan tidak tersedia";
        }

        // Group results by category
        $groupedResults = collect($allResults)->groupBy('category');

        // Use TOON format if enabled (saves ~67% tokens with sbsaga/toon)
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

        // ULTRA-COMPACT format to minimize tokens
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

    protected function handleNextMenuPage(
        AiAgentConversation $conversation,
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgent $aiAgent
    ): void {
        $currentPage = $conversation->getCurrentMenuPage();
        $nextPage = $currentPage + 1;
        $useToon = $aiAgent->use_toon_format ?? false;

        $result = $this->getAllProducts($account->user_id, false, $nextPage);

        if (str_starts_with($result, 'EMPTY')) {
            $msg = 'Sudah tidak ada menu lagi. Itu semua menu yang tersedia! 😊 Mau pesan yang mana?';
            $conversation->addMessage('human', 'menu lainnya');
            $conversation->addMessage('ai', $msg);
            $this->sendReply($account, $contact->wa_id, $msg);

            return;
        }

        $conversation->setCurrentMenuPage($nextPage);
        $conversation->addMessage('human', 'menu lainnya');
        $conversation->addMessage('ai', $result);
        $this->sendReply($account, $contact->wa_id, $result);
    }

    protected function formatMenuFallback(string $rawResult, int $page): string
    {
        $lines = explode("\n", $rawResult);
        $menuItems = [];
        foreach ($lines as $line) {
            if (str_contains($line, 'Rp')) {
                $menuItems[] = $line;
            }
        }

        $response = "📋 Menu (Halaman {$page}):\n\n";
        $response .= implode("\n", $menuItems);
        $response .= "\n\nMau pesan yang mana? Atau ketik 'menu lainnya' untuk lihat lebih banyak.";

        return $response;
    }

    /**
     * Get all active products with pagination (10 per page), grouped by category.
     */
    protected function getAllProducts(int $userId, bool $useToon = false, int $page = 1, ?string $search = null): string
    {
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        // Build base query
        $query = Product::where('user_id', $userId)->where('is_active', true);

        // Add search filter if provided
        if ($search) {
            $cleanSearch = trim(strtolower($search));
            $query->where(function ($q) use ($cleanSearch) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$cleanSearch}%"]);
            });
        }

        // Get total count for pagination info
        $totalProducts = $query->count();
        $totalPages = max(1, ceil($totalProducts / $perPage));

        // Get products with category relation
        $products = $query->with('category:id,name')
            ->orderBy('category_id', 'asc')
            ->orderBy('name', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get(['id', 'name', 'price', 'stock_quantity', 'category_id']);

        if ($products->isEmpty()) {
            if ($page > 1) {
                return "EMPTY\nTidak ada menu lagi di halaman ini.";
            }

            return "EMPTY\nMaaf, belum ada menu tersedia saat ini.";
        }

        // Group products by category
        $groupedProducts = $products->groupBy(function ($product) {
            return $product->category?->name ?? 'Lainnya';
        });

        // Format directly for user display (consistent with test endpoint)
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
     * Get product details.
     */
    protected function getProductDetails(int $userId, int $productId): string
    {
        // Validate product_id parameter
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
     * Add product to cart.
     */
    protected function addToCart(
        AiAgentConversation $conversation,
        int $userId,
        int $productId,
        int $quantity
    ): string {
        // Validate product_id parameter
        if ($productId <= 0) {
            return 'Maaf, ID produk tidak valid. Mohon berikan ID produk yang benar.';
        }

        // Validate quantity parameter
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
            $quantity = $item['quantity'] ?? null;

            // Validate
            if (! $productId || ! $quantity) {
                $errors[] = 'Produk dengan data tidak lengkap dilewati';

                continue;
            }

            if (! is_numeric($productId) || ! is_numeric($quantity)) {
                $errors[] = "Produk ID {$productId}: format tidak valid";

                continue;
            }

            $productId = (int) $productId;
            $quantity = (int) $quantity;

            if ($productId <= 0 || $quantity <= 0) {
                $errors[] = "Produk ID {$productId}: nilai tidak valid";

                continue;
            }

            // Get product
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
                    'product_id' => $productId,
                    'product_name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $quantity,
                ];
            }

            $addedProducts[] = [
                'name' => $product->name,
                'quantity' => $quantity,
            ];
        }

        // Save cart
        if (! empty($addedProducts)) {
            $conversation->updateCart($cart);
        }

        // Build response
        if (empty($addedProducts) && ! empty($errors)) {
            return "❌ Gagal menambahkan produk:\n".implode("\n", $errors);
        }

        $response = "✅ Berhasil menambahkan ke keranjang!\n\n";
        foreach ($addedProducts as $item) {
            $response .= "📦 {$item['name']} x{$item['quantity']}\n";
        }

        if (! empty($errors)) {
            $response .= "\n⚠️ Beberapa produk tidak dapat ditambahkan:\n".implode("\n", $errors);
        }

        $response .= "\nKetik 'lihat keranjang' untuk melihat ringkasan pesanan.";

        return $response;
    }

    /**
     * Add items to cart by product name (search and add in one step).
     * This is the preferred method as it doesn't require product_id.
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
     * Get cart summary.
     */
    protected function getCartSummary(AiAgentConversation $conversation, int $userId, ?AiAgent $aiAgent = null): string
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

        $ongkir = 0;
        if ($aiAgent && $aiAgent->isDeliveryEnabled()) {
            $deliveryType = $conversation->getDeliveryType();
            if ($deliveryType === 'delivery') {
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
            // Match by partial name (case-insensitive)
            if (stripos($item['product_name'], $cleanName) !== false) {
                $removedItem = $item;
            } else {
                $newCart[] = $item;
            }
        }

        if (! $removedItem) {
            return "❌ Produk '{$productName}' tidak ditemukan di keranjang.\n\nKetik 'lihat keranjang' untuk melihat isi keranjang Anda.";
        }

        $conversation->updateCart($newCart);

        $response = "✅ {$removedItem['product_name']} berhasil dihapus dari keranjang.\n\n";

        if (empty($newCart)) {
            $response .= '🛒 Keranjang sekarang kosong.';
        } else {
            // Show remaining cart summary
            $total = 0;
            $response .= "📦 Sisa pesanan:\n";
            foreach ($newCart as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $total += $subtotal;
                $response .= "• {$item['product_name']} x{$item['quantity']} - Rp ".number_format($subtotal, 0, ',', '.')."\n";
            }
            $tax = round($total * 0.11, 2);
            $grandTotal = round($total + $tax, 2);
            $response .= "\nTotal: Rp ".number_format($grandTotal, 0, ',', '.')."\n";
            $response .= "\nKetik 'konfirmasi' untuk checkout atau tambah pesanan lagi.";
        }

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

    protected function setDeliveryType(AiAgentConversation $conversation, array $arguments, ?AiAgent $aiAgent): string
    {
        $deliveryType = $arguments['delivery_type'] ?? null;
        if (! in_array($deliveryType, ['pickup', 'delivery'])) {
            return 'Maaf, pilihan tidak valid. Pilih Pickup atau Delivery.';
        }

        $conversation->setDeliveryType($deliveryType);

        $ongkir = 0;
        if ($deliveryType === 'delivery') {
            $ongkir = $aiAgent ? (float) $aiAgent->default_ongkir : 0;
            $address = $arguments['address'] ?? null;
            if ($address) {
                $conversation->setDeliveryAddress($address);
            }
        }
        $conversation->setOngkir($ongkir);

        if ($deliveryType === 'delivery') {
            $address = $arguments['address'] ?? null;
            $formattedOngkir = 'Rp '.number_format($ongkir, 0, ',', '.');

            $response = "✅ Delivery dipilih.\n";
            if ($address) {
                $response .= "📍 Alamat: {$address}\n";
            }
            $response .= "🚚 Ongkir: {$formattedOngkir}\n";

            if (! $address) {
                $response .= "\nSilakan kirim alamat pengiriman Anda.";

                return $response;
            }

            $response .= "\nAda catatan khusus untuk pesanan? (contoh: tidak pedas, tanpa bawang)\nKetik 'tidak ada' jika tidak ada catatan.";

            return $response;
        }

        $response = "✅ Pickup dipilih. Ongkir: Rp 0.\n\n";
        $response .= "Ada catatan khusus untuk pesanan? (contoh: tidak pedas, tanpa bawang)\nKetik 'tidak ada' jika tidak ada catatan.";

        return $response;
    }

    protected function setOrderNotes(AiAgentConversation $conversation, array $arguments): string
    {
        $notes = trim($arguments['notes'] ?? '');

        if ($notes === '' || strtolower($notes) === 'tidak ada') {
            $conversation->setDeliveryNotes(null);

            return "✅ Tidak ada catatan khusus.\n\nKetik 'konfirmasi' untuk melanjutkan checkout.";
        }

        $conversation->setDeliveryNotes($notes);

        return "📝 Catatan tersimpan: {$notes}\n\nKetik 'konfirmasi' untuk melanjutkan checkout.";
    }

    /**
     * Confirm and immediately create the order (mirrors AiAgentController::confirmOrder).
     */
    protected function prepareOrderConfirmation(AiAgentConversation $conversation, int $userId, ?AiAgent $aiAgent = null): string
    {
        $cart = $conversation->getCart();

        if (empty($cart)) {
            return '🛒 Keranjang belanja Anda masih kosong. Silakan tambahkan produk terlebih dahulu.';
        }

        if (! $aiAgent || ! $aiAgent->default_store_id) {
            return 'Maaf, toko default belum dikonfigurasi.';
        }

        if ($aiAgent->isDeliveryEnabled()) {
            $deliveryType = $conversation->getDeliveryType();
            if (! $deliveryType) {
                return "Sebelum checkout, pilih metode pengiriman:\n• Ketik 'pickup' untuk ambil di tempat\n• Ketik 'delivery' untuk diantar";
            }
        }

        try {
            return DB::transaction(function () use ($conversation, $aiAgent, $userId, $cart) {
                $contact = $conversation->whatsappContact()->withoutGlobalScopes()->first();

                $deliveryType = $conversation->getDeliveryType();
                $deliveryAddress = $conversation->getDeliveryAddress();
                $deliveryNotes = $conversation->getDeliveryNotes();
                $ongkir = $conversation->getOngkir();

                Log::info('Creating order via WhatsApp AI confirm_order tool', [
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
                    'customer_name' => $contact->name ?? 'WhatsApp Customer',
                    'customer_phone' => $contact->wa_id,
                    'delivery_type' => $deliveryType ?? Order::DELIVERY_TYPE_PICKUP,
                    'alamat' => $deliveryAddress,
                    'ongkir' => $ongkir,
                    'catatan' => $deliveryNotes,
                ]);

                foreach ($cart as $item) {
                    $product = Product::find($item['product_id']);
                    if ($product) {
                        $this->orderService->addItem($order, $product, $item['quantity']);
                    }
                }

                $order->refresh();

                $conversation->setCurrentOrder($order->id);
                $conversation->clearCart();
                $conversation->clearPendingOrder();
                $conversation->clearDeliveryContext();

                Log::info('Order created via WhatsApp AI', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                ]);

                // Auto-generate QRIS if enabled
                if ($aiAgent->isQrisEnabled()) {
                    $subMerchant = $aiAgent->getSubMerchant();

                    if ($subMerchant) {
                        try {
                            $qrisTransaction = $this->qrisService->generateQris($subMerchant, (float) $order->total, [
                                'description' => "Pesanan #{$order->order_number}",
                            ]);

                            $qrisTransaction->linked_order_id = $order->id;
                            $qrisTransaction->save();

                            Payment::create([
                                'order_id' => $order->id,
                                'qris_transaction_id' => $qrisTransaction->id,
                                'method' => Payment::METHOD_QRIS,
                                'amount' => $order->total,
                                'status' => Payment::STATUS_PENDING,
                            ]);

                            $conversation->setCurrentQrisTransaction($qrisTransaction->id);

                            $expiryTime = $qrisTransaction->expires_at->format('H:i');
                            $formattedTotal = 'Rp '.number_format($order->total, 0, ',', '.');
                            $shareableLink = $qrisTransaction->getShareableLink();

                            $response = "✅ Pesanan Berhasil Dibuat!\n\n";
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
                            Log::error('QRIS generation failed during WhatsApp order confirmation', [
                                'error' => $e->getMessage(),
                                'order_id' => $order->id,
                            ]);
                            // Fall through to send order without QRIS
                        }
                    }
                }

                $formattedTotal = 'Rp '.number_format($order->total, 0, ',', '.');

                return "✅ Pesanan Berhasil Dibuat!\n\n".
                       "📋 No. Pesanan: {$order->order_number}\n".
                       "💰 Total: {$formattedTotal}\n\n".
                       "Pesanan Anda sedang diproses.\n".
                       "Silakan tunjukkan pesan ini ke kasir untuk melakukan pembayaran.\n\n".
                       'Terima kasih! 🙏';
            });

        } catch (\Exception $e) {
            Log::error('Order creation failed via WhatsApp AI confirm_order', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 'Maaf, terjadi kesalahan saat membuat pesanan. Silakan coba lagi.';
        }
    }

    /**
     * Generate QRIS for an order.
     */
    protected function generateQrisForOrder(
        AiAgentConversation $conversation,
        int $userId,
        float $amount,
        ?string $description = null
    ): string {
        try {
            $aiAgent = $conversation->aiAgent;

            // Validate QRIS is enabled
            if (! $aiAgent->isQrisEnabled()) {
                $errors = $aiAgent->validateQrisConfiguration();
                if (! empty($errors)) {
                    Log::warning('QRIS not properly configured', [
                        'ai_agent_id' => $aiAgent->id,
                        'errors' => $errors,
                    ]);
                }

                return 'Maaf, pembayaran QRIS belum tersedia saat ini. Silakan hubungi penjual untuk metode pembayaran lain.';
            }

            // Get SubMerchant
            $subMerchant = $aiAgent->getSubMerchant();
            if (! $subMerchant) {
                return 'Maaf, pembayaran QRIS belum tersedia. Silakan hubungi penjual.';
            }

            // Validate amount
            if ($amount <= 0) {
                return 'Maaf, jumlah pembayaran tidak valid.';
            }

            // Get current order if exists
            $order = $conversation->getCurrentOrder();

            // Generate QRIS
            $qrisTransaction = $this->qrisService->generateQris($subMerchant, $amount, [
                'description' => $description ?? 'Pembayaran via WhatsApp',
            ]);

            // Link QRIS to order if exists
            if ($order) {
                $qrisTransaction->linked_order_id = $order->id;
                $qrisTransaction->save();

                // Create Payment record
                Payment::create([
                    'order_id' => $order->id,
                    'qris_transaction_id' => $qrisTransaction->id,
                    'method' => Payment::METHOD_QRIS,
                    'amount' => $amount,
                    'status' => Payment::STATUS_PENDING,
                ]);
            }

            // Store QRIS transaction in conversation context
            $conversation->setCurrentQrisTransaction($qrisTransaction->id);

            // Format response message
            $expiryTime = $qrisTransaction->expires_at->format('H:i');
            $formattedAmount = 'Rp '.number_format($amount, 0, ',', '.');

            $shareableLink = $qrisTransaction->getShareableLink();

            $response = "💳 Pembayaran QRIS\n\n";
            $response .= "Total: {$formattedAmount}\n\n";
            $response .= "📱 Silakan bayar melalui link berikut:\n";
            $response .= "{$shareableLink}\n\n";
            $response .= "⏰ Berlaku hingga: {$expiryTime}\n\n";
            $response .= "Cara pembayaran:\n";
            $response .= "1. Klik link di atas\n";
            $response .= "2. Scan QR Code yang muncul\n";
            $response .= "3. Buka aplikasi e-wallet/mobile banking\n";
            $response .= "4. Konfirmasi pembayaran\n\n";
            $response .= "Setelah pembayaran berhasil, ketik 'cek status' untuk konfirmasi. 🙏";

            return $response;

        } catch (\Exception $e) {
            Log::error('QRIS generation failed in AI Agent', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
                'amount' => $amount,
            ]);

            return 'Maaf, terjadi kesalahan saat membuat kode pembayaran. Silakan coba lagi atau hubungi penjual.';
        }
    }

    /**
     * Check payment status for current QRIS transaction.
     */
    protected function checkPaymentStatus(AiAgentConversation $conversation): string
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

                    $deliveryType = $conversation->order_context['delivery_type'] ?? null;
                    if ($deliveryType === Order::DELIVERY_TYPE_DELIVERY) {
                        $address = $conversation->order_context['delivery_address'] ?? null;
                        $response .= '🚚 Pesanan Anda sedang disiapkan dan akan segera diantar';
                        if ($address) {
                            $response .= " ke:\n📍 {$address}";
                        }
                        $response .= "\n\nMohon siapkan diri untuk menerima pesanan. Terima kasih! 🙏";
                    } else {
                        $response .= 'Pesanan Anda sedang diproses. Terima kasih atas pembayaran Anda! 🙏';
                    }

                    // Clear payment context so it's not double-counted
                    $conversation->clearPaymentContext();
                    $conversation->clearCart();
                    $conversation->clearPendingOrder();

                    return $response;

                case QrisTransaction::STATUS_PENDING:
                    if ($qrisTransaction->isExpired()) {
                        return "⏰ Kode pembayaran sudah kadaluarsa.\n\n".
                               "Ketik 'buat qris baru' untuk mendapatkan kode pembayaran baru.";
                    }

                    $remainingMinutes = ceil($qrisTransaction->getRemainingTimeInSeconds() / 60);

                    return "⏳ Pembayaran Menunggu\n\n".
                           "Jumlah: {$formattedAmount}\n".
                           "Sisa waktu: {$remainingMinutes} menit\n\n".
                           "Silakan selesaikan pembayaran melalui QR Code yang sudah diberikan.\n".
                           'Jika sudah membayar, tunggu beberapa saat lalu cek status kembali.';

                case QrisTransaction::STATUS_EXPIRE:
                    return "⏰ Kode pembayaran sudah kadaluarsa.\n\n".
                           "Ketik 'buat qris baru' untuk mendapatkan kode pembayaran baru.";

                case QrisTransaction::STATUS_CANCEL:
                    return "❌ Pembayaran dibatalkan.\n\n".
                           'Silakan buat pesanan baru jika ingin melanjutkan.';

                default:
                    return "Status pembayaran: {$qrisTransaction->status}\n".
                           'Silakan hubungi penjual untuk informasi lebih lanjut.';
            }

        } catch (\Exception $e) {
            Log::error('Payment status check failed', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
            ]);

            return 'Maaf, terjadi kesalahan saat mengecek status pembayaran. Silakan coba lagi.';
        }
    }

    /**
     * Build a short, deterministic greeting message. Used by the GREETING
     * short-circuit so we don't burn LLM tokens on a "hello" reply.
     */
    protected function buildGreeting(AiAgent $aiAgent): string
    {
        $botName = $aiAgent->bot_name ?: 'asisten kami';
        $custom = $aiAgent->settings['greeting_message'] ?? null;

        if (is_string($custom) && trim($custom) !== '') {
            return $custom;
        }

        return "Halo! Selamat datang di {$botName}. Tap *Lihat Menu* untuk mulai memesan, "
             . "atau kirim pesan kalau ada yang ingin ditanyakan.";
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
        WhatsAppContact $contact,
        AiAgent $aiAgent
    ): void {
        try {
            $pendingOrder = $conversation->getPendingOrder();

            if (! $aiAgent->default_store_id) {
                $this->sendReply(
                    $account,
                    $contact->wa_id,
                    'Maaf, toko default belum dikonfigurasi. Silakan hubungi administrator.'
                );

                return;
            }

            $deliveryType = $conversation->getDeliveryType() ?? 'pickup';
            $deliveryAddress = $conversation->getDeliveryAddress();
            $deliveryNotes = $conversation->getDeliveryNotes();
            $ongkir = $conversation->getOngkir();

            // Create order with source and customer info
            $order = $this->orderService->create([
                'store_id' => $aiAgent->default_store_id,
                'table_id' => null,
                'pos_user_id' => null,
                'source' => Order::SOURCE_WHATSAPP_AI,
                'customer_name' => $contact->name ?? 'WhatsApp Customer',
                'customer_phone' => $contact->wa_id,
                'delivery_type' => $deliveryType,
                'alamat' => $deliveryType === 'delivery' ? $deliveryAddress : null,
                'ongkir' => $ongkir,
                'catatan' => $deliveryNotes,
            ]);

            // Add items
            foreach ($pendingOrder['items'] as $item) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $this->orderService->addItem($order, $product, $item['quantity']);
                }
            }

            // Refresh order to get updated total
            $order->refresh();

            // Store order in conversation context
            $conversation->setCurrentOrder($order->id);

            // Clear cart and pending order
            $conversation->clearCart();
            $conversation->clearPendingOrder();
            $conversation->clearDeliveryContext();

            // Check if QRIS is enabled - auto generate QRIS
            Log::info('QRIS check during order confirmation', [
                'ai_agent_id' => $aiAgent->id,
                'qris_enabled' => $aiAgent->qris_enabled,
                'has_active_submerchant' => $aiAgent->hasActiveSubMerchant(),
                'is_qris_enabled' => $aiAgent->isQrisEnabled(),
                'order_id' => $order->id,
                'order_total' => $order->total,
            ]);

            if ($aiAgent->isQrisEnabled()) {
                $subMerchant = $aiAgent->getSubMerchant();

                Log::info('QRIS enabled, attempting generation', [
                    'submerchant_found' => $subMerchant !== null,
                    'submerchant_id' => $subMerchant?->id,
                ]);

                if ($subMerchant) {
                    try {
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

                        // Send order confirmation with QRIS
                        $expiryTime = $qrisTransaction->expires_at->format('H:i');
                        $formattedTotal = 'Rp '.number_format($order->total, 0, ',', '.');

                        $shareableLink = $qrisTransaction->getShareableLink();

                        $response = "✅ Pesanan berhasil dibuat!\n\n";
                        $response .= "Nomor Pesanan: {$order->order_number}\n";
                        $response .= "Total: {$formattedTotal}\n\n";
                        $response .= "💳 Silakan bayar melalui link berikut:\n";
                        $response .= "{$shareableLink}\n\n";
                        $response .= "⏰ Berlaku hingga: {$expiryTime}\n\n";
                        $response .= "Cara pembayaran:\n";
                        $response .= "1. Klik link di atas\n";
                        $response .= "2. Scan QR Code yang muncul\n";
                        $response .= "3. Buka aplikasi e-wallet/mobile banking\n";
                        $response .= "4. Konfirmasi pembayaran\n\n";
                        $response .= "Ketik 'cek status' setelah membayar. 🙏";

                        $this->sendReply($account, $contact->wa_id, $response);

                        return;

                    } catch (\Exception $e) {
                        Log::error('QRIS generation failed during order confirmation', [
                            'error' => $e->getMessage(),
                            'order_id' => $order->id,
                        ]);
                        // Fall through to send order without QRIS
                    }
                }
            }

            // Send confirmation without QRIS (fallback or QRIS not enabled)
            Log::warning('Order confirmed without QRIS', [
                'ai_agent_id' => $aiAgent->id,
                'is_qris_enabled' => $aiAgent->isQrisEnabled(),
                'order_id' => $order->id,
            ]);

            $response = "✅ Pesanan Berhasil Dibuat!\n\n";
            $response .= "📋 No. Pesanan: {$order->order_number}\n";
            $response .= '💰 Total: Rp '.number_format($order->total, 0, ',', '.')."\n\n";
            $response .= "Pesanan Anda sedang diproses.\n";
            $response .= "Silakan tunjukkan pesan ini ke kasir untuk melakukan pembayaran.\n\n";
            $response .= 'Terima kasih! 🙏';

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

    /**
     * Send payment confirmation for POS manual orders (no AI conversation).
     * Sends directly to customer_phone stored on the linked order.
     */
    private function sendPosOrderPaymentConfirmation(QrisTransaction $qrisTransaction): void
    {
        try {
            $order = $qrisTransaction->linkedOrder;

            if (! $order || empty($order->customer_phone)) {
                Log::info('POS order has no customer_phone, skipping payment confirmation', [
                    'qris_transaction_id' => $qrisTransaction->id,
                    'linked_order_id' => $qrisTransaction->linked_order_id,
                ]);

                return;
            }

            // Get WhatsApp account for the store owner
            $storeUserId = $order->store->user_id;
            $account = $this->whatsappAccountService->getActiveAccount($storeUserId);

            if (! $account) {
                Log::warning('No active WhatsApp account for POS payment confirmation', [
                    'store_user_id' => $storeUserId,
                    'order_id' => $order->id,
                ]);

                return;
            }

            $phone = preg_replace('/[^0-9]/', '', $order->customer_phone);
            $formattedAmount = 'Rp '.number_format($qrisTransaction->amount, 0, ',', '.');

            $message = "✅ *Pembayaran Berhasil!*\n\n";
            $message .= "No. Pesanan: #{$order->order_number}\n";
            $message .= "Jumlah: {$formattedAmount}\n";

            if ($order->delivery_type === Order::DELIVERY_TYPE_DELIVERY && $order->alamat) {
                $message .= "\n🚚 Pesanan Anda sedang disiapkan dan akan segera diantar ke:\n";
                $message .= "📍 {$order->alamat}\n";
                $message .= "\nMohon siapkan diri untuk menerima pesanan. Terima kasih! 🙏";
            } else {
                $message .= "\nPesanan Anda sedang diproses. Terima kasih! 🙏";
            }

            $this->sendReply($account, $phone, $message);

            Log::info('POS order payment confirmation sent via WhatsApp', [
                'order_id' => $order->id,
                'customer_phone' => $phone,
                'qris_transaction_id' => $qrisTransaction->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send POS order payment confirmation', [
                'error' => $e->getMessage(),
                'qris_transaction_id' => $qrisTransaction->id,
            ]);
        }
    }

    /**
     * Send payment confirmation to customer via WhatsApp.
     * Called when QRIS payment is completed via webhook.
     */
    public function sendPaymentConfirmation(QrisTransaction $qrisTransaction): void
    {
        try {
            // Find conversation linked to this QRIS transaction
            $conversation = AiAgentConversation::where('current_qris_transaction_id', $qrisTransaction->id)
                ->first();

            if (! $conversation) {
                Log::info('No AI conversation for QRIS transaction, falling back to POS order confirmation', [
                    'qris_transaction_id' => $qrisTransaction->id,
                    'linked_order_id' => $qrisTransaction->linked_order_id,
                ]);
                // Fallback: POS manual order — send directly to customer_phone on the linked order
                $this->sendPosOrderPaymentConfirmation($qrisTransaction);

                return;
            }

            // Get WhatsApp contact and account
            $contact = $conversation->whatsappContact()
                ->withoutGlobalScope('userContacts')
                ->first();
            $aiAgent = $conversation->aiAgent;

            if (! $contact || ! $aiAgent) {
                Log::warning('Missing contact or AI agent for payment confirmation', [
                    'conversation_id' => $conversation->id,
                    'has_contact' => $contact !== null,
                    'has_ai_agent' => $aiAgent !== null,
                ]);

                return;
            }

            $account = $aiAgent->whatsappAccount()
                ->withoutGlobalScope('userAccounts')
                ->first();
            if (! $account || ! $account->is_active) {
                Log::warning('WhatsApp account not available for payment confirmation', [
                    'ai_agent_id' => $aiAgent->id,
                ]);

                return;
            }

            // Format confirmation message
            $formattedAmount = 'Rp '.number_format($qrisTransaction->amount, 0, ',', '.');
            $order = $conversation->getCurrentOrder();

            $message = "✅ Pembayaran Berhasil!\n\n";
            $message .= "Jumlah: {$formattedAmount}\n";
            $message .= "No. Transaksi: {$qrisTransaction->order_id}\n";

            if ($order) {
                $message .= "No. Pesanan: {$order->order_number}\n";
            }

            $deliveryType = $conversation->order_context['delivery_type'] ?? null;
            if ($deliveryType === Order::DELIVERY_TYPE_DELIVERY) {
                $address = $conversation->order_context['delivery_address'] ?? null;
                $message .= "\n🚚 Pesanan Anda sedang disiapkan dan akan segera diantar";
                if ($address) {
                    $message .= " ke:\n📍 {$address}";
                }
                $message .= "\n\nMohon siapkan diri untuk menerima pesanan. Terima kasih! 🙏";
            } else {
                $message .= "\nTerima kasih atas pembayaran Anda! Pesanan sedang diproses. 🙏";
            }

            // Send WhatsApp message
            $this->sendReply($account, $contact->wa_id, $message);

            // Add message to conversation history
            $conversation->addMessage('ai', $message);

            // Clear payment context
            $conversation->clearPaymentContext();
            $conversation->clearCart();
            $conversation->clearPendingOrder();

            Log::info('Payment confirmation sent via WhatsApp', [
                'qris_transaction_id' => $qrisTransaction->id,
                'conversation_id' => $conversation->id,
                'contact_wa_id' => $contact->wa_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation', [
                'error' => $e->getMessage(),
                'qris_transaction_id' => $qrisTransaction->id,
            ]);
        }
    }
}
