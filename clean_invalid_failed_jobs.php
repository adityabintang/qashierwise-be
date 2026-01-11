<?php

/**
 * Clean Invalid Failed Jobs
 * 
 * This script removes failed jobs that reference deleted models (WhatsAppContact, WhatsAppAccount)
 * so they don't cause errors when retrying.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "===========================================\n";
echo "   CLEAN INVALID FAILED JOBS\n";
echo "===========================================\n\n";

// Get all failed jobs
$failedJobs = DB::table('failed_jobs')->get();

echo "Total failed jobs: " . $failedJobs->count() . "\n\n";

if ($failedJobs->isEmpty()) {
    echo "✅ No failed jobs to clean.\n\n";
    exit(0);
}

$invalidJobs = [];
$validJobs = [];

foreach ($failedJobs as $job) {
    $payload = json_decode($job->payload, true);
    $command = unserialize($payload['data']['command']);
    
    $isInvalid = false;
    $reason = '';
    
    // Check if it's ProcessAiAgentMessage job
    if ($command instanceof \App\Jobs\ProcessAiAgentMessage) {
        // Check if WhatsAppAccount exists
        try {
            $accountId = $command->account->id ?? null;
            if ($accountId) {
                $accountExists = DB::table('whatsapp_accounts')->where('id', $accountId)->exists();
                if (!$accountExists) {
                    $isInvalid = true;
                    $reason = "WhatsAppAccount ID {$accountId} not found";
                }
            }
        } catch (\Exception $e) {
            $isInvalid = true;
            $reason = "Error checking account: " . $e->getMessage();
        }
        
        // Check if WhatsAppContact exists
        try {
            $contactId = $command->contact->id ?? null;
            if ($contactId && !$isInvalid) {
                $contactExists = DB::table('whatsapp_contacts')->where('id', $contactId)->exists();
                if (!$contactExists) {
                    $isInvalid = true;
                    $reason = "WhatsAppContact ID {$contactId} not found";
                }
            }
        } catch (\Exception $e) {
            $isInvalid = true;
            $reason = "Error checking contact: " . $e->getMessage();
        }
    }
    
    if ($isInvalid) {
        $invalidJobs[] = [
            'id' => $job->id,
            'queue' => $job->queue,
            'reason' => $reason,
            'failed_at' => $job->failed_at,
        ];
    } else {
        $validJobs[] = $job->id;
    }
}

echo "Analysis:\n";
echo "- Valid jobs: " . count($validJobs) . "\n";
echo "- Invalid jobs: " . count($invalidJobs) . "\n\n";

if (empty($invalidJobs)) {
    echo "✅ All failed jobs are valid. You can retry them with:\n";
    echo "   php artisan queue:retry all\n\n";
    exit(0);
}

echo "Invalid jobs found:\n";
echo "-------------------\n";
foreach ($invalidJobs as $job) {
    echo "ID: {$job['id']} | Queue: {$job['queue']}\n";
    echo "Reason: {$job['reason']}\n";
    echo "Failed at: {$job['failed_at']}\n\n";
}

echo "Do you want to delete these invalid jobs? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) === 'yes' || strtolower($line) === 'y') {
    $deletedCount = 0;
    
    foreach ($invalidJobs as $job) {
        DB::table('failed_jobs')->where('id', $job['id'])->delete();
        $deletedCount++;
    }
    
    echo "\n✅ Deleted {$deletedCount} invalid failed jobs.\n";
    
    if (!empty($validJobs)) {
        echo "\n⚠️  You still have " . count($validJobs) . " valid failed jobs.\n";
        echo "   These might fail again if WhatsApp account is not connected.\n";
        echo "   After connecting WhatsApp account, retry with:\n";
        echo "   php artisan queue:retry all\n";
    }
} else {
    echo "\n❌ Cancelled. No jobs were deleted.\n";
}

echo "\n";
echo "===========================================\n";
echo "   NEXT STEPS\n";
echo "===========================================\n\n";

echo "1. Connect WhatsApp account (see CARA_HUBUNGKAN_WHATSAPP_ACCOUNT.md)\n";
echo "2. Check status: php check_queue_status.php\n";
echo "3. Test by sending a WhatsApp message\n\n";

echo "===========================================\n\n";
