<?php
/**
 * Test Webhook Locally - Full Simulation
 * 
 * Simulates a WhatsApp webhook to test the full flow locally.
 * This bypasses the need for actual WhatsApp webhook.
 * 
 * Usage: php test_webhook_local.php [message]
 * Example: php test_webhook_local.php "Halo"
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AntiSpamWorkflow;
use App\Services\AiAgentService;

echo "=== Local Webhook Test (Full Simulation) ===\n\n";

// Get message from command line or use default
$testMessage = $argv[1] ?? 'Halo test ' . date('H:i:s');

// Get the WhatsApp account
$account = WhatsAppAccount::withoutGlobalScopes()->where('phone_number_id', '!=', null)->first();

if (!$account) {
    echo "❌ No WhatsApp account found!\n";
    exit(1);
}

echo "✅ Found WhatsApp Account: {$account->id} (Phone: {$account->phone_number_id})\n";

// Get or create a test contact
$testPhoneNumber = '6288802597405'; // Your test phone number
$contact = WhatsAppContact::withoutGlobalScopes()->firstOrCreate(
    [
        'user_id' => $account->user_id,
        'phone_number_id' => $account->phone_number_id,
        'wa_id' => $testPhoneNumber,
    ],
    [
        'name' => 'Test User',
    ]
);

echo "✅ Found/Created Contact: {$contact->id} (WA ID: {$contact->wa_id})\n\n";

// Clear any existing buffer for this phone number
$bufferKey = "msg_buffer:{$testPhoneNumber}";
$lockKey = "msg_debounce:{$testPhoneNumber}";
Cache::forget($bufferKey);
Cache::forget($lockKey);
echo "✅ Cleared existing buffer and lock\n\n";

// Test the AntiSpamWorkflow directly
$antiSpamWorkflow = app(AntiSpamWorkflow::class);

$testMessageId = 'wamid_test_' . time();

echo "📤 Sending test message: '{$testMessage}'\n";
echo "   Message ID: {$testMessageId}\n\n";

$shouldProcess = $antiSpamWorkflow->validate(
    $account,
    $contact,
    $testMessage,
    $testMessageId
);

echo "📋 AntiSpamWorkflow result:\n";
echo "   Should process immediately: " . ($shouldProcess ? 'YES' : 'NO (buffered)') . "\n\n";

// Check buffer status
$bufferContent = Cache::get($bufferKey, []);
$hasLock = Cache::has($lockKey);

echo "📦 Buffer Status:\n";
echo "   Buffer content: " . json_encode($bufferContent) . "\n";
echo "   Buffer size: " . count($bufferContent) . "\n";
echo "   Debounce lock exists: " . ($hasLock ? 'YES' : 'NO') . "\n\n";

// Check if job was dispatched
$jobs = DB::table('jobs')->where('queue', 'ai-agent')->get();
echo "📋 Jobs in 'ai-agent' queue: " . count($jobs) . "\n";

foreach ($jobs as $job) {
    $payload = json_decode($job->payload, true);
    $displayName = $payload['displayName'] ?? 'Unknown';
    $availableAt = date('Y-m-d H:i:s', $job->available_at);
    $now = date('Y-m-d H:i:s');
    echo "   - Job: {$displayName}\n";
    echo "     Available at: {$availableAt}\n";
    echo "     Current time: {$now}\n";
}

// Option to process immediately without queue
echo "\n";
$processNow = readline("Process message immediately without queue? (y/n): ");

if (strtolower($processNow) === 'y') {
    echo "\n🚀 Processing message directly...\n\n";
    
    // Flush buffer and process
    $messageBuffer = app(\App\Services\MessageBuffer::class);
    $messages = $messageBuffer->flush($testPhoneNumber);
    
    if (empty($messages)) {
        // If buffer was empty, use the test message directly
        $messages = [$testMessage];
    }
    
    $mergedMessage = $messageBuffer->mergeMessages($messages);
    
    echo "📝 Merged message: '{$mergedMessage}'\n\n";
    
    // Process through AI Agent
    $aiAgentService = app(AiAgentService::class);
    
    try {
        $aiAgentService->processMessage($account, $contact, $mergedMessage);
        echo "✅ Message processed successfully!\n";
        echo "   Check the logs for LLM response.\n";
    } catch (\Exception $e) {
        echo "❌ Error processing message: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n=== Instructions ===\n";
    echo "1. Make sure queue worker is running:\n";
    echo "   php artisan queue:work --queue=ai-agent\n\n";
    echo "2. Wait for the debounce period (2 seconds) for the job to be processed\n";
    echo "3. Check the logs for 'Processing buffered messages' and 'LLM API Response'\n";
}

echo "\n=== Done ===\n";
