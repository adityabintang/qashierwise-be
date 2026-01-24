<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Subscription;
use App\Models\User;

echo "Testing Midtrans subscription creation...\n\n";

// Get first user
$user = User::first();
if (!$user) {
    echo "No users found. Please create a user first.\n";
    exit(1);
}

echo "User ID: {$user->id}\n";
echo "User Email: {$user->email}\n\n";

// Test data
$subscriptionData = [
    'user_id' => $user->id,
    'provider' => 'midtrans',
    'plan_name' => 'standard',
    'status' => 'active',
    'current_period_start' => now(),
    'current_period_end' => now()->addMonth(),
    'metadata' => json_encode([
        'order_id' => 'SUB-TEST-' . time(),
        'transaction_id' => 'TXN-TEST-' . time(),
        'amount' => '99000',
        'currency' => 'IDR',
        'payment_type' => 'credit_card',
    ]),
];

try {
    echo "Creating Midtrans subscription...\n";
    $subscription = Subscription::create($subscriptionData);
    
    echo "✓ Success! Subscription created:\n";
    echo "  ID: {$subscription->id}\n";
    echo "  Provider: {$subscription->provider}\n";
    echo "  Plan: {$subscription->plan_name}\n";
    echo "  Status: {$subscription->status}\n";
    echo "  Polar Subscription ID: " . ($subscription->polar_subscription_id ?? 'NULL') . "\n";
    echo "  Midtrans Subscription ID: " . ($subscription->midtrans_subscription_id ?? 'NULL') . "\n";
    echo "\n";
    
    // Clean up
    echo "Cleaning up test subscription...\n";
    $subscription->delete();
    echo "✓ Test subscription deleted\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n✓ All tests passed!\n";
