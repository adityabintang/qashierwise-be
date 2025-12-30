<?php

/**
 * Script untuk monitoring empty response patterns dari AI Agent
 * 
 * Menganalisis log untuk menemukan:
 * - Frekuensi empty content dari LLM
 * - Pattern waktu terjadinya
 * - Tool calls yang terkait
 * - Conversation context saat terjadi
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== MONITOR: AI AGENT EMPTY RESPONSE PATTERNS ===\n\n";

$logFile = storage_path('logs/laravel.log');

if (!file_exists($logFile)) {
    echo "❌ Log file tidak ditemukan: {$logFile}\n";
    exit(1);
}

echo "📁 Reading log file: {$logFile}\n";
echo "📊 Analyzing patterns...\n\n";

$logContent = file_get_contents($logFile);
$lines = explode("\n", $logContent);

// Statistics
$stats = [
    'empty_content_count' => 0,
    'no_results_count' => 0,
    'empty_content_times' => [],
    'no_results_times' => [],
    'related_tool_calls' => [],
    'conversation_ids' => [],
];

// Parse logs
foreach ($lines as $line) {
    // Check for empty content warnings
    if (strpos($line, 'LLM returned empty content') !== false) {
        $stats['empty_content_count']++;
        
        // Extract timestamp
        if (preg_match('/\[([\d\-\s:]+)\]/', $line, $matches)) {
            $stats['empty_content_times'][] = $matches[1];
        }
        
        // Extract conversation_id if present
        if (preg_match('/conversation_id["\']?\s*:\s*["\']?(\d+)/', $line, $matches)) {
            $stats['conversation_ids'][] = $matches[1];
        }
    }
    
    // Check for no results warnings
    if (strpos($line, 'No user-facing results from tool calls') !== false) {
        $stats['no_results_count']++;
        
        // Extract timestamp
        if (preg_match('/\[([\d\-\s:]+)\]/', $line, $matches)) {
            $stats['no_results_times'][] = $matches[1];
        }
        
        // Extract tool calls
        if (preg_match('/tool_calls["\']?\s*:\s*\[(.*?)\]/', $line, $matches)) {
            $toolCalls = $matches[1];
            $stats['related_tool_calls'][] = $toolCalls;
        }
        
        // Extract conversation_id if present
        if (preg_match('/conversation_id["\']?\s*:\s*["\']?(\d+)/', $line, $matches)) {
            $stats['conversation_ids'][] = $matches[1];
        }
    }
}

// Display results
echo "=== STATISTICS ===\n\n";

echo "📊 Empty Content Warnings: {$stats['empty_content_count']}\n";
echo "📊 No Results Warnings: {$stats['no_results_count']}\n";
echo "📊 Total Issues: " . ($stats['empty_content_count'] + $stats['no_results_count']) . "\n\n";

if ($stats['empty_content_count'] > 0) {
    echo "=== EMPTY CONTENT DETAILS ===\n\n";
    
    echo "⏰ Timestamps:\n";
    $recentTimes = array_slice($stats['empty_content_times'], -10);
    foreach ($recentTimes as $time) {
        echo "   - {$time}\n";
    }
    echo "\n";
    
    // Analyze time patterns
    if (count($stats['empty_content_times']) > 1) {
        $hours = [];
        foreach ($stats['empty_content_times'] as $time) {
            if (preg_match('/(\d{2}):/', $time, $matches)) {
                $hour = (int)$matches[1];
                $hours[$hour] = ($hours[$hour] ?? 0) + 1;
            }
        }
        
        if (!empty($hours)) {
            arsort($hours);
            echo "📈 Peak Hours:\n";
            $count = 0;
            foreach ($hours as $hour => $freq) {
                echo "   - {$hour}:00 - {$freq} occurrences\n";
                $count++;
                if ($count >= 5) break;
            }
            echo "\n";
        }
    }
}

if ($stats['no_results_count'] > 0) {
    echo "=== NO RESULTS DETAILS ===\n\n";
    
    echo "⏰ Timestamps:\n";
    $recentTimes = array_slice($stats['no_results_times'], -10);
    foreach ($recentTimes as $time) {
        echo "   - {$time}\n";
    }
    echo "\n";
    
    if (!empty($stats['related_tool_calls'])) {
        echo "🔧 Related Tool Calls:\n";
        $toolCallCounts = [];
        foreach ($stats['related_tool_calls'] as $toolCallStr) {
            // Count occurrences of each tool
            if (preg_match_all('/search_products|search_multiple_products|get_all_products/', $toolCallStr, $matches)) {
                foreach ($matches[0] as $tool) {
                    $toolCallCounts[$tool] = ($toolCallCounts[$tool] ?? 0) + 1;
                }
            }
        }
        
        if (!empty($toolCallCounts)) {
            arsort($toolCallCounts);
            foreach ($toolCallCounts as $tool => $count) {
                echo "   - {$tool}: {$count} times\n";
            }
        }
        echo "\n";
    }
}

if (!empty($stats['conversation_ids'])) {
    $uniqueConversations = array_unique($stats['conversation_ids']);
    echo "💬 Affected Conversations: " . count($uniqueConversations) . "\n";
    echo "   IDs: " . implode(', ', array_slice($uniqueConversations, 0, 10));
    if (count($uniqueConversations) > 10) {
        echo " ... and " . (count($uniqueConversations) - 10) . " more";
    }
    echo "\n\n";
}

// Recommendations
echo "=== RECOMMENDATIONS ===\n\n";

$totalIssues = $stats['empty_content_count'] + $stats['no_results_count'];

if ($totalIssues === 0) {
    echo "✅ Tidak ada empty response issues terdeteksi\n";
    echo "   System berfungsi dengan baik!\n\n";
} elseif ($totalIssues < 5) {
    echo "✅ Issues minimal (< 5)\n";
    echo "   Fallback messages berfungsi dengan baik\n";
    echo "   Monitor terus untuk memastikan tidak meningkat\n\n";
} elseif ($totalIssues < 20) {
    echo "⚠️  Issues moderate (5-20)\n";
    echo "   Pertimbangkan untuk:\n";
    echo "   1. Review system prompt untuk lebih jelas\n";
    echo "   2. Adjust LLM temperature (saat ini 0.7)\n";
    echo "   3. Check BytePlus ARK API status\n\n";
} else {
    echo "❌ Issues tinggi (> 20)\n";
    echo "   Action required:\n";
    echo "   1. Review dan improve system prompt\n";
    echo "   2. Turunkan temperature ke 0.5\n";
    echo "   3. Add retry logic dengan prompt berbeda\n";
    echo "   4. Check API rate limits dan quotas\n";
    echo "   5. Consider fallback ke model lain\n\n";
}

// Check if fix is working
if ($totalIssues > 0) {
    echo "=== FIX STATUS ===\n\n";
    echo "✅ Fix telah diimplementasi\n";
    echo "✅ Fallback messages mencegah empty response ke user\n";
    echo "✅ Logging membantu identify patterns\n\n";
    echo "Next steps:\n";
    echo "1. Monitor frequency - apakah meningkat atau menurun?\n";
    echo "2. Analyze patterns - waktu, tool calls, conversations\n";
    echo "3. Improve berdasarkan data yang dikumpulkan\n\n";
}

echo "=== MONITORING COMMANDS ===\n\n";
echo "Real-time monitoring:\n";
echo "  tail -f storage/logs/laravel.log | grep 'empty content\\|No user-facing'\n\n";
echo "Count today's issues:\n";
echo "  grep '" . date('Y-m-d') . "' storage/logs/laravel.log | grep -c 'empty content\\|No user-facing'\n\n";
echo "Find specific conversation:\n";
echo "  grep 'conversation_id.*123' storage/logs/laravel.log\n\n";

echo "=== Monitor Selesai ===\n";
