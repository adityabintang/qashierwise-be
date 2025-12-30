<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AiAgentConversation;

echo "=== SUMMARY STATUS CHECK ===\n\n";

// Get latest conversation
$conv = AiAgentConversation::latest()->first();

if (!$conv) {
    echo "❌ No conversation found\n";
    exit(1);
}

echo "Conversation ID: {$conv->id}\n";
echo "Messages: " . count($conv->messages ?? []) . "\n";
echo "Last updated: {$conv->updated_at}\n\n";

// Check if has summary
if ($conv->hasSummary()) {
    echo "✅ HAS SUMMARY\n\n";
    
    $summary = $conv->getSummary();
    
    echo "=== SUMMARY DETAILS ===\n\n";
    echo "Summary: " . $summary['summary'] . "\n\n";
    echo "Intent: " . $summary['intent'] . "\n";
    echo "Source: " . ($summary['source'] ?? 'JSON') . "\n";
    echo "Generated at: " . ($summary['generated_at'] ?? 'N/A') . "\n\n";
    
    // Check if it's a fallback summary
    if (isset($summary['source']) && $summary['source'] === 'plain_text_fallback') {
        echo "⚠️  This is a FALLBACK summary (LLM didn't return JSON)\n";
        echo "   But it still works and saves tokens!\n\n";
    } else {
        echo "✅ This is a JSON summary (LLM returned proper JSON)\n\n";
    }
    
    // Show key data if available
    if (!empty($summary['key_data'])) {
        echo "=== KEY DATA ===\n\n";
        
        if (!empty($summary['key_data']['products'])) {
            echo "Products: " . implode(', ', $summary['key_data']['products']) . "\n";
        }
        
        if (!empty($summary['key_data']['order_items'])) {
            echo "Order items:\n";
            foreach ($summary['key_data']['order_items'] as $item) {
                $product = $item['product'] ?? 'Unknown';
                $quantity = $item['quantity'] ?? 1;
                echo "  - {$product} x{$quantity}\n";
            }
        }
        
        if (isset($summary['key_data']['total_estimate'])) {
            echo "Total estimate: Rp " . number_format($summary['key_data']['total_estimate'], 0, ',', '.') . "\n";
        }
    }
    
} else {
    echo "❌ NO SUMMARY YET\n";
    echo "   (Conversation needs at least 6 messages to trigger summarization)\n";
}

echo "\n=== RECENT ACTIVITY ===\n\n";

// Show recent conversations with summaries
$recentConvs = AiAgentConversation::orderBy('updated_at', 'desc')
    ->limit(5)
    ->get();

echo "Recent conversations:\n";
foreach ($recentConvs as $c) {
    $hasSummary = $c->hasSummary() ? '✅' : '❌';
    $msgCount = count($c->messages ?? []);
    echo "{$hasSummary} ID {$c->id}: {$msgCount} messages";
    
    if ($c->hasSummary()) {
        $s = $c->getSummary();
        $source = isset($s['source']) && $s['source'] === 'plain_text_fallback' ? ' (fallback)' : ' (JSON)';
        echo " - {$s['intent']}{$source}";
    }
    
    echo "\n";
}
