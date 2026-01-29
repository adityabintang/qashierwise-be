<?php

namespace Tests\Feature;

use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgentService;
use App\Services\ConversationSummarizer;
use App\Services\IntentTracker;
use App\Services\SummaryValidator;
use App\Services\TokenEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * End-to-end integration tests for Conversation Summarization feature.
 *
 * Tests the complete flow from message processing through summarization
 * to context usage in subsequent LLM calls.
 */
class ConversationSummarizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WhatsAppAccount $whatsappAccount;

    private WhatsAppContact $whatsappContact;

    private AiAgent $aiAgent;

    private AiAgentService $aiAgentService;

    private ConversationSummarizer $summarizer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and WhatsApp account
        $this->user = User::factory()->create();
        $this->whatsappAccount = WhatsAppAccount::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Create WhatsApp contact
        $this->whatsappContact = WhatsAppContact::create([
            'user_id' => $this->user->id,
            'wa_id' => '+6281234567890',
            'name' => 'Test User',
        ]);

        // Create AI agent
        $this->aiAgent = AiAgent::create([
            'whatsapp_account_id' => $this->whatsappAccount->id,
            'bot_name' => 'Test Agent',
            'system_prompt' => 'You are a helpful restaurant assistant.',
            'is_active' => true,
        ]);

        // Get services from container
        $this->aiAgentService = app(AiAgentService::class);
        $this->summarizer = app(ConversationSummarizer::class);
    }

    /**
     * Test complete flow: multiple messages → summarization → context usage
     */
    public function test_complete_summarization_flow_with_real_conversation(): void
    {
        // Mock LLM responses
        Http::fake([
            '*/chat/completions' => Http::sequence()
                // Response 1: Initial greeting
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => 'Halo! Selamat datang di restoran kami. Ada yang bisa saya bantu?',
                        ],
                    ]],
                ])
                // Response 2: Menu inquiry
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => 'Kami punya menu ayam goreng, nasi goreng, dan mie goreng. Mau pesan yang mana?',
                        ],
                    ]],
                ])
                // Response 3-6: More conversation
                ->push([
                    'choices' => [[
                        'message' => ['content' => 'Ayam goreng kami sangat enak! Berapa porsi yang Anda inginkan?'],
                    ]],
                ])
                ->push([
                    'choices' => [[
                        'message' => ['content' => 'Baik, 2 porsi ayam goreng. Ada yang lain?'],
                    ]],
                ])
                ->push([
                    'choices' => [[
                        'message' => ['content' => 'Oke, jadi 2 ayam goreng dan 1 nasi goreng. Total Rp 75.000. Mau pesan sekarang?'],
                    ]],
                ])
                // Response: Summarization call
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'summary' => 'User ingin memesan makanan: 2 porsi ayam goreng dan 1 nasi goreng',
                                'intent' => 'order_food',
                                'key_data' => [
                                    'products' => ['ayam goreng', 'nasi goreng'],
                                    'order_items' => [
                                        ['product' => 'ayam goreng', 'quantity' => 2, 'price' => 25000],
                                        ['product' => 'nasi goreng', 'quantity' => 1, 'price' => 25000],
                                    ],
                                    'total_estimate' => 75000,
                                    'reservation_date' => null,
                                    'reservation_time' => null,
                                    'people_count' => null,
                                ],
                                'missing_information' => [],
                            ]),
                        ],
                    ]],
                ])
                // Response: After summarization
                ->push([
                    'choices' => [[
                        'message' => ['content' => 'Pesanan Anda sudah dikonfirmasi! Silakan lakukan pembayaran.'],
                    ]],
                ]),
        ]);

        // Create conversation
        $conversation = AiAgentConversation::create([
            'ai_agent_id' => $this->aiAgent->id,
            'whatsapp_contact_id' => $this->whatsappContact->id,
            'messages' => [],
            'order_context' => [],
            'expires_at' => now()->addHour(),
        ]);

        // Simulate conversation flow
        $messages = [
            'Halo, saya mau pesan makanan',
            'Ada menu apa saja?',
            'Saya mau ayam goreng',
            '2 porsi',
            'Tambah nasi goreng 1 porsi',
            'Ya, pesan sekarang',
        ];

        foreach ($messages as $index => $message) {
            // Add user message
            $currentMessages = $conversation->messages ?? [];
            $currentMessages[] = [
                'type' => 'human',
                'content' => $message,
                'timestamp' => now()->toIso8601String(),
            ];
            $conversation->messages = $currentMessages;
            $conversation->save();

            // Check if summarization should be triggered
            $shouldSummarize = $this->summarizer->shouldSummarize($conversation);

            // If we have 6+ messages, summarization should trigger
            if (count($currentMessages) >= 6 && $index === 5) {
                $this->assertTrue($shouldSummarize, 'Summarization should be triggered after 6 messages');

                // Generate summary
                $summary = $this->summarizer->generateSummary($currentMessages);
                $this->assertNotNull($summary, 'Summary should be generated');
                $this->assertIsArray($summary);
                $this->assertArrayHasKey('summary', $summary);
                $this->assertArrayHasKey('intent', $summary);
                $this->assertArrayHasKey('key_data', $summary);

                // Store summary
                $this->summarizer->storeSummary($conversation, $summary);
                $conversation->refresh();

                // Verify summary is stored
                $this->assertTrue($conversation->hasSummary());
                $storedSummary = $conversation->getSummary();
                $this->assertEquals('order_food', $storedSummary['intent']);
            }

            // Get AI response (simulated)
            $aiResponse = 'Response '.($index + 1);
            $currentMessages[] = [
                'type' => 'ai',
                'content' => $aiResponse,
                'timestamp' => now()->toIso8601String(),
            ];
            $conversation->messages = $currentMessages;
            $conversation->save();
        }

        // Verify final state
        $this->assertTrue($conversation->hasSummary());
        $summary = $conversation->getSummary();
        $this->assertEquals('order_food', $summary['intent']);
        $this->assertArrayHasKey('order_items', $summary['key_data']);
    }

    /**
     * Test token savings calculation
     */
    public function test_token_savings_in_production_scenario(): void
    {
        $tokenEstimator = new TokenEstimator;

        // Create a long conversation (simulating real WhatsApp chat)
        $longConversation = [
            ['type' => 'human', 'content' => 'Halo, saya mau tanya menu apa saja yang tersedia di restoran ini?'],
            ['type' => 'ai', 'content' => 'Halo! Kami punya berbagai menu: ayam goreng, nasi goreng, mie goreng, sate ayam, dan gado-gado.'],
            ['type' => 'human', 'content' => 'Berapa harga ayam gorengnya?'],
            ['type' => 'ai', 'content' => 'Ayam goreng kami Rp 25.000 per porsi.'],
            ['type' => 'human', 'content' => 'Kalau nasi goreng?'],
            ['type' => 'ai', 'content' => 'Nasi goreng juga Rp 25.000 per porsi.'],
            ['type' => 'human', 'content' => 'Oke, saya mau pesan 2 ayam goreng dan 1 nasi goreng'],
            ['type' => 'ai', 'content' => 'Baik, jadi 2 porsi ayam goreng dan 1 porsi nasi goreng. Total Rp 75.000. Benar?'],
        ];

        // Calculate original token count
        $originalTokens = $tokenEstimator->estimateConversationTokens($longConversation);

        // Create summary
        $summary = [
            'summary' => 'User memesan 2 ayam goreng dan 1 nasi goreng, total Rp 75.000',
            'intent' => 'order_food',
            'key_data' => [
                'products' => ['ayam goreng', 'nasi goreng'],
                'order_items' => [
                    ['product' => 'ayam goreng', 'quantity' => 2, 'price' => 25000],
                    ['product' => 'nasi goreng', 'quantity' => 1, 'price' => 25000],
                ],
                'total_estimate' => 75000,
            ],
            'missing_information' => [],
        ];

        // Calculate summary token count
        $summaryTokens = $tokenEstimator->estimateTokens(json_encode($summary));

        // Verify token savings
        $tokenSavings = $originalTokens - $summaryTokens;
        $savingsPercentage = ($tokenSavings / $originalTokens) * 100;

        $this->assertGreaterThan(0, $tokenSavings, 'Should have token savings');
        $this->assertGreaterThan(15, $savingsPercentage, 'Should save at least 15% of tokens');

        Log::info('Token savings test', [
            'original_tokens' => $originalTokens,
            'summary_tokens' => $summaryTokens,
            'savings' => $tokenSavings,
            'savings_percentage' => round($savingsPercentage, 2),
        ]);
    }

    /**
     * Test summary quality and context preservation
     */
    public function test_summary_quality_and_context_preservation(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'summary' => 'User ingin reservasi meja untuk 4 orang pada tanggal 15 Januari jam 19:00',
                            'intent' => 'reservation',
                            'key_data' => [
                                'products' => [],
                                'reservation_date' => '2024-01-15',
                                'reservation_time' => '19:00',
                                'people_count' => 4,
                                'order_items' => [],
                                'total_estimate' => null,
                            ],
                            'missing_information' => ['contact_name', 'phone_number'],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $messages = [
            ['type' => 'human', 'content' => 'Saya mau reservasi meja'],
            ['type' => 'ai', 'content' => 'Baik, untuk berapa orang?'],
            ['type' => 'human', 'content' => 'Untuk 4 orang'],
            ['type' => 'ai', 'content' => 'Kapan Anda ingin reservasi?'],
            ['type' => 'human', 'content' => 'Tanggal 15 Januari'],
            ['type' => 'ai', 'content' => 'Jam berapa?'],
            ['type' => 'human', 'content' => 'Jam 7 malam'],
        ];

        $summary = $this->summarizer->generateSummary($messages);

        $this->assertNotNull($summary);
        $this->assertEquals('reservation', $summary['intent']);
        $this->assertEquals(4, $summary['key_data']['people_count']);
        $this->assertEquals('2024-01-15', $summary['key_data']['reservation_date']);
        $this->assertEquals('19:00', $summary['key_data']['reservation_time']);
        $this->assertContains('contact_name', $summary['missing_information']);
        $this->assertContains('phone_number', $summary['missing_information']);
    }

    /**
     * Test intent change detection
     */
    public function test_intent_change_detection_triggers_summarization(): void
    {
        $intentTracker = new IntentTracker;

        $conversation = AiAgentConversation::create([
            'ai_agent_id' => $this->aiAgent->id,
            'whatsapp_contact_id' => $this->whatsappContact->id,
            'messages' => [],
            'order_context' => ['last_intent' => 'browse_menu'],
            'expires_at' => now()->addHour(),
        ]);

        // Check initial intent
        $currentIntent = $intentTracker->getCurrentIntent($conversation);
        $this->assertEquals('browse_menu', $currentIntent);

        // Check if intent changed
        $hasChanged = $intentTracker->hasIntentChanged($conversation, 'order_food');
        $this->assertTrue($hasChanged);

        // Update intent
        $intentTracker->updateIntent($conversation, 'order_food');
        $conversation->refresh();

        // Verify intent was updated
        $newIntent = $intentTracker->getCurrentIntent($conversation);
        $this->assertEquals('order_food', $newIntent);
    }

    /**
     * Test error handling and fallback to full messages
     */
    public function test_error_handling_fallback_to_full_messages(): void
    {
        // Mock LLM to return invalid JSON
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => 'This is not valid JSON at all!',
                    ],
                ]],
            ]),
        ]);

        $messages = [
            ['type' => 'human', 'content' => 'Test message 1'],
            ['type' => 'ai', 'content' => 'Response 1'],
            ['type' => 'human', 'content' => 'Test message 2'],
            ['type' => 'ai', 'content' => 'Response 2'],
            ['type' => 'human', 'content' => 'Test message 3'],
            ['type' => 'ai', 'content' => 'Response 3'],
        ];

        // Try to generate summary (should fail gracefully)
        $summary = $this->summarizer->generateSummary($messages);
        $this->assertNull($summary, 'Should return null for invalid JSON');

        // Create conversation without summary
        $conversation = AiAgentConversation::create([
            'ai_agent_id' => $this->aiAgent->id,
            'whatsapp_contact_id' => $this->whatsappContact->id,
            'messages' => $messages,
            'order_context' => [],
            'expires_at' => now()->addHour(),
        ]);

        // Get context should fallback to full messages
        $context = $this->summarizer->getContextForLLM($conversation);
        $this->assertIsArray($context);
        $this->assertCount(6, $context);
        $this->assertEquals($messages, $context);
    }

    /**
     * Test backward compatibility with conversations without summary
     */
    public function test_backward_compatibility_without_summary(): void
    {
        $messages = [
            ['type' => 'human', 'content' => 'Hello'],
            ['type' => 'ai', 'content' => 'Hi there!'],
        ];

        $conversation = AiAgentConversation::create([
            'ai_agent_id' => $this->aiAgent->id,
            'whatsapp_contact_id' => $this->whatsappContact->id,
            'messages' => $messages,
            'order_context' => [],
            'expires_at' => now()->addHour(),
        ]);

        // Should not have summary
        $this->assertFalse($conversation->hasSummary());

        // Should not trigger summarization (too few messages)
        $shouldSummarize = $this->summarizer->shouldSummarize($conversation);
        $this->assertFalse($shouldSummarize);

        // Get context should return full messages
        $context = $this->summarizer->getContextForLLM($conversation);
        $this->assertEquals($messages, $context);
    }

    /**
     * Test conversation expiry and cleanup
     */
    public function test_conversation_expiry_handling(): void
    {
        // Create expired conversation
        $expiredConversation = AiAgentConversation::create([
            'ai_agent_id' => $this->aiAgent->id,
            'whatsapp_contact_id' => $this->whatsappContact->id,
            'messages' => [
                ['type' => 'human', 'content' => 'Old message'],
            ],
            'order_context' => [
                'summary' => [
                    'summary' => 'Old summary',
                    'intent' => 'browse_menu',
                ],
            ],
            'expires_at' => now()->subHour(), // Expired 1 hour ago
        ]);

        // Verify conversation is expired
        $this->assertTrue($expiredConversation->expires_at->isPast());

        // In real scenario, AiAgentService would create new conversation
        // Here we just verify the expired conversation exists
        $this->assertNotNull($expiredConversation->id);
    }

    /**
     * Test short confirmation messages don't trigger summarization
     */
    public function test_short_confirmations_dont_trigger_summarization(): void
    {
        $messages = [
            ['type' => 'human', 'content' => 'Saya mau pesan ayam goreng'],
            ['type' => 'ai', 'content' => 'Berapa porsi?'],
            ['type' => 'human', 'content' => '2 porsi'],
            ['type' => 'ai', 'content' => 'Baik, 2 porsi ayam goreng. Ada lagi?'],
            ['type' => 'human', 'content' => 'Tidak, itu saja'],
            ['type' => 'ai', 'content' => 'Total Rp 50.000. Mau pesan sekarang?'],
            ['type' => 'human', 'content' => 'ya'], // Short confirmation
        ];

        $conversation = AiAgentConversation::create([
            'ai_agent_id' => $this->aiAgent->id,
            'whatsapp_contact_id' => $this->whatsappContact->id,
            'messages' => $messages,
            'order_context' => [],
            'expires_at' => now()->addHour(),
        ]);

        // Should not trigger summarization due to short confirmation
        $shouldSummarize = $this->summarizer->shouldSummarize($conversation);
        $this->assertFalse($shouldSummarize, 'Short confirmation should not trigger summarization');
    }

    /**
     * Test summary validator
     */
    public function test_summary_validator_validates_correctly(): void
    {
        $validator = new SummaryValidator;

        // Valid summary
        $validSummary = [
            'summary' => 'User wants to order food',
            'intent' => 'order_food',
            'key_data' => [
                'products' => ['ayam goreng'],
            ],
            'missing_information' => [],
        ];

        $this->assertTrue($validator->validate($validSummary));
        $this->assertEmpty($validator->getErrors());

        // Invalid summary - missing fields
        $invalidSummary = [
            'summary' => 'Test',
        ];

        $this->assertFalse($validator->validate($invalidSummary));
        $this->assertNotEmpty($validator->getErrors());

        // Invalid summary - wrong intent
        $invalidIntent = [
            'summary' => 'Test',
            'intent' => 'invalid_intent',
            'key_data' => [],
            'missing_information' => [],
        ];

        $this->assertFalse($validator->validate($invalidIntent));
        $errors = $validator->getErrors();
        $this->assertStringContainsString('intent', $errors[0]);
    }
}
