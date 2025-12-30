<?php

/**
 * Script untuk test memory AI Agent
 * 
 * Cara pakai:
 * php test_ai_memory.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AiAgentConversation;
use App\Models\AiAgent;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;

echo "=== TEST AI AGENT MEMORY ===\n\n";

// 1. Cek apakah ada AI Agent yang aktif
$aiAgent = AiAgent::where('is_active', true)->first();

if (!$aiAgent) {
    echo "❌ Tidak ada AI Agent yang aktif\n";
    echo "Silakan aktifkan AI Agent terlebih dahulu di dashboard\n";
    exit(1);
}

echo "✅ AI Agent ditemukan: {$aiAgent->bot_name}\n";
echo "   WhatsApp Account ID: {$aiAgent->whatsapp_account_id}\n\n";

// 2. Cek conversation yang ada
$conversations = AiAgentConversation::where('ai_agent_id', $aiAgent->id)
    ->orderBy('updated_at', 'desc')
    ->limit(5)
    ->get();

echo "📊 Total Conversations: " . AiAgentConversation::where('ai_agent_id', $aiAgent->id)->count() . "\n\n";

if ($conversations->isEmpty()) {
    echo "ℹ️  Belum ada conversation. Silakan test AI Agent di dashboard terlebih dahulu.\n";
    exit(0);
}

echo "=== 5 CONVERSATION TERAKHIR ===\n\n";

foreach ($conversations as $index => $conv) {
    $contact = WhatsAppContact::find($conv->whatsapp_contact_id);
    $messageCount = count($conv->messages ?? []);
    
    echo ($index + 1) . ". Conversation ID: {$conv->id}\n";
    echo "   Contact: " . ($contact ? $contact->name : 'Unknown') . "\n";
    echo "   Messages: {$messageCount}\n";
    echo "   Last Updated: {$conv->updated_at->format('Y-m-d H:i:s')}\n";
    
    // Tampilkan 3 pesan terakhir
    if ($messageCount > 0) {
        $messages = array_slice($conv->messages, -3);
        echo "   Last 3 messages:\n";
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'human' ? '👤 User' : '🤖 AI';
            $content = substr($msg['content'], 0, 60);
            if (strlen($msg['content']) > 60) {
                $content .= '...';
            }
            echo "   - {$role}: {$content}\n";
        }
    }
    
    // Cek apakah ada order context (memory untuk order)
    if (!empty($conv->order_context)) {
        echo "   📦 Order Context: " . json_encode($conv->order_context) . "\n";
    }
    
    // Cek apakah ada summary (memory yang sudah diringkas)
    if (!empty($conv->summary)) {
        echo "   📝 Summary: " . substr($conv->summary, 0, 80) . "...\n";
    }
    
    echo "\n";
}

echo "\n=== TEST MEMORY FUNCTION ===\n\n";

// 3. Test memory dengan conversation terakhir
$latestConv = $conversations->first();

if ($latestConv) {
    echo "Testing dengan Conversation ID: {$latestConv->id}\n\n";
    
    // Test: Tambah message baru
    echo "1. Test: Menambah message baru...\n";
    $beforeCount = count($latestConv->messages ?? []);
    $latestConv->addMessage('human', 'Test message untuk cek memory');
    $latestConv->refresh();
    $afterCount = count($latestConv->messages ?? []);
    
    if ($afterCount > $beforeCount) {
        echo "   ✅ Message berhasil ditambahkan ({$beforeCount} -> {$afterCount})\n";
    } else {
        echo "   ❌ Gagal menambah message\n";
    }
    
    // Test: Cek apakah message tersimpan
    echo "\n2. Test: Cek message tersimpan di database...\n";
    $latestConv->refresh();
    $lastMessage = end($latestConv->messages);
    
    if ($lastMessage && $lastMessage['content'] === 'Test message untuk cek memory') {
        echo "   ✅ Message tersimpan dengan benar\n";
        echo "   Content: {$lastMessage['content']}\n";
        echo "   Role: {$lastMessage['role']}\n";
    } else {
        echo "   ❌ Message tidak tersimpan dengan benar\n";
    }
    
    // Test: Cek token estimation
    echo "\n3. Test: Estimasi token untuk conversation...\n";
    $tokenEstimator = new \App\Services\TokenEstimator();
    $estimatedTokens = $tokenEstimator->estimateConversationTokens($latestConv->messages ?? []);
    echo "   📊 Estimated tokens: {$estimatedTokens}\n";
    
    // Test: Cek apakah perlu summarization
    echo "\n4. Test: Cek apakah perlu summarization...\n";
    $summarizer = new \App\Services\ConversationSummarizer($tokenEstimator);
    $shouldSummarize = $summarizer->shouldSummarize($latestConv);
    
    if ($shouldSummarize) {
        echo "   ⚠️  Conversation perlu di-summarize (terlalu panjang)\n";
        echo "   Message count: " . count($latestConv->messages ?? []) . "\n";
        echo "   Token count: {$estimatedTokens}\n";
    } else {
        echo "   ✅ Conversation masih dalam batas normal\n";
    }
}

echo "\n=== KESIMPULAN ===\n\n";
echo "Memory AI Agent: " . ($messageCount > 0 ? "✅ BERFUNGSI" : "❌ TIDAK BERFUNGSI") . "\n";
echo "Conversation tersimpan: " . ($conversations->count() > 0 ? "✅ YA" : "❌ TIDAK") . "\n";
echo "\nUntuk test lebih lanjut:\n";
echo "1. Buka dashboard -> AI Agent\n";
echo "2. Kirim beberapa pesan di test chat\n";
echo "3. Jalankan script ini lagi untuk melihat hasilnya\n";
