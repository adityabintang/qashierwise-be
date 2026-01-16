<?php

/**
 * Script to regenerate flow JSON for existing flows.
 * Run this after updating the flow structure to fix publish issues.
 * 
 * Usage: php regenerate_existing_flow.php [user_id]
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ReservationFlowConfig;
use App\Services\WhatsAppFlowService;
use App\Services\WhatsAppAccountService;

$userId = $argv[1] ?? 1;

echo "=== Regenerate WhatsApp Flow JSON ===\n\n";

try {
    $config = ReservationFlowConfig::where('user_id', $userId)->first();
    
    if (!$config) {
        echo "No flow config found for user {$userId}\n";
        exit(1);
    }
    
    echo "User ID: {$userId}\n";
    echo "Flow ID: " . ($config->flow_id ?? 'Not created') . "\n";
    echo "Flow Status: {$config->flow_status}\n";
    echo "Flow Name: {$config->flow_name}\n\n";
    
    if (!$config->flow_id) {
        echo "No flow created yet. Please create a flow first from the dashboard.\n";
        exit(1);
    }
    
    $flowService = app(WhatsAppFlowService::class);
    
    echo "Regenerating flow JSON...\n";
    
    $result = $flowService->updateFlowJson($userId, $config->flow_id, $config);
    
    if ($result) {
        echo "\n✅ Flow JSON updated successfully!\n";
        echo "\nNext steps:\n";
        echo "1. Go to Meta Business Suite > WhatsApp > Flows\n";
        echo "2. Find your flow: {$config->flow_name}\n";
        echo "3. Click 'Jalankan pemeriksaan kesehatan' (Run health check)\n";
        echo "4. If health check passes, click 'Terbitkan' (Publish)\n";
    } else {
        echo "\n❌ Failed to update flow JSON\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
