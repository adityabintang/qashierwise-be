<?php

/**
 * Script untuk test fix summary non-JSON response
 * 
 * Cara pakai:
 * php test_summary_fix.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ConversationSummarizer;
use App\Services\TokenEstimator;
use App\Services\SummaryValidator;
use App\Services\IntentTracker;

echo "=== TEST SUMMARY FIX: NON-JSON RESPONSE ===\n\n";

// Initialize services
$tokenEstimator = new TokenEstimator();
$summaryValidator = new SummaryValidator();
$intentTracker = new IntentTracker();
$summarizer = new ConversationSummarizer($tokenEstimator, $summaryValidator, $intentTracker);

// Test 1: Plain text response (simulate LLM non-JSON response)
echo "Test 1: Plain Text Response\n";
echo "----------------------------\n";

$plainTextResponse = "User bertanya tentang menu seafood dan ingin memesan 2 porsi nasi goreng seafood dengan harga Rp 50.000 per porsi.";

// Use reflection to test private method
$reflection = new ReflectionClass($summarizer);
$method = $reflection->getMethod('parseJsonResponse');
$method->setAccessible(true);

$result = $method->invoke($summarizer, $plainTextResponse);

if ($result) {
    echo "✅ Summary created from plain text\n";
    echo "   Summary: " . substr($result['summary'], 0, 80) . "...\n";
    echo "   Intent: " . $result['intent'] . "\n";
    echo "   Source: " . ($result['source'] ?? 'N/A') . "\n";
    
    if (!empty($result['key_data']['products'])) {
        echo "   Products detected: " . implode(', ', $result['key_data']['products']) . "\n";
    }
    
    if (isset($result['key_data']['total_estimate'])) {
        echo "   Total estimate: Rp " . number_format($result['key_data']['total_estimate'], 0, ',', '.') . "\n";
    }
} else {
    echo "❌ Failed to create summary\n";
}

echo "\n";

// Test 2: Valid JSON response
echo "Test 2: Valid JSON Response\n";
echo "----------------------------\n";

$jsonResponse = json_encode([
    'summary' => 'User ingin memesan dimsum keju',
    'intent' => 'order_food',
    'key_data' => [
        'products' => ['dimsum keju'],
        'order_items' => [
            ['product' => 'Dimsum Keju', 'quantity' => 2]
        ],
        'total_estimate' => 80000
    ],
    'missing_information' => []
]);

$result = $method->invoke($summarizer, $jsonResponse);

if ($result) {
    echo "✅ Summary parsed from JSON\n";
    echo "   Summary: " . $result['summary'] . "\n";
    echo "   Intent: " . $result['intent'] . "\n";
    echo "   Source: " . ($result['source'] ?? 'JSON') . "\n";
} else {
    echo "❌ Failed to parse JSON\n";
}

echo "\n";

// Test 3: Validation
echo "Test 3: Validation\n";
echo "-------------------\n";

// Test plain text fallback validation
$fallbackSummary = [
    'summary' => 'Test summary',
    'intent' => 'browse_menu',
    'key_data' => [],
    'missing_information' => [],
    'source' => 'plain_text_fallback'
];

$isValid = $summaryValidator->validate($fallbackSummary);
echo "Fallback summary validation: " . ($isValid ? "✅ VALID" : "❌ INVALID") . "\n";

if (!$isValid) {
    echo "Errors: " . implode(', ', $summaryValidator->getErrors()) . "\n";
}

echo "\n";

// Test 4: Intent Detection
echo "Test 4: Intent Detection\n";
echo "------------------------\n";

$testTexts = [
    'User ingin pesan 2 nasi goreng' => 'order_food',
    'Apa saja menu yang tersedia?' => 'browse_menu',
    'Saya mau bayar pakai QRIS' => 'payment',
    'Booking meja untuk 4 orang' => 'reservation',
    'Jam buka restoran kapan?' => 'general_question',
];

$detectMethod = $reflection->getMethod('detectIntentFromText');
$detectMethod->setAccessible(true);

foreach ($testTexts as $text => $expectedIntent) {
    $detectedIntent = $detectMethod->invoke($summarizer, $text);
    $match = $detectedIntent === $expectedIntent ? '✅' : '❌';
    echo "{$match} \"{$text}\"\n";
    echo "   Expected: {$expectedIntent}, Got: {$detectedIntent}\n";
}

echo "\n";

// Test 5: Data Extraction
echo "Test 5: Data Extraction\n";
echo "-----------------------\n";

$extractMethod = $reflection->getMethod('extractKeyDataFromText');
$extractMethod->setAccessible(true);

$testText = "User pesan 3 porsi dimsum keju dan 2 teh jumbo dengan total Rp 150000";
$extractedData = $extractMethod->invoke($summarizer, $testText);

echo "Text: \"{$testText}\"\n";
echo "Extracted data:\n";
echo "  - Products: " . implode(', ', $extractedData['products']) . "\n";
echo "  - Total estimate: Rp " . number_format($extractedData['total_estimate'] ?? 0, 0, ',', '.') . "\n";
echo "  - People count: " . ($extractedData['people_count'] ?? 'N/A') . "\n";

echo "\n";

// Test 6: Real conversation summary
echo "Test 6: Real Conversation Summary\n";
echo "----------------------------------\n";

$testMessages = [
    ['type' => 'human', 'content' => 'Halo, saya mau tanya menu apa saja yang tersedia?'],
    ['type' => 'ai', 'content' => 'Kami punya dimsum keju, nasi goreng seafood, dan teh jumbo.'],
    ['type' => 'human', 'content' => 'Berapa harga dimsum keju?'],
    ['type' => 'ai', 'content' => 'Dimsum keju harganya Rp 40.000 per porsi.'],
    ['type' => 'human', 'content' => 'Oke, saya mau pesan 2 porsi dimsum keju.'],
    ['type' => 'ai', 'content' => 'Baik, 2 porsi dimsum keju sudah ditambahkan ke keranjang.'],
    ['type' => 'human', 'content' => 'Tolong konfirmasi pesanan saya.'],
];

echo "Generating summary for " . count($testMessages) . " messages...\n";

$summary = $summarizer->generateSummary($testMessages);

if ($summary) {
    echo "✅ Summary generated successfully\n";
    echo "   Summary: " . substr($summary['summary'], 0, 100) . "...\n";
    echo "   Intent: " . $summary['intent'] . "\n";
    echo "   Source: " . ($summary['source'] ?? 'JSON') . "\n";
    echo "   Generated at: " . ($summary['generated_at'] ?? 'N/A') . "\n";
} else {
    echo "❌ Failed to generate summary\n";
}

echo "\n=== TEST COMPLETED ===\n";
echo "\nCatatan:\n";
echo "- Jika semua test ✅, fix berhasil!\n";
echo "- Summary sekarang bisa handle non-JSON response dari LLM\n";
echo "- Intent detection menggunakan keyword matching\n";
echo "- Data extraction menggunakan pattern matching\n";
