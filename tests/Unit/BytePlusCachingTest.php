<?php

namespace Tests\Unit;

use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BytePlusCachingTest extends TestCase
{
    use RefreshDatabase;

    protected AiAgent $agent;

    protected AiAgentConversation $conversation;

    protected AiAgentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $user = User::factory()->create();

        // Create WhatsApp account
        $account = WhatsAppAccount::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create WhatsApp contact
        $contact = WhatsAppContact::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create AI Agent with caching enabled
        $this->agent = AiAgent::factory()->create([
            'whatsapp_account_id' => $account->id,
            'bot_name' => 'Test Bot',
            'system_prompt' => 'You are a helpful assistant.',
            'enable_prompt_caching' => true,
            'use_optimized_prompt' => true,
        ]);

        // Create conversation
        $this->conversation = AiAgentConversation::create([
            'ai_agent_id' => $this->agent->id,
            'whatsapp_contact_id' => $contact->id,
            'messages' => [],
            'order_context' => [],
            'expires_at' => now()->addHours(24),
        ]);

        $this->service = app(AiAgentService::class);
    }

    /** @test */
    public function conversation_can_store_cache_response_id()
    {
        $responseId = 'resp_test_123456';

        $this->conversation->setCacheResponseId($responseId);

        $this->assertEquals($responseId, $this->conversation->cache_response_id);
        $this->assertNotNull($this->conversation->cache_expires_at);
        $this->assertTrue($this->conversation->cache_expires_at->isFuture());
    }

    /** @test */
    public function conversation_returns_null_for_expired_cache()
    {
        $this->conversation->cache_response_id = 'resp_expired_123';
        $this->conversation->cache_expires_at = now()->subHour();
        $this->conversation->save();

        $this->assertNull($this->conversation->getCacheResponseId());
        $this->assertFalse($this->conversation->hasCacheResponseId());
    }

    /** @test */
    public function conversation_returns_valid_cache_response_id()
    {
        $responseId = 'resp_valid_123';
        $this->conversation->cache_response_id = $responseId;
        $this->conversation->cache_expires_at = now()->addHours(24);
        $this->conversation->save();

        $this->assertEquals($responseId, $this->conversation->getCacheResponseId());
        $this->assertTrue($this->conversation->hasCacheResponseId());
    }

    /** @test */
    public function conversation_can_clear_cache_response_id()
    {
        $this->conversation->cache_response_id = 'resp_to_clear_123';
        $this->conversation->cache_expires_at = now()->addHours(24);
        $this->conversation->save();

        $this->conversation->clearCacheResponseId();

        $this->assertNull($this->conversation->cache_response_id);
        $this->assertNull($this->conversation->cache_expires_at);
    }

    /** @test */
    public function cache_expires_at_is_set_to_72_hours()
    {
        $this->conversation->setCacheResponseId('resp_test_123');

        // Cache should expire in approximately 72 hours
        $expectedExpiry = now()->addHours(72);
        $actualExpiry = $this->conversation->cache_expires_at;

        // Allow 1 minute tolerance
        $this->assertTrue(
            $actualExpiry->diffInMinutes($expectedExpiry) < 1,
            'Cache expiry should be approximately 72 hours from now'
        );
    }

    /** @test */
    public function call_llm_includes_caching_metadata_when_enabled()
    {
        // Mock the HTTP response for BytePlus ARK chat completions
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello! How can I help you?',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 20,
                    'total_tokens' => 120,
                ],
            ], 200),
        ]);

        $result = $this->service->callLLM(
            'You are a helpful assistant.',
            [['type' => 'human', 'content' => 'Hello']],
            null,
            $this->agent
        );

        $this->assertArrayHasKey('content', $result);
        $this->assertEquals('Hello! How can I help you?', $result['content']);

        // Verify BytePlus ARK API was called with caching metadata
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['metadata']['prompt_caching_enabled'])
                && $body['metadata']['prompt_caching_enabled'] === true;
        });
    }

    /** @test */
    public function call_llm_omits_caching_metadata_when_disabled()
    {
        // Disable caching
        $this->agent->enable_prompt_caching = false;
        $this->agent->save();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello without caching!',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 20,
                    'total_tokens' => 120,
                ],
            ], 200),
        ]);

        $result = $this->service->callLLM(
            'You are a helpful assistant.',
            [['type' => 'human', 'content' => 'Hello']],
            null,
            $this->agent
        );

        $this->assertEquals('Hello without caching!', $result['content']);

        // Verify caching metadata is NOT included
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return !isset($body['metadata']['prompt_caching_enabled']);
        });
    }

    /** @test */
    public function call_llm_handles_tool_calls()
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'tool_calls' => [
                                [
                                    'id' => 'call_123',
                                    'function' => [
                                        'name' => 'search_products',
                                        'arguments' => '{"query": "dimsum"}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 200,
                    'completion_tokens' => 30,
                    'total_tokens' => 230,
                ],
            ], 200),
        ]);

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Search for products',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->callLLM(
            'You are a helpful assistant.',
            [['type' => 'human', 'content' => 'I want dimsum']],
            $tools,
            $this->agent
        );

        $this->assertArrayHasKey('tool_calls', $result);
        $this->assertCount(1, $result['tool_calls']);
        $this->assertEquals('search_products', $result['tool_calls'][0]['function']['name']);
    }

    /** @test */
    public function call_llm_uses_correct_model_and_parameters()
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Response',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 50,
                    'completion_tokens' => 10,
                    'total_tokens' => 60,
                ],
            ], 200),
        ]);

        $this->service->callLLM(
            'Test prompt',
            [['type' => 'human', 'content' => 'Test message']],
            null,
            $this->agent
        );

        // Verify request parameters
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['model'])
                && isset($body['temperature'])
                && isset($body['max_tokens'])
                && is_array($body['messages'])
                && count($body['messages']) === 2; // system + user
        });
    }

    /** @test */
    public function call_llm_includes_tools_in_request_when_provided()
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Let me search for that.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 150,
                    'completion_tokens' => 15,
                    'total_tokens' => 165,
                ],
            ], 200),
        ]);

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_all_products',
                    'description' => 'Get all products',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
        ];

        $this->service->callLLM(
            'You are a helpful assistant.',
            [['type' => 'human', 'content' => 'Show me products']],
            $tools,
            $this->agent
        );

        // Verify tools are included
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['tools'])
                && is_array($body['tools'])
                && isset($body['tool_choice'])
                && $body['tool_choice'] === 'auto';
        });
    }
}
