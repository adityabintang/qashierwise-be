<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Subscription;

echo "=== Testing Local Subscription Cancellation ===\n\n";

// Find the user with subscription
$user = User::whereHas('subscription')->first();

if (!$user) {
    echo "❌ No user found with subscription\n";
    exit(1);
}

echo "✅ Found user: {$user->name} ({$user->email})\n\n";

$subscription = $user->subscription;
echo "Subscription Details BEFORE cancellation:\n";
echo "ID: {$subscription->id}\n";
echo "Status: {$subscription->status}\n";
echo "Provider: {$subscription->provider}\n";
echo "Midtrans Subscription ID: " . ($subscription->midtrans_subscription_id ?? 'NULL') . "\n";
echo "Plan: {$subscription->plan_name}\n";
echo "Amount: Rp " . number_format($subscription->amount, 0, ',', '.') . "\n";
echo "Cancelled at: " . ($subscription->cancelled_at ?? 'NULL') . "\n\n";

// Test cancellation logic
echo "Testing cancellation logic...\n";

if ($subscription->status === 'cancelled') {
    echo "⚠️ Subscription is already cancelled\n";
    exit(0);
}

try {
    // Simulate the controller logic
    if ($subscription->provider === 'midtrans' && !empty($subscription->midtrans_subscription_id)) {
        echo "Would call Midtrans API to cancel subscription\n";
    } else {
        echo "No Midtrans ID - cancelling locally only\n";
    }
    
    // Update subscription
    $subscription->update([
        'status' => 'cancelled',
        'cancelled_at' => now(),
    ]);
    
    echo "✅ Subscription cancelled successfully!\n\n";
    
    // Show updated details
    $subscription->refresh();
    echo "Subscription Details AFTER cancellation:\n";
    echo "ID: {$subscription->id}\n";
    echo "Status: {$subscription->status}\n";
    echo "Cancelled at: {$subscription->cancelled_at}\n";
    echo "Period end: {$subscription->current_period_end}\n";
    
    echo "\n✅ User can continue using service until: {$subscription->current_period_end}\n";
    
} catch (\Exception $e) {
    echo "❌ Exception occurred: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
