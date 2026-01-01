<?php

/**
 * Direct test untuk verify limit 10 pada getAllProducts
 * Akses via: http://your-domain/test_ai_agent_limit.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AiAgent;
use App\Services\AiAgentService;

header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST AI AGENT PRODUCT LIMIT ===\n\n";

// Test dengan user_id dari logs
$userId = 1;

// Get AI Agent
$aiAgent = AiAgent::where('user_id', $userId)->first();

if (! $aiAgent) {
    echo "❌ AI Agent tidak ditemukan untuk user_id: {$userId}\n";
    exit(1);
}

echo "✅ AI Agent ditemukan: {$aiAgent->agent_name}\n";
echo '   use_toon_format: '.($aiAgent->use_toon_format ? 'true' : 'false')."\n\n";

// Test getAllProducts dengan TOON format
$aiAgentService = new AiAgentService;

// Use reflection to call protected method
$reflection = new ReflectionClass($aiAgentService);
$method = $reflection->getMethod('getAllProducts');
$method->setAccessible(true);

echo "=== Testing getAllProducts (TOON Format) ===\n";
$result = $method->invoke($aiAgentService, $userId, true);
echo $result."\n\n";

echo "=== Testing getAllProducts (Legacy Format) ===\n";
$result = $method->invoke($aiAgentService, $userId, false);
echo $result."\n\n";

echo "✅ Test selesai!\n";
echo "Jika masih menampilkan 16 produk, restart web server Anda (Apache/Nginx/XAMPP)\n";
