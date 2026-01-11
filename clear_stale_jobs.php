<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

echo "=== Clearing Stale Jobs & Buffer ===\n\n";

// Clear all jobs in ai-agent queue
$deleted = DB::table('jobs')->where('queue', 'ai-agent')->delete();
echo "Deleted {$deleted} jobs from ai-agent queue\n";

// Clear any pending buffers for the test number
$phoneNumber = '6288802597405';
Cache::forget("msg_buffer:{$phoneNumber}");
Cache::forget("msg_debounce:{$phoneNumber}");
echo "Cleared buffer for {$phoneNumber}\n";

// Show remaining jobs
$remaining = DB::table('jobs')->count();
echo "Total remaining jobs in queue: {$remaining}\n\n";

echo "✅ Done! Now restart queue worker and test again.\n";
