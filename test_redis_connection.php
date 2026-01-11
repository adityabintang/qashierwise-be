<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing Redis Connection...\n";
echo "Host: " . config('database.redis.default.host') . "\n";
echo "Port: " . config('database.redis.default.port') . "\n";
echo "Client: " . config('database.redis.client') . "\n\n";

try {
    $result = \Illuminate\Support\Facades\Redis::ping();
    echo "✓ Redis PING: " . ($result ? 'PONG' : $result) . "\n";
    
    // Test set/get
    \Illuminate\Support\Facades\Redis::set('test_key', 'Hello from Laravel!');
    $value = \Illuminate\Support\Facades\Redis::get('test_key');
    echo "✓ Redis SET/GET: " . $value . "\n";
    
    // Cleanup
    \Illuminate\Support\Facades\Redis::del('test_key');
    echo "✓ Redis DEL: OK\n";
    
    echo "\n✅ Redis connection successful!\n";
} catch (\Exception $e) {
    echo "❌ Redis Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
