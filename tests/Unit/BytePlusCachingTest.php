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
    public function call_llm_uses_responses_api_when_caching_enabled()
    {
        // Mock the HTTP response for Responses API
        Http::fake([
            '*/responses' => Http::response([
                'id' => 'resp_new_123',
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'Hello! How can I help you?',
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 100,
                    'output_tokens' => 20,
                    'total_tokens' => 120,
                    'input_tokens_details' => [
                        'cached_tokens' => 0,
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->callLLM(
            'You are a helpful assistant.',
            [['type' => 'human', 'content' => 'Hello']],
            null,
            $this->agent,
            $this->conversation
        );

        $this->assertArrayHasKey('content', $result);
        $this->assertEquals('Hello! How can I help you?', $result['content']);

        // Verify cache response ID was stored
        $this->conversation->refresh();
        $this->assertEquals('resp_new_123', $this->conversation->cache_response_id);

        // Verify Responses API was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/responses');
        });
    }

    /** @test */
    public function call_llm_uses_previous_response_id_for_subsequent_requests()
    {
        // Set up existing cache
        $this->conversation->setCacheResponseId('resp_existing_123');

        Http::fake([
            '*/responses' => Http::response([
                'id' => 'resp_updated_456',
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'I can help with that!',
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 150,
                    'output_tokens' => 25,
                    'total_tokens' => 175,
                    'input_tokens_details' => [
                        'cached_tokens' => 100, // Cache hit!
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->callLLM(
            'You are a helpful assistant.',
            [['type' => 'human', 'content' => 'Can you help me?']],
            null,
            $this->agent,
            $this->conversation
        );

        // Verify previous_response_id was sent
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['previous_response_id'])
                && $body['previous_response_id'] === 'resp_existing_123';
        });

        // Verify cache was updated with new response ID
        $this->conversation->refresh();
        $this->assertEquals('resp_updated_456', $this->conversation->cache_response_id);
    }

    /** @test */
    public function call_llm_falls_back_to_chat_completions_when_caching_disabled()
    {
        // Disable caching
        $this->agent->enable_prompt_caching = false;
        $this->agent->save();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello from chat completions!',
                        ],
                    ],
                ],
                'usage' => [
                    'total_tokens' => 50,
                ],
            ], 200),
        ]);

        $result = $this->service->callLLM(
            'You are a helpful assistant.',
            [['type' => 'human', 'content' => 'Hello']],
            null,
            $this->agent,
            $this->conversation
        );

        $this->assertEquals('Hello from chat completions!', $result['content']);

        // Verify Chat Completions API was called, not Responses API
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/chat/completions');
        });
    }

    /** @test */
    public function call_llm_handles_tool_calls_from_responses_api()
    {
        Http::fake([
            '*/responses' => Http::response([
                'id' => 'resp_tool_123',
                'output' => [
                    [
                        'type' => 'function_call',
                        'call_id' => 'call_123',
                        'name' => 'search_products',
                        'arguments' => '{"query": "dimsum"}',
                    ],
                ],
                'usage' => [
                    'input_tokens' => 200,
                    'output_tokens' => 30,
                    'total_tokens' => 230,
                    'input_tokens_details' => [
                        'cached_tokens' => 150,
                    ],
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
            $this->agent,
            $this->conversation
        );

        $this->assertArrayHasKey('tool_calls', $result);
        $this->assertCount(1, $result['tool_calls']);
        $this->assertEquals('search_products', $result['tool_calls'][0]['function']['name']);
    }

    /** @test */
    public function first_request_includes_prefix_caching_flag()
    {
        // Ensure no existing cache
        $this->conversation->clearCacheResponseId();

        Http::fake([
            '*/responses' => Http::response([
                'id' => 'resp_first_123',
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            ['type' => 'output_text', 'text' => 'Hello!'],
                        ],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 100,
                    'output_tokens' => 10,
                    'total_tokens' => 110,
                    'input_tokens_details' => ['cached_tokens' => 0],
                ],
            ], 200),
        ]);

        $this->service->callLLM(
            'You are a helpful assistant.',
            [['type' => 'human', 'content' => 'Hello']],
            null,
            $this->agent,
            $this->conversation
        );

        // Verify first request has prefix: true
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['caching']['prefix'])
                && $body['caching']['prefix'] === true;
        });
    }
}
