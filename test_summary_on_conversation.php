<?php

/**
 * Test summary fix pada conversation yang ada di database
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AiAgentConversation;
use App\Services\ConversationSummarizer;

echo "=== TEST SUMMARY ON REAL CONVERSATION ===\n\n";

// Get latest conversation
$conv = AiAgentConversation::latest()->first();

if (!$conv) {
    echo "❌ No conversation found in database\n";
    echo "Silakan test AI Agent di dashboard terlebih dahulu\n";
    exit(1);
}

echo "✅ Conversation found: ID {$conv->id}\n";
echo "   Messages: " . count($conv->messages ?? []) . "\n";
echo "   Last updated: {$conv->updated_at}\n\n";

// Check if needs summarization
$summarizer = app(ConversationSummarizer::class);

if (!$summarizer->shouldSummarize($conv)) {
    echo "ℹ️  Conversation does not need summarization yet\n";
    echo "   (Need at least 6 messages with 3 substantive messages)\n";
    exit(0);
}

echo "✅ Conversation needs summarization\n\n";

// Generate summary
echo "Generating summary...\n";
$summary = $summarizer->generateSummary($conv->messages);

if (!$summary) {
    echo "❌ Failed to generate summary\n";
    exit(1);
}

echo "✅ Summary generated successfully!\n\n";

// Display summary details
echo "=== SUMMARY DETAILS ===\n\n";
echo "Summary: " . $summary['summary'] . "\n\n";
echo "Intent: " . $summary['intent'] . "\n";
echo "Source: " . ($summary['source'] ?? 'JSON') . "\n";
echo "Generated at: " . ($summary['generated_at'] ?? 'N/A') . "\n\n";

if (!empty($summary['key_data']['products'])) {
    echo "Products: " . implode(', ', $summary['key_data']['products']) . "\n";
}

if (!empty($summary['key_data']['order_items'])) {
    echo "Order items:\n";
    foreach ($summary['key_data']['order_items'] as $item) {
        echo "  - " . ($item['product'] ?? 'Unknown') . " x" . ($item['quantity'] ?? 1) . "\n";
    }
}

if (isset($summary['key_data']['total_estimate'])) {
    echo "Total estimate: Rp " . number_format($summary['key_data']['total_estimate'], 0, ',', '.') . "\n";
}

// Store summary
echo "\nStoring summary to database...\n";
$summarizer->storeSummary($conv, $summary);

// Verify storage
$conv->refresh();
if ($conv->hasSummary()) {
    echo "✅ Summary stored successfully!\n\n";
    
    // Test context retrieval
    echo "=== TEST CONTEXT RETRIEVAL ===\n\n";
    $context = $summarizer->getContextForLLM($conv);
    echo "Context messages: " . count($context) . "\n";
    echo "First message type: " . ($context[0]['type'] ?? 'N/A') . "\n";
    
    if (isset($context[0]['content']) && str_contains($context[0]['content'], 'Ringkasan Percakapan')) {
        echo "✅ Context includes summary\n";
    }
} else {
    echo "❌ Failed to store summary\n";
}

echo "\n=== TEST COMPLETED ===\n";
