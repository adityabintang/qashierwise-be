<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Jobs in Queue ===\n\n";

// Check ai-agent queue
$aiAgentJobs = DB::table('jobs')->where('queue', 'ai-agent')->get();
echo "Jobs in 'ai-agent' queue: " . count($aiAgentJobs) . "\n";

foreach ($aiAgentJobs as $job) {
    $payload = json_decode($job->payload, true);
    $displayName = $payload['displayName'] ?? 'Unknown';
    echo "  - ID: {$job->id}, Job: {$displayName}, Available at: " . date('Y-m-d H:i:s', $job->available_at) . "\n";
}

echo "\n";

// Check failed jobs
$failedJobs = DB::table('failed_jobs')->get();
echo "Failed jobs: " . count($failedJobs) . "\n";

foreach ($failedJobs as $job) {
    $payload = json_decode($job->payload, true);
    $displayName = $payload['displayName'] ?? 'Unknown';
    echo "  - ID: {$job->id}, Job: {$displayName}, Failed at: {$job->failed_at}\n";
}

echo "\n";

// Check buffer status for the phone number
$phoneNumber = '6288802597405';
$bufferKey = "msg_buffer:{$phoneNumber}";
$lockKey = "msg_debounce:{$phoneNumber}";

echo "=== Buffer Status for {$phoneNumber} ===\n";
echo "Buffer key: {$bufferKey}\n";
echo "Buffer content: " . json_encode(Cache::get($bufferKey, [])) . "\n";
echo "Buffer size: " . count(Cache::get($bufferKey, [])) . "\n";
echo "Debounce lock exists: " . (Cache::has($lockKey) ? 'YES' : 'NO') . "\n";

echo "\n=== Done ===\n";
