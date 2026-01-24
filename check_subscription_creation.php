<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

echo "=== Investigating Subscription Creation ===\n\n";

$subscription = Subscription::find(7);

if (!$subscription) {
    echo "❌ Subscription not found\n";
    exit(1);
}

echo "Subscription Details:\n";
echo "ID: {$subscription->id}\n";
echo "User ID: {$subscription->user_id}\n";
echo "Provider: {$subscription->provider}\n";
echo "Plan: {$subscription->plan_name}\n";
echo "Status: {$subscription->status}\n";
echo "Amount: {$subscription->amount}\n";
echo "Created at: {$subscription->created_at}\n";
echo "Updated at: {$subscription->updated_at}\n";
echo "Midtrans Subscription ID: " . ($subscription->midtrans_subscription_id ?? 'NULL') . "\n";
echo "Midtrans Customer ID: " . ($subscription->midtrans_customer_id ?? 'NULL') . "\n";
echo "Polar Subscription ID: " . ($subscription->polar_subscription_id ?? 'NULL') . "\n";
echo "Polar Customer ID: " . ($subscription->polar_customer_id ?? 'NULL') . "\n";
echo "Metadata: " . ($subscription->metadata ?? 'NULL') . "\n";
echo "\n";

// Check if this was created through webhook or manually
echo "Checking creation method...\n\n";

// Look for webhook logs around the creation time
$createdAt = $subscription->created_at;
$startTime = $createdAt->copy()->subMinutes(5);
$endTime = $createdAt->copy()->addMinutes(5);

echo "Looking for logs between {$startTime} and {$endTime}...\n\n";

// Check laravel.log for webhook entries
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    
    // Search for subscription creation logs
    $patterns = [
        'subscription.midtrans.created',
        'subscription.created',
        'Midtrans subscription created',
        'subscription webhook',
        'user_id.*' . $subscription->user_id,
    ];
    
    $foundLogs = false;
    foreach ($patterns as $pattern) {
        if (preg_match('/' . preg_quote($pattern, '/') . '/i', $logContent)) {
            echo "✅ Found log pattern: {$pattern}\n";
            $foundLogs = true;
        }
    }
    
    if (!$foundLogs) {
        echo "⚠️ No webhook logs found for this subscription creation\n";
    }
} else {
    echo "⚠️ Log file not found\n";
}

echo "\n";

// Check if there are any payments associated
$payments = DB::table('payments')
    ->where('user_id', $subscription->user_id)
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

if ($payments->isEmpty()) {
    echo "⚠️ No payments found for this user\n";
} else {
    echo "Recent payments for this user:\n";
    foreach ($payments as $payment) {
        echo "  - Payment ID: {$payment->id}\n";
        echo "    Amount: Rp " . number_format($payment->amount, 0, ',', '.') . "\n";
        echo "    Status: {$payment->status}\n";
        echo "    Created: {$payment->created_at}\n";
        echo "    ---\n";
    }
}

echo "\n";

// Check if there are any QRIS transactions
$qrisTransactions = DB::table('qris_transactions')
    ->where('user_id', $subscription->user_id)
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

if ($qrisTransactions->isEmpty()) {
    echo "⚠️ No QRIS transactions found for this user\n";
} else {
    echo "Recent QRIS transactions for this user:\n";
    foreach ($qrisTransactions as $tx) {
        echo "  - Transaction ID: {$tx->id}\n";
        echo "    Amount: Rp " . number_format($tx->amount, 0, ',', '.') . "\n";
        echo "    Status: {$tx->status}\n";
        echo "    Created: {$tx->created_at}\n";
        echo "    ---\n";
    }
}

echo "\n";

// Possible reasons
echo "=== Possible Reasons for Missing Midtrans ID ===\n\n";

$reasons = [
    "1. Subscription was created manually (via tinker, seeder, or direct DB insert)",
    "2. Webhook from Midtrans never arrived or failed",
    "3. Webhook arrived but failed to process properly",
    "4. Subscription was created before Midtrans integration was implemented",
    "5. Test/development subscription created without going through Midtrans flow",
];

foreach ($reasons as $reason) {
    echo $reason . "\n";
}

echo "\n";

// Recommendations
echo "=== Recommendations ===\n\n";

if ($subscription->amount == 0) {
    echo "⚠️ Amount is 0 - This looks like a test/trial subscription\n";
    echo "   Recommendation: This might be a free trial that doesn't need Midtrans ID\n";
} else {
    echo "💡 To fix this subscription:\n";
    echo "   1. If user paid through Midtrans, check Midtrans dashboard for subscription ID\n";
    echo "   2. Update subscription with: \$subscription->update(['midtrans_subscription_id' => 'sub_xxx']);\n";
    echo "   3. Or delete this subscription and have user subscribe again through proper flow\n";
}

echo "\n=== Investigation Complete ===\n";
