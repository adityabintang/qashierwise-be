<?php

namespace Tests\Unit;

use App\Enums\UserIntent;
use App\Models\AiAgent;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\AiAgentPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected WhatsAppAccount $whatsappAccount;

    protected AiAgent $agent;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create WhatsApp account
        $this->whatsappAccount = WhatsAppAccount::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Create Store
        $this->store = Store::create([
            'user_id' => $this->user->id,
            'name' => 'Test Store',
            'code' => 'TEST001',
        ]);

        // Create AI Agent with store (for order_enabled to work)
        $this->agent = AiAgent::factory()->create([
            'whatsapp_account_id' => $this->whatsappAccount->id,
            'bot_name' => 'Test Bot',
            'system_prompt' => 'You are a helpful assistant.',
            'order_enabled' => true,
            'default_store_id' => $this->store->id,
            'use_optimized_prompt' => true,
        ]);
    }

    public function test_basic_prompt_generation()
    {
        $builder = new AiAgentPromptBuilder($this->agent, $this->user->id);
        $prompt = $builder->build();

        // Check for new optimized prompt format
        $this->assertStringContainsString('ATURAN', $prompt);
        $this->assertStringContainsString('Test Bot', $prompt);
    }

    public function test_prompt_excludes_ordering_when_disabled()
    {
        $this->agent->order_enabled = false;
        $this->agent->save();

        $builder = new AiAgentPromptBuilder($this->agent, $this->user->id);
        $prompt = $builder->build();

        $this->assertStringNotContainsString('Alur Pesan', $prompt);
        $this->assertStringNotContainsString('add_to_cart', $prompt);
    }

    public function test_intent_based_prompt_optimization()
    {
        $this->agent->business_info = [
            'operating_hours' => '09:00 - 21:00',
        ];
        $this->agent->save();

        // Greeting intent - should include business info
        $builder = new AiAgentPromptBuilder($this->agent, $this->user->id, UserIntent::GREETING);
        $prompt = $builder->build();
        $this->assertStringContainsString('Jam', $prompt);

        // Order intent - should include workflow
        $builder = new AiAgentPromptBuilder($this->agent, $this->user->id, UserIntent::ORDER);
        $prompt = $builder->build();
        $this->assertStringContainsString('Alur Pesan', $prompt);
    }

    public function test_prompt_token_reduction()
    {
        $fullPrompt = $this->agent->buildSystemPrompt($this->user->id);
        $optimizedPrompt = (new AiAgentPromptBuilder($this->agent, $this->user->id, UserIntent::GREETING))->build();

        // Optimized should be shorter or equal
        $this->assertLessThanOrEqual(
            strlen($fullPrompt),
            strlen($optimizedPrompt)
        );
    }

    public function test_few_shot_examples()
    {
        $examples = AiAgentPromptBuilder::getFewShotExamples();

        $this->assertIsArray($examples);
        $this->assertNotEmpty($examples);
        $this->assertArrayHasKey('role', $examples[0]);
        $this->assertArrayHasKey('content', $examples[0]);
    }
}
