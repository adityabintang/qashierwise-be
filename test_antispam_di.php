<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AntiSpamWorkflow;

echo "Testing AntiSpamWorkflow DI...\n";

try {
    $workflow = app(AntiSpamWorkflow::class);
    echo "✅ AntiSpamWorkflow instantiated successfully\n";
    
    // Check if MessageBuffer is injected
    $reflection = new ReflectionClass($workflow);
    $property = $reflection->getProperty('messageBuffer');
    $property->setAccessible(true);
    $buffer = $property->getValue($workflow);
    
    if ($buffer) {
        echo "✅ MessageBuffer is injected: " . get_class($buffer) . "\n";
    } else {
        echo "❌ MessageBuffer is NULL\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
