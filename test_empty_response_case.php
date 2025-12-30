<?php

/**
 * Test case untuk empty response issue
 * Mensimulasikan: "oke beli teh jumbonya dua"
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\AiAgent;
use App\Services\AiAgentService;

echo "=== TEST: Empty Response Case ===\n\n";

// Get first WhatsApp account
$account = WhatsAppAccount::first();
if (!$account) {
    echo "❌ No WhatsApp account found\n";
    exit(1);
}

// Get or create test contact
$contact = WhatsAppContact::firstOrCreate(
    [
        'user_id' => $account->user_id,
        'wa_id' => '6281234567890',
    ],
    [
        'name' => 'Test User',
    ]
);

// Get AI Agent
$aiAgent = AiAgent::where('whatsapp_account_id', $account->id)
    ->where('is_active', true)
    ->first();

if (!$aiAgent) {
    echo "❌ No active AI Agent found\n";
    exit(1);
}

echo "✅ Setup complete\n";
echo "   Account: {$account->phone_number}\n";
echo "   Contact: {$contact->name} ({$contact->wa_id})\n";
echo "   AI Agent: {$aiAgent->name}\n\n";

// Get AI Agent Service
$service = app(AiAgentService::class);

// Test messages that caused empty response
$testMessages = [
    "oke beli teh jumbonya dua",
    "oke beli teh jumbonya 2",
    "pesan dimsum 1",
    "mau pesan nasi goreng",
];

foreach ($testMessages as $index => $message) {
    echo "=== Test " . ($index + 1) . " ===\n";
    echo "User: {$message}\n";
    
    try {
        // Process message
        $service->processMessage($account, $contact, $message);
        
        // Get conversation to check response
        $conversation = $service->getOrCreateConversation($aiAgent->id, $contact->id);
        $messages = $conversation->messages ?? [];
        
        // Get last AI message
        $lastAiMessage = null;
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (isset($messages[$i]['type']) && $messages[$i]['type'] === 'ai') {
                $lastAiMessage = $messages[$i]['content'] ?? '';
                break;
            }
        }
        
        if ($lastAiMessage === null) {
            echo "❌ No AI response found\n";
        } elseif (empty(trim($lastAiMessage))) {
            echo "❌ EMPTY RESPONSE DETECTED!\n";
            echo "   Raw content: '" . $lastAiMessage . "'\n";
            echo "   Length: " . strlen($lastAiMessage) . "\n";
        } else {
            echo "✅ AI Response: " . substr($lastAiMessage, 0, 100);
            if (strlen($lastAiMessage) > 100) {
                echo "...";
            }
            echo "\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ Error: {$e->getMessage()}\n";
    }
    
    echo "\n";
    
    // Wait a bit between tests
    sleep(2);
}

echo "=== Test Complete ===\n";
echo "\nCheck logs for details:\n";
echo "  tail -f storage/logs/laravel.log | grep 'empty content'\n";
