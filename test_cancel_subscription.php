<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Subscription;

echo "=== Testing Subscription Cancellation ===\n\n";

// Find a user with an active subscription
$user = User::whereHas('subscription', function ($query) {
    $query->where('status', 'active')
          ->where('provider', 'midtrans')
          ->whereNotNull('midtrans_subscription_id');
})->first();

if (!$user) {
    echo "❌ No user found with active Midtrans subscription\n";
    echo "\nLet's check all subscriptions:\n";
    
    $subscriptions = Subscription::with('user')->get();
    
    if ($subscriptions->isEmpty()) {
        echo "❌ No subscriptions found in database\n";
    } else {
        echo "Found " . $subscriptions->count() . " subscription(s):\n\n";
        
        foreach ($subscriptions as $sub) {
            echo "Subscription ID: {$sub->id}\n";
            echo "User: {$sub->user->name} ({$sub->user->email})\n";
            echo "Status: {$sub->status}\n";
            echo "Provider: {$sub->provider}\n";
            echo "Midtrans ID: " . ($sub->midtrans_subscription_id ?? 'NULL') . "\n";
            echo "Plan: {$sub->plan_name}\n";
            echo "Amount: Rp " . number_format($sub->amount, 0, ',', '.') . "\n";
            echo "Period: {$sub->current_period_start} to {$sub->current_period_end}\n";
            echo "Cancelled at: " . ($sub->cancelled_at ?? 'NULL') . "\n";
            echo "---\n\n";
        }
    }
    
    exit(1);
}

echo "✅ Found user with active subscription:\n";
echo "User: {$user->name} ({$user->email})\n";
echo "User ID: {$user->id}\n\n";

$subscription = $user->subscription;
echo "Subscription Details:\n";
echo "ID: {$subscription->id}\n";
echo "Status: {$subscription->status}\n";
echo "Provider: {$subscription->provider}\n";
echo "Midtrans Subscription ID: {$subscription->midtrans_subscription_id}\n";
echo "Plan: {$subscription->plan_name}\n";
echo "Amount: Rp " . number_format($subscription->amount, 0, ',', '.') . "\n";
echo "Period: {$subscription->current_period_start} to {$subscription->current_period_end}\n\n";

// Check Midtrans configuration
echo "Checking Midtrans Configuration:\n";
$serverKey = config('midtrans.server_key');
$isProduction = config('midtrans.is_production');
$environment = $isProduction ? 'Production' : 'Sandbox';

if (empty($serverKey)) {
    echo "❌ Midtrans server key is not configured\n";
    exit(1);
}

echo "✅ Midtrans server key: " . substr($serverKey, 0, 10) . "...\n";
echo "✅ Environment: {$environment}\n\n";

// Test the cancellation
echo "Testing cancellation...\n";

try {
    $midtransService = app(\App\Services\MidtransSubscriptionService::class);
    
    echo "Calling Midtrans API to cancel subscription...\n";
    $success = $midtransService->cancelSubscription($subscription->midtrans_subscription_id);
    
    if ($success) {
        echo "✅ Midtrans API call successful\n\n";
        
        echo "Updating local subscription status...\n";
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
        
        echo "✅ Subscription cancelled successfully!\n\n";
        
        echo "Updated Subscription:\n";
        $subscription->refresh();
        echo "Status: {$subscription->status}\n";
        echo "Cancelled at: {$subscription->cancelled_at}\n";
        
    } else {
        echo "❌ Midtrans API call failed\n";
        echo "Check the logs for more details\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Exception occurred: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
