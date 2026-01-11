<?php

/**
 * Fix AI Agent WhatsApp Connection
 * 
 * This script helps fix the connection between AI Agent and WhatsApp Account
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\AiAgent;
use App\Models\WhatsAppAccount;
use App\Models\User;

echo "\n";
echo "===========================================\n";
echo "   AI AGENT CONNECTION FIXER\n";
echo "===========================================\n\n";

// Check current status
echo "1. CHECKING CURRENT STATUS\n";
echo "   -----------------------\n";

$aiAgents = AiAgent::all();
$whatsappAccounts = WhatsAppAccount::all();
$users = User::all();

echo "   Users: " . $users->count() . "\n";
echo "   AI Agents: " . $aiAgents->count() . "\n";
echo "   WhatsApp Accounts: " . $whatsappAccounts->count() . "\n\n";

if ($aiAgents->isEmpty()) {
    echo "   ❌ No AI Agents found!\n";
    echo "   Please create an AI Agent first via dashboard.\n\n";
    exit(1);
}

if ($whatsappAccounts->isEmpty()) {
    echo "   ❌ No WhatsApp Accounts found!\n\n";
    echo "   You need to connect a WhatsApp account first.\n";
    echo "   Options:\n";
    echo "   1. Via Dashboard: Go to WhatsApp Settings → Connect Account\n";
    echo "   2. Manual: See FIX_WEBHOOK_AI_AGENT_FLOW.md for SQL commands\n\n";
    
    // Ask if user wants to create a test account
    echo "   Do you want to create a TEST WhatsApp account? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) === 'yes' || strtolower($line) === 'y') {
        createTestWhatsAppAccount($users->first());
        $whatsappAccounts = WhatsAppAccount::all();
    } else {
        exit(1);
    }
}

echo "\n2. FIXING AI AGENT CONNECTIONS\n";
echo "   ----------------------------\n";

$fixed = 0;
$alreadyConnected = 0;

foreach ($aiAgents as $agent) {
    echo "   Agent: {$agent->bot_name} (ID: {$agent->id})\n";
    
    if ($agent->whatsapp_account_id) {
        $account = WhatsAppAccount::find($agent->whatsapp_account_id);
        if ($account) {
            echo "   - Already connected to WhatsApp account (Phone: {$account->phone_number_id})\n";
            $alreadyConnected++;
        } else {
            echo "   - ⚠️  Connected to non-existent account, fixing...\n";
            $newAccount = $whatsappAccounts->first();
            $agent->whatsapp_account_id = $newAccount->id;
            $agent->save();
            echo "   - ✅ Connected to WhatsApp account (Phone: {$newAccount->phone_number_id})\n";
            $fixed++;
        }
    } else {
        echo "   - ❌ Not connected to any WhatsApp account\n";
        
        // Find WhatsApp account for the same user
        $account = $whatsappAccounts->where('user_id', $agent->user_id)->first();
        
        if (!$account) {
            // Use first available account
            $account = $whatsappAccounts->first();
        }
        
        if ($account) {
            $agent->whatsapp_account_id = $account->id;
            $agent->save();
            echo "   - ✅ Connected to WhatsApp account (Phone: {$account->phone_number_id})\n";
            $fixed++;
        } else {
            echo "   - ❌ No WhatsApp account available to connect\n";
        }
    }
    echo "\n";
}

echo "   Summary:\n";
echo "   - Already connected: {$alreadyConnected}\n";
echo "   - Fixed: {$fixed}\n\n";

// Check failed jobs
echo "3. CHECKING FAILED JOBS\n";
echo "   --------------------\n";

$failedJobs = DB::table('failed_jobs')->count();
echo "   Failed jobs: {$failedJobs}\n";

if ($failedJobs > 0) {
    echo "\n   Do you want to retry all failed jobs? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) === 'yes' || strtolower($line) === 'y') {
        echo "   Retrying failed jobs...\n";
        exec('php artisan queue:retry all', $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "   ✅ Failed jobs queued for retry\n";
        } else {
            echo "   ❌ Error retrying jobs\n";
        }
    }
}

echo "\n";
echo "===========================================\n";
echo "   NEXT STEPS\n";
echo "===========================================\n\n";

echo "1. ✅ AI Agents are now connected to WhatsApp accounts\n";
echo "2. 🔄 Test by sending a WhatsApp message to your business number\n";
echo "3. 📊 Monitor with: php check_queue_status.php\n";
echo "4. 📝 Check logs: tail -f storage/logs/laravel.log\n\n";

if (config('queue.default') === 'database') {
    $pendingJobs = DB::table('jobs')->count();
    if ($pendingJobs > 0) {
        echo "⚠️  IMPORTANT: You have {$pendingJobs} pending jobs!\n";
        echo "   Start queue worker: php artisan queue:work --queue=ai-agent\n";
        echo "   Or use: start_queue_worker.bat (Windows) / start_queue_worker.sh (Linux/Mac)\n\n";
    }
}

echo "===========================================\n\n";

/**
 * Create a test WhatsApp account
 */
function createTestWhatsAppAccount($user)
{
    echo "\n   Creating TEST WhatsApp account...\n";
    echo "   ⚠️  This is for TESTING only. Replace with real credentials later!\n\n";
    
    echo "   Enter Phone Number ID (from Meta): ";
    $handle = fopen("php://stdin", "r");
    $phoneNumberId = trim(fgets($handle));
    
    echo "   Enter Access Token (from Meta): ";
    $accessToken = trim(fgets($handle));
    
    echo "   Enter WABA ID (optional, press Enter to skip): ";
    $wabaId = trim(fgets($handle));
    
    echo "   Enter Phone Number (e.g., 628123456789): ";
    $phoneNumber = trim(fgets($handle));
    
    fclose($handle);
    
    if (empty($phoneNumberId) || empty($accessToken) || empty($phoneNumber)) {
        echo "   ❌ Missing required fields. Aborting.\n";
        return;
    }
    
    try {
        $account = WhatsAppAccount::create([
            'user_id' => $user->id,
            'waba_id' => $wabaId ?: null,
            'phone_number_id' => $phoneNumberId,
            'phone_number' => $phoneNumber,
            'display_phone_number' => formatPhoneNumber($phoneNumber),
            'access_token' => $accessToken,
            'is_active' => true,
        ]);
        
        echo "   ✅ WhatsApp account created successfully!\n";
        echo "   - ID: {$account->id}\n";
        echo "   - Phone: {$account->display_phone_number}\n";
        
    } catch (\Exception $e) {
        echo "   ❌ Error creating account: " . $e->getMessage() . "\n";
    }
}

/**
 * Format phone number for display
 */
function formatPhoneNumber($phone)
{
    // Simple formatting: +62 812-3456-789
    if (strlen($phone) >= 10) {
        return '+' . substr($phone, 0, 2) . ' ' . 
               substr($phone, 2, 3) . '-' . 
               substr($phone, 5, 4) . '-' . 
               substr($phone, 9);
    }
    return $phone;
}
