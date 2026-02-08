<?php

namespace Tests\Unit;

use App\Enums\UserIntent;
use App\Models\AiAgent;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\AiAgentPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PromptCachingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected WhatsAppAccount $whatsappAccount;

    protected AiAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create WhatsApp account
        $this->whatsappAccount = WhatsAppAccount::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Create AI Agent with caching enabled
        $this->agent = AiAgent::factory()->create([
            'whatsapp_account_id' => $this->whatsappAccount->id,
            'bot_name' => 'Test Bot',
            'system_prompt' => 'You are a helpful assistant.',
            'order_enabled' => true,
            'use_optimized_prompt' => true,
            'enable_prompt_caching' => true,
        ]);
    }

    public function test_caching_enabled_uses_cache()
    {
        // Clear cache first
        Cache::flush();

        $builder = new AiAgentPromptBuilder($this->agent, $this->user->id);

        // First call - should cache
        $prompt1 = $builder->build();

        // Check cache exists
        $cacheKey = "ai_prompt_static_{$this->agent->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // Second call - should use cache
        $prompt2 = $builder->build();

        // Should be identical
        $this->assertEquals($prompt1, $prompt2);
    }

    public function test_caching_disabled_does_not_use_cache()
    {
        // Disable caching
        $this->agent->enable_prompt_caching = false;
        $this->agent->save();

        // Clear cache
        Cache::flush();

        $builder = new AiAgentPromptBuilder($this->agent, $this->user->id);
        $prompt = $builder->build();

        // Cache should not exist
        $cacheKey = "ai_prompt_static_{$this->agent->id}";
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_cache_improves_performance()
    {
        Cache::flush();

        $builder = new AiAgentPromptBuilder($this->agent, $this->user->id);

        // First call (cache miss)
        $start1 = microtime(true);
        $prompt1 = $builder->build();
        $time1 = (microtime(true) - $start1) * 1000;

        // Second call (cache hit)
        $start2 = microtime(true);
        $prompt2 = $builder->build();
        $time2 = (microtime(true) - $start2) * 1000;

        // Cache hit should be faster (or at least not slower)
        $this->assertLessThanOrEqual($time1, $time2 * 2); // Allow some variance
    }

    public function test_anthropic_caching_format()
    {
        $builder = new AiAgentPromptBuilder($this->agent, $this->user->id);
        $cachedPrompt = $builder->buildForAnthropicCaching();

        // Should return array
        $this->assertIsArray($cachedPrompt);

        // Should have 2 parts
        $this->assertCount(2, $cachedPrompt);

        // First part should have cache_control
        $this->assertArrayHasKey('cache_control', $cachedPrompt[0]);
        $this->assertEquals('ephemeral', $cachedPrompt[0]['cache_control']['type']);

        // Second part should not have cache_control
        $this->assertArrayNotHasKey('cache_control', $cachedPrompt[1]);
    }

    public function test_cache_invalidation()
    {
        Cache::flush();

        $builder = new AiAgentPromptBuilder($this->agent, $this->user->id);

        // Build prompt (creates cache)
        $prompt1 = $builder->build();

        $cacheKey = "ai_prompt_static_{$this->agent->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // Clear cache
        Cache::forget($cacheKey);

        // Cache should be gone
        $this->assertFalse(Cache::has($cacheKey));

        // Build again (recreates cache)
        $prompt2 = $builder->build();

        // Cache should exist again
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_cache_per_agent()
    {
        Cache::flush();

        // Create second agent
        $agent2 = AiAgent::factory()->create([
            'whatsapp_account_id' => $this->whatsappAccount->id,
            'bot_name' => 'Test Bot 2',
            'system_prompt' => 'Different prompt.',
            'enable_prompt_caching' => true,
        ]);

        // Build prompts for both agents
        $builder1 = new AiAgentPromptBuilder($this->agent, $this->user->id);
        $prompt1 = $builder1->build();

        $builder2 = new AiAgentPromptBuilder($agent2, $this->user->id);
        $prompt2 = $builder2->build();

        // Both should have separate cache keys
        $cacheKey1 = "ai_prompt_static_{$this->agent->id}";
        $cacheKey2 = "ai_prompt_static_{$agent2->id}";

        $this->assertTrue(Cache::has($cacheKey1));
        $this->assertTrue(Cache::has($cacheKey2));

        // Prompts should be different
        $this->assertNotEquals($prompt1, $prompt2);
    }

    public function test_cache_with_different_intents()
    {
        Cache::flush();

        // Build with different intents
        $builder1 = new AiAgentPromptBuilder($this->agent, $this->user->id, UserIntent::GREETING);
        $prompt1 = $builder1->build();

        $builder2 = new AiAgentPromptBuilder($this->agent, $this->user->id, UserIntent::ORDER);
        $prompt2 = $builder2->build();

        // Static part should be cached (same for both)
        $cacheKey = "ai_prompt_static_{$this->agent->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // But full prompts should be different (different dynamic parts)
        $this->assertNotEquals($prompt1, $prompt2);
    }
}
