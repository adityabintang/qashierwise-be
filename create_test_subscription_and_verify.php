<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Subscription;
use App\Services\SubscriptionService;

echo "=== Creating Test Subscription and Verifying API ===\n\n";

// Get user
$user = User::find(1);
if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "User: {$user->email} (ID: {$user->id})\n\n";

// Create Midtrans subscription
echo "1. Creating Midtrans subscription...\n";
$subscription = Subscription::create([
    'user_id' => $user->id,
    'provider' => 'midtrans',
    'plan_name' => 'standard',
    'status' => 'active',
    'current_period_start' => now(),
    'current_period_end' => now()->addMonth(),
    'metadata' => json_encode([
        'order_id' => 'SUB-TEST-PERSISTENT-' . time(),
        'transaction_id' => 'TXN-TEST-' . time(),
        'amount' => '99000',
        'currency' => 'IDR',
        'payment_type' => 'credit_card',
    ]),
]);

echo "   ✅ Subscription created (ID: {$subscription->id})\n";
echo "   - Provider: {$subscription->provider}\n";
echo "   - Plan: {$subscription->plan_name}\n";
echo "   - Status: {$subscription->status}\n\n";

// Test SubscriptionService
echo "2. Testing SubscriptionService...\n";
$subscriptionService = app(SubscriptionService::class);
$status = $subscriptionService->getUserSubscriptionStatus($user);

echo "   Status: {$status->status}\n";
echo "   Plan: {$status->planName}\n";
echo "   Period End: " . ($status->periodEnd ?? 'N/A') . "\n\n";

// Test API response format
echo "3. API Response Format:\n";
$apiResponse = [
    'success' => true,
    'data' => [
        'subscription' => $status->toArray(),
    ],
];
echo json_encode($apiResponse, JSON_PRETTY_PRINT) . "\n\n";

// Verify dashboard will show correct data
echo "4. Dashboard Display:\n";
if ($status->status === 'active') {
    echo "   ✅ Dashboard should show: Active subscription\n";
    echo "   ✅ Plan badge: Standard\n";
    echo "   ✅ Next billing: " . $status->periodEnd->format('M d, Y') . "\n";
} else {
    echo "   ❌ Dashboard will show: {$status->status}\n";
}

echo "\n=== Test Complete ===\n";
echo "\nNOTE: This subscription will persist in the database.\n";
echo "To remove it, run: php artisan tinker --execute=\"\\App\\Models\\Subscription::where('user_id', 1)->delete();\"\n";
