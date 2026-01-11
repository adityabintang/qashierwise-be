<?php

/**
 * Check Queue Status and AI Agent Configuration
 * 
 * This script helps diagnose why webhook messages are not being processed by AI Agent
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\AiAgent;
use App\Models\WhatsAppAccount;

echo "\n";
echo "===========================================\n";
echo "   QUEUE & AI AGENT STATUS CHECKER\n";
echo "===========================================\n\n";

// 1. Check Queue Configuration
echo "1. QUEUE CONFIGURATION\n";
echo "   -------------------\n";
$queueConnection = config('queue.default');
echo "   Queue Driver: {$queueConnection}\n";

if ($queueConnection === 'database') {
    echo "   ⚠️  Using database queue - QUEUE WORKER MUST BE RUNNING!\n";
    echo "   Run: php artisan queue:work --queue=ai-agent\n";
} elseif ($queueConnection === 'sync') {
    echo "   ℹ️  Using sync queue - Jobs run immediately (not recommended for production)\n";
} else {
    echo "   Queue: {$queueConnection}\n";
}
echo "\n";

// 2. Check Pending Jobs
echo "2. PENDING JOBS IN QUEUE\n";
echo "   ---------------------\n";
try {
    $pendingJobs = DB::table('jobs')->count();
    echo "   Pending Jobs: {$pendingJobs}\n";
    
    if ($pendingJobs > 0) {
        echo "   ⚠️  There are {$pendingJobs} jobs waiting to be processed!\n";
        echo "   Start queue worker: php artisan queue:work\n";
        
        // Show recent jobs
        $recentJobs = DB::table('jobs')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'queue', 'payload', 'created_at']);
        
        echo "\n   Recent Jobs:\n";
        foreach ($recentJobs as $job) {
            $payload = json_decode($job->payload, true);
            $displayName = $payload['displayName'] ?? 'Unknown';
            echo "   - ID: {$job->id} | Queue: {$job->queue} | Job: {$displayName}\n";
        }
    } else {
        echo "   ✅ No pending jobs\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Error checking jobs: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Check Failed Jobs
echo "3. FAILED JOBS\n";
echo "   -----------\n";
try {
    $failedJobs = DB::table('failed_jobs')->count();
    echo "   Failed Jobs: {$failedJobs}\n";
    
    if ($failedJobs > 0) {
        echo "   ⚠️  There are {$failedJobs} failed jobs!\n";
        echo "   View: php artisan queue:failed\n";
        echo "   Retry: php artisan queue:retry all\n";
        
        // Show recent failed jobs
        $recentFailed = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit(3)
            ->get(['id', 'queue', 'exception', 'failed_at']);
        
        echo "\n   Recent Failed Jobs:\n";
        foreach ($recentFailed as $failed) {
            $exceptionPreview = substr($failed->exception, 0, 100);
            echo "   - ID: {$failed->id} | Queue: {$failed->queue}\n";
            echo "     Error: {$exceptionPreview}...\n";
        }
    } else {
        echo "   ✅ No failed jobs\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Error checking failed jobs: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Check AI Agents
echo "4. AI AGENT STATUS\n";
echo "   ---------------\n";
try {
    $aiAgents = AiAgent::with('whatsappAccount')->get();
    
    if ($aiAgents->isEmpty()) {
        echo "   ⚠️  No AI Agents configured!\n";
    } else {
        echo "   Total AI Agents: " . $aiAgents->count() . "\n\n";
        
        foreach ($aiAgents as $agent) {
            $status = $agent->is_active ? '✅ ACTIVE' : '❌ INACTIVE';
            $orderStatus = $agent->enable_order ? '✅' : '❌';
            $qrisStatus = $agent->enable_qris ? '✅' : '❌';
            
            echo "   Agent: {$agent->bot_name}\n";
            echo "   - Status: {$status}\n";
            echo "   - Order Enabled: {$orderStatus}\n";
            echo "   - QRIS Enabled: {$qrisStatus}\n";
            
            if ($agent->whatsappAccount) {
                $waStatus = $agent->whatsappAccount->is_active ? '✅ ACTIVE' : '❌ INACTIVE';
                echo "   - WhatsApp Account: {$waStatus}\n";
                echo "   - Phone Number ID: {$agent->whatsappAccount->phone_number_id}\n";
            } else {
                echo "   - WhatsApp Account: ❌ NOT CONNECTED\n";
            }
            echo "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Error checking AI agents: " . $e->getMessage() . "\n";
}

// 5. Check WhatsApp Accounts
echo "5. WHATSAPP ACCOUNTS\n";
echo "   -----------------\n";
try {
    $accounts = WhatsAppAccount::all();
    
    if ($accounts->isEmpty()) {
        echo "   ⚠️  No WhatsApp accounts configured!\n";
    } else {
        echo "   Total Accounts: " . $accounts->count() . "\n\n";
        
        foreach ($accounts as $account) {
            $status = $account->is_active ? '✅ ACTIVE' : '❌ INACTIVE';
            $hasToken = !empty($account->access_token) ? '✅' : '❌';
            
            echo "   Account ID: {$account->id}\n";
            echo "   - Status: {$status}\n";
            echo "   - Phone Number ID: {$account->phone_number_id}\n";
            echo "   - Has Access Token: {$hasToken}\n";
            echo "   - User ID: {$account->user_id}\n";
            echo "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Error checking WhatsApp accounts: " . $e->getMessage() . "\n";
}

// 6. Check Recent Messages
echo "6. RECENT INCOMING MESSAGES\n";
echo "   ------------------------\n";
try {
    $recentMessages = DB::table('whatsapp_messages')
        ->where('direction', 'incoming')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get(['id', 'contact_id', 'type', 'content', 'created_at']);
    
    if ($recentMessages->isEmpty()) {
        echo "   No recent incoming messages\n";
    } else {
        foreach ($recentMessages as $msg) {
            $contentPreview = substr($msg->content, 0, 50);
            echo "   - ID: {$msg->id} | Type: {$msg->type} | Contact: {$msg->contact_id}\n";
            echo "     Content: {$contentPreview}...\n";
            echo "     Time: {$msg->created_at}\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Error checking messages: " . $e->getMessage() . "\n";
}
echo "\n";

// 7. Recommendations
echo "===========================================\n";
echo "   RECOMMENDATIONS\n";
echo "===========================================\n\n";

$recommendations = [];

// Check if queue worker is needed
if ($queueConnection === 'database') {
    $pendingCount = DB::table('jobs')->count();
    if ($pendingCount > 0) {
        $recommendations[] = "🔴 START QUEUE WORKER: php artisan queue:work --queue=ai-agent";
    }
}

// Check if AI agent is active
$activeAgents = AiAgent::where('is_active', true)->count();
if ($activeAgents === 0) {
    $recommendations[] = "🔴 ACTIVATE AI AGENT: Set is_active = true in ai_agents table";
}

// Check if WhatsApp account is active
$activeAccounts = WhatsAppAccount::where('is_active', true)->count();
if ($activeAccounts === 0) {
    $recommendations[] = "🔴 ACTIVATE WHATSAPP ACCOUNT: Connect WhatsApp account via dashboard";
}

// Check failed jobs
$failedCount = DB::table('failed_jobs')->count();
if ($failedCount > 0) {
    $recommendations[] = "🟡 RETRY FAILED JOBS: php artisan queue:retry all";
}

if (empty($recommendations)) {
    echo "✅ Everything looks good!\n";
    echo "\nIf messages still not working, check:\n";
    echo "- Webhook URL configured in Meta: " . config('app.url') . "/api/whatsapp/webhook\n";
    echo "- Webhook verify token matches in Meta dashboard\n";
    echo "- Check logs: tail -f storage/logs/laravel.log\n";
} else {
    echo "Action items:\n\n";
    foreach ($recommendations as $i => $rec) {
        echo ($i + 1) . ". {$rec}\n";
    }
}

echo "\n";
echo "===========================================\n";
echo "   For more details, check:\n";
echo "   - FIX_WEBHOOK_AI_AGENT_FLOW.md\n";
echo "===========================================\n\n";
