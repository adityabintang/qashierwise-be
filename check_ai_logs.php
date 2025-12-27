<?php

/**
 * Script untuk melihat log AI Agent
 * Jalankan: php check_ai_logs.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Log;

// Get log file path
$logPath = storage_path('logs/laravel.log');

if (!file_exists($logPath)) {
    echo "Log file tidak ditemukan: {$logPath}\n";
    exit(1);
}

// Read last 100 lines
$lines = [];
$file = new SplFileObject($logPath, 'r');
$file->seek(PHP_INT_MAX);
$lastLine = $file->key();
$startLine = max(0, $lastLine - 200);

$file->seek($startLine);
while (!$file->eof()) {
    $line = $file->current();
    if (stripos($line, 'AI Agent') !== false || 
        stripos($line, 'LLM') !== false || 
        stripos($line, 'tool call') !== false ||
        stripos($line, 'search_products') !== false ||
        stripos($line, 'add_to_cart') !== false) {
        $lines[] = $line;
    }
    $file->next();
}

echo "=== AI Agent Logs (Last 200 lines filtered) ===\n\n";
foreach ($lines as $line) {
    echo $line;
}

echo "\n\n=== Selesai ===\n";
