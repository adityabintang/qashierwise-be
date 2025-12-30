<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\QrisTransaction;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

class AiAgentService
{
    protected WhatsAppAccountService $whatsappAccountService;

    protected OrderService $orderService;

    protected QrisService $qrisService;

    protected ConversationSummarizer $conversationSummarizer;

    protected IntentTracker $intentTracker;

    public function __construct(
        WhatsAppAccountService $whatsappAccountService,
        OrderService $orderService,
        QrisService $qrisService,
        ConversationSummarizer $conversationSummarizer,
        IntentTracker $intentTracker
    ) {
        $this->whatsappAccountService = $whatsappAccountService;
        $this->orderService = $orderService;
        $this->qrisService = $qrisService;
        $this->conversationSummarizer = $conversationSummarizer;
        $this->intentTracker = $intentTracker;
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
            $conversation->addMessage('human', $messageText);

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

            // Build system prompt
            $systemPrompt = $aiAgent->buildSystemPrompt($account->user_id);

            // Get conversation context (summary + recent messages OR full messages)
            $messages = $this->conversationSummarizer->getContextForLLM($conversation);
            
            // Log context usage for monitoring
            $hasSummary = $conversation->hasSummary();
            Log::info('Using conversation context for LLM', [
                'conversation_id' => $conversation->id,
                'using_summary' => $hasSummary,
                'context_message_count' => count($messages),
                'total_message_count' => count($conversation->messages ?? []),
            ]);

            // Call LLM with tools
            $tools = $this->getToolDefinitionsForAgent($aiAgent);
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
            // Map type (human/ai) to role (user/assistant) for LLM API
            $role = match($msg['type'] ?? $msg['role'] ?? 'user') {
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
                    if (!isset($choice['message'])) {
                        Log::error('Invalid LLM response format - no message', [
                            'choice' => $choice,
                        ]);
                        throw new \Exception('Invalid LLM response format: no message in choice');
                    }

                    if (isset($choice['message']['tool_calls'])) {
                        Log::info('LLM returned tool calls', [
                            'tool_calls_count' => count($choice['message']['tool_calls']),
                            'tool_calls' => array_map(function($tc) {
                                return [
                                    'id' => $tc['id'] ?? null,
                                    'function' => $tc['function']['name'] ?? 'unknown',
                                    'arguments' => $tc['function']['arguments'] ?? '{}',
                                ];
                            }, $choice['message']['tool_calls']),
                        ]);

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
                    'description' => 'Cari SATU produk berdasarkan nama atau SKU. Untuk mencari MULTIPLE produk sekaligus (contoh: "dimsum dan teh"), gunakan search_multiple_products.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Kata kunci pencarian PENDEK untuk SATU produk (contoh: "dimsum", "teh", "nasi").',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_multiple_products',
                    'description' => 'WAJIB DIGUNAKAN saat user memesan LEBIH DARI SATU produk sekaligus (contoh: "pesan dimsum dan teh jumbo", "mau nasi goreng sama es teh"). Cari beberapa produk sekaligus dalam satu panggilan. Hasil akan menampilkan semua produk yang ditemukan beserta ID-nya untuk digunakan di add_to_cart.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'queries' => [
                                'type' => 'array',
                                'description' => 'Array kata kunci pencarian. Setiap kata kunci untuk satu produk. Contoh: ["dimsum", "teh"] untuk mencari dimsum dan teh.',
                                'items' => [
                                    'type' => 'string',
                                    'description' => 'Kata kunci PENDEK untuk satu produk',
                                ],
                            ],
                        ],
                        'required' => ['queries'],
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
                    'description' => 'Tambahkan satu atau lebih produk ke keranjang belanja. PENTING: User akan menyebutkan NAMA produk, kamu harus cari ID produk dari daftar produk atau hasil search_products terlebih dahulu. Untuk multiple produk, gunakan array products dan masukkan SEMUA produk dalam SATU pemanggilan function.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'products' => [
                                'type' => 'array',
                                'description' => 'Array of products to add. Each product must have product_id (yang sudah kamu cari dari nama produk) and quantity.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'product_id' => [
                                            'type' => 'integer',
                                            'description' => 'ID produk yang sudah kamu cari dari nama produk yang disebutkan user',
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
                        'required' => ['products'],
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
     * Get tool definitions based on AI Agent configuration.
     */
    protected function getToolDefinitionsForAgent(AiAgent $aiAgent): ?array
    {
        if (!$aiAgent->isOrderEnabled()) {
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
     * Get QRIS-specific tool definitions.
     */
    protected function getQrisToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_qris',
                    'description' => 'Generate kode QRIS untuk pembayaran pesanan. Gunakan setelah pesanan dikonfirmasi.',
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
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_payment_status',
                    'description' => 'Cek status pembayaran QRIS terakhir',
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
        // Log how many tool calls received
        Log::info('Handling tool calls from LLM', [
            'tool_calls_count' => count($toolCalls),
            'tool_calls' => array_map(function($tc) {
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
            if (!is_array($arguments)) {
                $arguments = [];
            }
            
            $toolCallId = $toolCall['id'] ?? uniqid();

            Log::info('Executing tool call', [
                'function' => $functionName,
                'arguments' => $arguments,
            ]);

            $result = $this->executeToolCall($functionName, $arguments, $account->user_id, $conversation);
            
            // Track what type of calls we have
            if (in_array($functionName, ['search_products', 'search_multiple_products'])) {
                $hasSearchCall = true;
            }
            if (in_array($functionName, ['add_to_cart', 'confirm_order', 'get_cart_summary', 'generate_qris', 'check_payment_status'])) {
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
        if ($hasSearchCall && !$hasFinalAction) {
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
            
            // Build messages with tool results for LLM to continue
            $messages = $conversation->messages ?? [];
            
            // Add assistant message with tool calls
            $messages[] = [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => $toolCalls,
            ];
            
            // Add tool results
            foreach ($toolResults as $tr) {
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tr['tool_call_id'],
                    'content' => $tr['result'],
                ];
            }

            // Call LLM again to continue processing
            $systemPrompt = $aiAgent->buildSystemPrompt($account->user_id);
            $tools = $this->getToolDefinitionsForAgent($aiAgent);
            
            try {
                $response = $this->callLLMWithToolResults($systemPrompt, $messages, $tools);
                
                // If LLM returns more tool calls, handle them
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
                
                // Otherwise send the response
                $assistantMessage = $response['content'] ?? '';
                if ($assistantMessage) {
                    $conversation->addMessage('ai', $assistantMessage);
                    $this->sendReply($account, $contact->wa_id, $assistantMessage);
                }
                return;
            } catch (\Exception $e) {
                Log::error('Error in follow-up LLM call', ['error' => $e->getMessage()]);
                // Fall through to send search results if follow-up fails
            }
        }

        // For final actions or if follow-up failed, send results to user
        $userFacingResults = [];
        foreach ($toolResults as $tr) {
            // Don't show raw search results to user - they're internal
            if (!in_array($tr['function_name'], ['search_products', 'search_multiple_products'])) {
                $userFacingResults[] = $tr['result'];
            }
        }

        // Send combined result to user
        $responseMessage = implode("\n\n", array_filter($userFacingResults));
        if ($responseMessage) {
            $conversation->addMessage('ai', $responseMessage);
            $this->sendReply($account, $contact->wa_id, $responseMessage);
        }
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
                $role = match($msg['type'] ?? $msg['role'] ?? 'user') {
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
            $payload['tool_choice' ] = 'auto';
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

        throw new \Exception('LLM API call failed: ' . $response->body());
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
            // Validate parameters based on function requirements
            switch ($functionName) {
                case 'search_products':
                    if (!isset($arguments['query'])) {
                        return 'Maaf, parameter pencarian tidak lengkap. Mohon berikan kata kunci pencarian.';
                    }
                    return $this->searchProducts($userId, $arguments['query']);

                case 'search_multiple_products':
                    if (!isset($arguments['queries']) || !is_array($arguments['queries'])) {
                        return 'Maaf, parameter pencarian tidak lengkap. Mohon berikan array kata kunci pencarian.';
                    }
                    return $this->searchMultipleProducts($userId, $arguments['queries']);

                case 'get_product_details':
                    if (!isset($arguments['product_id'])) {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan ID produk.';
                    }
                    if (!is_numeric($arguments['product_id'])) {
                        return 'Maaf, ID produk harus berupa angka.';
                    }
                    return $this->getProductDetails($userId, (int) $arguments['product_id']);

                case 'add_to_cart':
                    // Support both array of products and single product
                    if (isset($arguments['products']) && is_array($arguments['products'])) {
                        // Batch add to cart
                        return $this->addMultipleToCart($conversation, $userId, $arguments['products']);
                    } elseif (isset($arguments['product_id']) && isset($arguments['quantity'])) {
                        // Single product (backward compatibility)
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
                    } else {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan products (array) atau product_id dan quantity.';
                    }

                case 'get_cart_summary':
                    return $this->getCartSummary($conversation, $userId);

                case 'confirm_order':
                    return $this->prepareOrderConfirmation($conversation, $userId);

                case 'generate_qris':
                    if (!isset($arguments['amount'])) {
                        return 'Maaf, parameter tidak lengkap. Mohon berikan jumlah pembayaran.';
                    }
                    if (!is_numeric($arguments['amount'])) {
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
    protected function searchProducts(int $userId, string $query): string
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
            ->limit(10)
            ->get(['id', 'name', 'price', 'stock_quantity', 'description']);

        if ($products->isEmpty()) {
            return "Maaf, tidak ada produk yang ditemukan dengan kata kunci '{$query}'.";
        }

        // Build response for AI (with IDs in brackets for internal use)
        $response = "HASIL PENCARIAN PRODUK:\n\n";
        foreach ($products as $product) {
            $response .= "- {$product->name} [ID:{$product->id}] - Rp ".number_format($product->price, 0, ',', '.')." - Stok: {$product->stock_quantity}\n";
        }
        
        $response .= "\n**INSTRUKSI WAJIB**: Sekarang LANGSUNG panggil add_to_cart dengan ID di atas. JANGAN search lagi! JANGAN tampilkan daftar produk ke user lagi!";

        return $response;
    }

    /**
     * Search multiple products by multiple queries at once.
     * This is useful when user orders multiple products in one message.
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
                    // Avoid duplicates
                    if (!isset($allResults[$product->id])) {
                        $allResults[$product->id] = [
                            'id' => $product->id,
                            'name' => $product->name,
                            'price' => $product->price,
                            'stock' => $product->stock_quantity,
                            'query' => $query,
                        ];
                    }
                }
            }
        }

        if (empty($allResults) && !empty($notFound)) {
            return "Maaf, tidak ada produk yang ditemukan untuk: " . implode(', ', $notFound);
        }

        // Build response
        $response = "HASIL PENCARIAN PRODUK:\n\n";
        foreach ($allResults as $product) {
            $response .= "- {$product['name']} [ID:{$product['id']}] - Rp ".number_format($product['price'], 0, ',', '.')." - Stok: {$product['stock']}\n";
        }

        if (!empty($notFound)) {
            $response .= "\n⚠️ Tidak ditemukan: " . implode(', ', $notFound) . "\n";
        }

        $response .= "\n**INSTRUKSI WAJIB**: Sekarang LANGSUNG panggil add_to_cart dengan SEMUA ID di atas dalam SATU array. JANGAN search lagi! JANGAN tampilkan daftar produk ke user lagi!";

        return $response;
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
            if (!$productId || !$quantity) {
                $errors[] = 'Produk dengan data tidak lengkap dilewati';
                continue;
            }

            if (!is_numeric($productId) || !is_numeric($quantity)) {
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

            if (!$product) {
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

            if (!$found) {
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
        if (!empty($addedProducts)) {
            $conversation->updateCart($cart);
        }

        // Build response
        if (empty($addedProducts) && !empty($errors)) {
            return "❌ Gagal menambahkan produk:\n" . implode("\n", $errors);
        }

        $response = "✅ Berhasil menambahkan ke keranjang!\n\n";
        foreach ($addedProducts as $item) {
            $response .= "📦 {$item['name']} x{$item['quantity']}\n";
        }

        if (!empty($errors)) {
            $response .= "\n⚠️ Beberapa produk tidak dapat ditambahkan:\n" . implode("\n", $errors);
        }

        $response .= "\nKetik 'lihat keranjang' untuk melihat ringkasan pesanan.";

        return $response;
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
            if (!$aiAgent->isQrisEnabled()) {
                $errors = $aiAgent->validateQrisConfiguration();
                if (!empty($errors)) {
                    Log::warning('QRIS not properly configured', [
                        'ai_agent_id' => $aiAgent->id,
                        'errors' => $errors,
                    ]);
                }
                return 'Maaf, pembayaran QRIS belum tersedia saat ini. Silakan hubungi penjual untuk metode pembayaran lain.';
            }

            // Get SubMerchant
            $subMerchant = $aiAgent->getSubMerchant();
            if (!$subMerchant) {
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
            $formattedAmount = 'Rp ' . number_format($amount, 0, ',', '.');

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

            if (!$qrisTransaction) {
                return 'Tidak ada pembayaran yang sedang diproses. Silakan buat pesanan terlebih dahulu.';
            }

            // Refresh from database
            $qrisTransaction->refresh();

            $formattedAmount = 'Rp ' . number_format($qrisTransaction->amount, 0, ',', '.');

            switch ($qrisTransaction->status) {
                case QrisTransaction::STATUS_SETTLEMENT:
                    // Clear payment context after successful payment
                    $conversation->clearPaymentContext();
                    $conversation->clearCart();
                    $conversation->clearPendingOrder();

                    return "✅ Pembayaran Berhasil!\n\n" .
                           "Jumlah: {$formattedAmount}\n" .
                           "No. Transaksi: {$qrisTransaction->order_id}\n\n" .
                           "Terima kasih atas pembayaran Anda! Pesanan sedang diproses. 🙏";

                case QrisTransaction::STATUS_PENDING:
                    if ($qrisTransaction->isExpired()) {
                        return "⏰ Kode pembayaran sudah kadaluarsa.\n\n" .
                               "Ketik 'buat qris baru' untuk mendapatkan kode pembayaran baru.";
                    }

                    $remainingMinutes = ceil($qrisTransaction->getRemainingTimeInSeconds() / 60);
                    return "⏳ Pembayaran Menunggu\n\n" .
                           "Jumlah: {$formattedAmount}\n" .
                           "Sisa waktu: {$remainingMinutes} menit\n\n" .
                           "Silakan selesaikan pembayaran melalui QR Code yang sudah diberikan.\n" .
                           "Jika sudah membayar, tunggu beberapa saat lalu cek status kembali.";

                case QrisTransaction::STATUS_EXPIRE:
                    return "⏰ Kode pembayaran sudah kadaluarsa.\n\n" .
                           "Ketik 'buat qris baru' untuk mendapatkan kode pembayaran baru.";

                case QrisTransaction::STATUS_CANCEL:
                    return "❌ Pembayaran dibatalkan.\n\n" .
                           "Silakan buat pesanan baru jika ingin melanjutkan.";

                default:
                    return "Status pembayaran: {$qrisTransaction->status}\n" .
                           "Silakan hubungi penjual untuk informasi lebih lanjut.";
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

            // Create order with source and customer info
            $order = $this->orderService->create([
                'store_id' => $aiAgent->default_store_id,
                'table_id' => null,
                'pos_user_id' => null,
                'source' => Order::SOURCE_WHATSAPP_AI,
                'customer_name' => $contact->name ?? 'WhatsApp Customer',
                'customer_phone' => $contact->wa_id,
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

            // Check if QRIS is enabled - auto generate QRIS
            if ($aiAgent->isQrisEnabled()) {
                $subMerchant = $aiAgent->getSubMerchant();
                
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
                        $formattedTotal = 'Rp ' . number_format($order->total, 0, ',', '.');

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
            $response = "✅ Pesanan Berhasil Dibuat!\n\n";
            $response .= "📋 No. Pesanan: {$order->order_number}\n";
            $response .= "💰 Total: Rp ".number_format($order->total, 0, ',', '.')."\n\n";
            $response .= "Pesanan Anda sedang diproses.\n";
            $response .= "Silakan tunjukkan pesan ini ke kasir untuk melakukan pembayaran.\n\n";
            $response .= "Terima kasih! 🙏";

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
     * Send payment confirmation to customer via WhatsApp.
     * Called when QRIS payment is completed via webhook.
     */
    public function sendPaymentConfirmation(QrisTransaction $qrisTransaction): void
    {
        try {
            // Find conversation linked to this QRIS transaction
            $conversation = AiAgentConversation::where('current_qris_transaction_id', $qrisTransaction->id)
                ->first();

            if (!$conversation) {
                Log::info('No conversation found for QRIS transaction, skipping notification', [
                    'qris_transaction_id' => $qrisTransaction->id,
                    'order_id' => $qrisTransaction->order_id,
                ]);
                return;
            }

            // Get WhatsApp contact and account
            $contact = $conversation->whatsappContact;
            $aiAgent = $conversation->aiAgent;

            if (!$contact || !$aiAgent) {
                Log::warning('Missing contact or AI agent for payment confirmation', [
                    'conversation_id' => $conversation->id,
                    'has_contact' => $contact !== null,
                    'has_ai_agent' => $aiAgent !== null,
                ]);
                return;
            }

            $account = $aiAgent->whatsappAccount;
            if (!$account || !$account->is_active) {
                Log::warning('WhatsApp account not available for payment confirmation', [
                    'ai_agent_id' => $aiAgent->id,
                ]);
                return;
            }

            // Format confirmation message
            $formattedAmount = 'Rp ' . number_format($qrisTransaction->amount, 0, ',', '.');
            $order = $conversation->getCurrentOrder();

            $message = "✅ Pembayaran Berhasil!\n\n";
            $message .= "Jumlah: {$formattedAmount}\n";
            $message .= "No. Transaksi: {$qrisTransaction->order_id}\n";
            
            if ($order) {
                $message .= "No. Pesanan: {$order->order_number}\n";
            }
            
            $message .= "\nTerima kasih atas pembayaran Anda! Pesanan sedang diproses. 🙏";

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
