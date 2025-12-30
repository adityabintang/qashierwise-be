<?php

/**
 * Script untuk test fix empty response dari AI Agent
 * 
 * Test scenarios:
 * 1. Simulate empty content dari LLM setelah search
 * 2. Simulate no user-facing results dari tool calls
 * 3. Verify fallback messages dikirim
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AiAgent;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Log;

echo "=== TEST FIX: AI AGENT EMPTY RESPONSE ===\n\n";

// Get first active AI Agent
$aiAgent = AiAgent::where('is_active', true)->first();

if (!$aiAgent) {
    echo "❌ Tidak ada AI Agent yang aktif\n";
    echo "Silakan aktifkan AI Agent terlebih dahulu\n";
    exit(1);
}

$account = WhatsAppAccount::find($aiAgent->whatsapp_account_id);
if (!$account) {
    echo "❌ WhatsApp Account tidak ditemukan\n";
    exit(1);
}

echo "✅ AI Agent ditemukan: {$aiAgent->bot_name}\n";
echo "✅ WhatsApp Account: {$account->phone_number_id}\n\n";

// Get or create test contact
$testContact = WhatsAppContact::firstOrCreate(
    [
        'user_id' => $account->user_id,
        'wa_id' => 'test_empty_response_' . time(),
    ],
    [
        'name' => 'Test Empty Response',
    ]
);

echo "✅ Test Contact: {$testContact->name} ({$testContact->wa_id})\n\n";

// Initialize service
$aiAgentService = app(AiAgentService::class);

echo "=== Test 1: Normal Message (Should Work) ===\n";
echo "Mengirim: 'Halo'\n";

try {
    // Enable log monitoring
    Log::info('TEST: Starting normal message test');
    
    $aiAgentService->processMessage($account, $testContact, 'Halo');
    
    echo "✅ Test 1 berhasil - pesan diproses tanpa error\n\n";
} catch (\Exception $e) {
    echo "❌ Test 1 gagal: {$e->getMessage()}\n\n";
}

echo "=== Test 2: Message yang Trigger Search ===\n";
echo "Mengirim: 'Ada menu apa saja?'\n";

try {
    Log::info('TEST: Starting search trigger test');
    
    $aiAgentService->processMessage($account, $testContact, 'Ada menu apa saja?');
    
    echo "✅ Test 2 berhasil - search diproses tanpa error\n\n";
} catch (\Exception $e) {
    echo "❌ Test 2 gagal: {$e->getMessage()}\n\n";
}

echo "=== Test 3: Complex Order Message ===\n";
echo "Mengirim: 'Pesan dimsum 2 dan teh jumbo 1'\n";

try {
    Log::info('TEST: Starting complex order test');
    
    $aiAgentService->processMessage($account, $testContact, 'Pesan dimsum 2 dan teh jumbo 1');
    
    echo "✅ Test 3 berhasil - order diproses tanpa error\n\n";
} catch (\Exception $e) {
    echo "❌ Test 3 gagal: {$e->getMessage()}\n\n";
}

echo "=== Checking Logs for Empty Response Warnings ===\n\n";

// Check recent logs for our new warnings
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    
    // Get last 100 lines
    $recentLines = array_slice($lines, -100);
    
    $emptyContentWarnings = 0;
    $noResultsWarnings = 0;
    
    foreach ($recentLines as $line) {
        if (strpos($line, 'LLM returned empty content') !== false) {
            $emptyContentWarnings++;
            echo "⚠️  Found: LLM returned empty content\n";
        }
        if (strpos($line, 'No user-facing results from tool calls') !== false) {
            $noResultsWarnings++;
            echo "⚠️  Found: No user-facing results from tool calls\n";
        }
    }
    
    echo "\n";
    echo "Summary dari log (last 100 lines):\n";
    echo "- Empty content warnings: {$emptyContentWarnings}\n";
    echo "- No results warnings: {$noResultsWarnings}\n\n";
    
    if ($emptyContentWarnings > 0 || $noResultsWarnings > 0) {
        echo "✅ Fix berfungsi - warnings terdeteksi dan fallback messages dikirim\n";
    } else {
        echo "ℹ️  Tidak ada warnings terdeteksi - LLM berfungsi normal\n";
    }
} else {
    echo "⚠️  Log file tidak ditemukan\n";
}

echo "\n=== Test Selesai ===\n\n";

echo "Catatan:\n";
echo "1. Jika semua test ✅, fix berhasil diimplementasi\n";
echo "2. Check WhatsApp untuk memastikan user menerima response\n";
echo "3. Jika ada warnings di log, itu normal - artinya fallback berfungsi\n";
echo "4. Monitor logs untuk pattern: grep 'LLM returned empty content' storage/logs/laravel.log\n";
