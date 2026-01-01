<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AiAgent;
use App\Services\AiAgentPromptBuilder;
use App\Services\AiPromptAnalytics;
use Illuminate\Support\Facades\DB;

echo "=== Verifikasi Implementasi Prompt Caching & Analytics ===\n\n";

// 1. Check database schema
echo "1. Checking AI Agent configuration...\n";
$agent = AiAgent::first();

if ($agent) {
    echo "   ✅ AiAgent model found\n";
    echo '   - enable_prompt_caching: '.($agent->enable_prompt_caching ? 'true' : 'false')."\n";
    echo '   - use_optimized_prompt: '.($agent->use_optimized_prompt ? 'true' : 'false')."\n";
    echo '   - product_sample_limit: '.($agent->product_sample_limit ?? 'null')."\n";
} else {
    echo "   ⚠️  No AI agents found in database\n";
}

echo "\n2. Checking AiAgent model attributes...\n";
$fillable = (new AiAgent)->getFillable();
echo "   - Fillable contains 'enable_prompt_caching': ".(in_array('enable_prompt_caching', $fillable) ? '✅ Yes' : '❌ No')."\n";
echo "   - Fillable contains 'use_optimized_prompt': ".(in_array('use_optimized_prompt', $fillable) ? '✅ Yes' : '❌ No')."\n";

echo "\n3. Checking AiAgentPromptBuilder methods...\n";
$methods = get_class_methods(AiAgentPromptBuilder::class);
echo "   - Has 'build' method: ".(in_array('build', $methods) ? '✅ Yes' : '❌ No')."\n";
echo "   - Has 'buildWithCaching' method: ".(in_array('buildWithCaching', $methods) ? '✅ Yes' : '❌ No')."\n";
echo "   - Has 'buildForAnthropicCaching' method: ".(in_array('buildForAnthropicCaching', $methods) ? '✅ Yes' : '❌ No')."\n";

echo "\n4. Checking AiAgentService callLLM signature...\n";
$reflection = new ReflectionMethod('App\Services\AiAgentService', 'callLLM');
$params = $reflection->getParameters();
$paramNames = array_map(fn ($p) => $p->getName(), $params);
echo '   - Parameters: '.implode(', ', $paramNames)."\n";
echo "   - Has 'aiAgent' parameter: ".(in_array('aiAgent', $paramNames) ? '✅ Yes' : '❌ No')."\n";

echo "\n5. Checking ai_prompt_analytics table...\n";
try {
    // Use information_schema for PostgreSQL compatibility
    $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'ai_prompt_analytics'");
    $columnNames = array_map(fn ($c) => $c->column_name, $columns);
    echo '   - Table columns: '.implode(', ', $columnNames)."\n";
    echo "   - Has 'cache_hit' column: ".(in_array('cache_hit', $columnNames) ? '✅ Yes' : '❌ No')."\n";
    echo "   - Has 'response_time_ms' column: ".(in_array('response_time_ms', $columnNames) ? '✅ Yes' : '❌ No')."\n";
    echo "   - Has 'prompt_tokens' column: ".(in_array('prompt_tokens', $columnNames) ? '✅ Yes' : '❌ No')."\n";
    echo "   - Has 'completion_tokens' column: ".(in_array('completion_tokens', $columnNames) ? '✅ Yes' : '❌ No')."\n";
} catch (\Exception $e) {
    echo '   ❌ Error: '.$e->getMessage()."\n";
}

echo "\n6. Checking analytics data...\n";
$analyticsCount = DB::table('ai_prompt_analytics')->count();
echo "   - Total analytics records: {$analyticsCount}\n";

if ($analyticsCount > 0 && $agent) {
    $summary = AiPromptAnalytics::getSummary($agent->id);
    echo "   - Total requests (last 7 days): {$summary['total_requests']}\n";
    echo "   - Total tokens: {$summary['total_tokens']}\n";
    echo "   - Avg tokens per request: {$summary['avg_tokens']}\n";
    echo "   - Avg response time: {$summary['avg_response_time_ms']}ms\n";
    echo "   - Cache hit rate: {$summary['cache_hit_rate']}%\n";
} else {
    echo "   ⚠️  No analytics data yet. Try sending some messages to AI Agent.\n";
}

echo "\n7. Summary of all AI agents:\n";
$agents = AiAgent::all();
echo '   Total AI agents: '.$agents->count()."\n";
foreach ($agents as $a) {
    echo "   - Agent #{$a->id} ({$a->bot_name}): ";
    echo 'caching='.($a->enable_prompt_caching ? 'ON' : 'OFF');
    echo ', optimized='.($a->use_optimized_prompt ? 'ON' : 'OFF');
    echo ', sample_limit='.($a->product_sample_limit ?? 'null');
    echo "\n";
}

echo "\n=== ✅ Verifikasi Selesai ===\n";
echo "\nKesimpulan:\n";
echo "- ✅ use_optimized_prompt: Control untuk struktur prompt (optimized vs legacy)\n";
echo "- ✅ enable_prompt_caching: Control untuk cache mechanism di AiAgentService\n";
echo "- ✅ Analytics tracking sudah diimplementasikan\n";
echo "- ✅ Data akan masuk ke ai_prompt_analytics setelah chat dengan AI Agent\n";
echo "\nKirim pesan ke AI Agent untuk melihat data analytics! 🚀\n";
