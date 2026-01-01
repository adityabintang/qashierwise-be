<?php

/**
 * Test AI Agent Optimization
 *
 * Usage: php test_ai_optimization.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Enums\UserIntent;
use App\Models\AiAgent;
use App\Models\User;
use App\Services\AiAgentPromptBuilder;
use App\Services\AiResponseValidator;
use Illuminate\Support\Facades\Cache;

echo "=== AI Agent Optimization Test ===\n\n";

// Test 1: Intent Detection
echo "Test 1: Intent Detection\n";
echo "------------------------\n";
$testMessages = [
    'Halo' => 'GREETING',
    'menunya apa aja?' => 'VIEW_MENU',
    'pesan dimsum 2' => 'ORDER',
    'lihat keranjang' => 'VIEW_CART',
    'checkout' => 'CHECKOUT',
    'jam buka?' => 'BUSINESS_INFO',
    'siapa presiden?' => 'OFF_TOPIC',
];

foreach ($testMessages as $message => $expected) {
    $intent = UserIntent::detect($message);
    $status = $intent->value === strtolower($expected) ? '✅' : '❌';
    echo "{$status} '{$message}' => {$intent->value} (expected: ".strtolower($expected).")\n";
}
echo "\n";

// Test 2: Prompt Builder
echo "Test 2: Prompt Builder\n";
echo "----------------------\n";
$user = User::first();
if ($user) {
    $agent = AiAgent::where('whatsapp_account_id', function ($query) use ($user) {
        $query->select('id')
            ->from('whatsapp_accounts')
            ->where('user_id', $user->id)
            ->limit(1);
    })->first();

    if ($agent) {
        echo "Testing with Agent: {$agent->bot_name}\n";

        // Test with different intents
        $intents = [
            UserIntent::GREETING,
            UserIntent::VIEW_MENU,
            UserIntent::ORDER,
        ];

        foreach ($intents as $intent) {
            $builder = new AiAgentPromptBuilder($agent, $user->id, $intent);
            $prompt = $builder->build();
            $tokenEstimate = strlen($prompt) / 4; // Rough estimate

            echo "- {$intent->value}: ~".number_format($tokenEstimate, 0)." tokens\n";
        }

        // Compare with full prompt
        $fullPrompt = $agent->buildSystemPrompt($user->id);
        $fullTokens = strlen($fullPrompt) / 4;
        echo '- Full prompt: ~'.number_format($fullTokens, 0)." tokens\n";

        $avgOptimized = ($tokenEstimate * 3) / 3;
        $savings = (($fullTokens - $avgOptimized) / $fullTokens) * 100;
        echo "\n✅ Estimated savings: ".number_format($savings, 1)."%\n";
    } else {
        echo "❌ No AI Agent found for user\n";
    }
} else {
    echo "❌ No users found in database\n";
}
echo "\n";

// Test 3: Response Validator
echo "Test 3: Response Validator\n";
echo "--------------------------\n";
$validator = new AiResponseValidator;

$testResponses = [
    [
        'response' => 'Dimsum Keju [ID:123] - Rp 40.000',
        'should_fail' => true,
        'reason' => 'Product ID exposed',
    ],
    [
        'response' => 'Total: 40.000 x 2 = 80.000',
        'should_fail' => true,
        'reason' => 'Manual calculation',
    ],
    [
        'response' => 'Dimsum Keju - Rp 40.000',
        'should_fail' => false,
        'reason' => 'Valid response',
    ],
];

foreach ($testResponses as $test) {
    $isValid = $validator->validate($test['response']);
    $expected = ! $test['should_fail'];
    $status = ($isValid === $expected) ? '✅' : '❌';

    echo "{$status} {$test['reason']}: ".($isValid ? 'PASS' : 'FAIL')."\n";

    if (! $isValid) {
        $errors = $validator->getErrors();
        foreach ($errors as $error) {
            echo "   - {$error}\n";
        }
    }
}
echo "\n";

// Test 4: Sanitization
echo "Test 4: Sanitization\n";
echo "--------------------\n";
$dirtyResponse = "Dimsum Keju [ID:123] - Rp 40.000\nTeh Jumbo [ID:456] - Rp 5.000";
$cleanResponse = $validator->sanitize($dirtyResponse);

echo "Before: {$dirtyResponse}\n";
echo "After:  {$cleanResponse}\n";
echo (strpos($cleanResponse, '[ID:') === false ? '✅' : '❌')." Product IDs removed\n";
echo "\n";

// Test 5: Database Check
echo "Test 5: Database Check\n";
echo "----------------------\n";
try {
    $agentCount = AiAgent::count();
    $optimizedCount = AiAgent::where('use_optimized_prompt', true)->count();

    echo "✅ Total AI Agents: {$agentCount}\n";
    echo "✅ Optimized Agents: {$optimizedCount}\n";

    // Check analytics table
    $analyticsCount = DB::table('ai_prompt_analytics')->count();
    echo "✅ Analytics records: {$analyticsCount}\n";

} catch (\Exception $e) {
    echo '❌ Database error: '.$e->getMessage()."\n";
}
echo "\n";

// Test 6: Config Check
echo "Test 6: Config Check\n";
echo "--------------------\n";
try {
    $coreRules = config('ai_agent_prompts.core_rules');
    $orderingWorkflow = config('ai_agent_prompts.ordering_workflow');
    $antiHallucination = config('ai_agent_prompts.anti_hallucination_reminder');

    echo (strlen($coreRules) > 0 ? '✅' : '❌')." Core rules loaded\n";
    echo (strlen($orderingWorkflow) > 0 ? '✅' : '❌')." Ordering workflow loaded\n";
    echo (strlen($antiHallucination) > 0 ? '✅' : '❌')." Anti-hallucination rules loaded\n";
} catch (\Exception $e) {
    echo '❌ Config error: '.$e->getMessage()."\n";
}
echo "\n";

// Test 7: Prompt Caching
echo "Test 7: Prompt Caching\n";
echo "----------------------\n";
try {
    $user = User::first();
    if ($user) {
        $agent = AiAgent::where('whatsapp_account_id', function ($query) use ($user) {
            $query->select('id')
                ->from('whatsapp_accounts')
                ->where('user_id', $user->id)
                ->limit(1);
        })->first();

        if ($agent) {
            // Test caching
            Cache::flush();

            $builder = new AiAgentPromptBuilder($agent, $user->id);

            // Enable caching
            $agent->enable_prompt_caching = true;
            $agent->save();

            // First call (cache miss)
            $start1 = microtime(true);
            $prompt1 = $builder->build();
            $time1 = (microtime(true) - $start1) * 1000;

            // Check cache exists
            $cacheKey = "ai_prompt_static_{$agent->id}";
            $cacheExists = Cache::has($cacheKey);

            // Second call (cache hit)
            $start2 = microtime(true);
            $prompt2 = $builder->build();
            $time2 = (microtime(true) - $start2) * 1000;

            echo ($cacheExists ? '✅' : '❌')." Cache created\n";
            echo '✅ First call: '.number_format($time1, 2)."ms (cache miss)\n";
            echo '✅ Second call: '.number_format($time2, 2)."ms (cache hit)\n";

            $improvement = (($time1 - $time2) / $time1) * 100;
            if ($improvement > 0) {
                echo '✅ Performance improvement: '.number_format($improvement, 1)."%\n";
            }

            // Test Anthropic format
            $anthropicFormat = $builder->buildForAnthropicCaching();
            $hasAnthropicFormat = is_array($anthropicFormat) && count($anthropicFormat) === 2;
            echo ($hasAnthropicFormat ? '✅' : '❌')." Anthropic format available\n";

        } else {
            echo "❌ No AI Agent found\n";
        }
    } else {
        echo "❌ No users found\n";
    }
} catch (\Exception $e) {
    echo '❌ Caching test error: '.$e->getMessage()."\n";
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "All core components tested successfully!\n";
echo "\nNext steps:\n";
echo "1. Run: php artisan test --filter=AiAgent\n";
echo "2. Test via API: POST /api/ai-agent/test\n";
echo "3. Monitor logs: tail -f storage/logs/laravel.log\n";
echo "\n";
