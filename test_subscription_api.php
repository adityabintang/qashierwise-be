<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Subscription;
use App\Services\SubscriptionService;

echo "=== Testing Subscription API Response ===\n\n";

// Get user with ID 1
$user = User::find(1);
if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "User: {$user->email} (ID: {$user->id})\n\n";

// Check if user has subscription
$subscription = $user->subscription;
if ($subscription) {
    echo "✅ User has subscription in database:\n";
    echo "   - ID: {$subscription->id}\n";
    echo "   - Provider: {$subscription->provider}\n";
    echo "   - Plan: {$subscription->plan_name}\n";
    echo "   - Status: {$subscription->status}\n";
    echo "   - Period Start: {$subscription->current_period_start}\n";
    echo "   - Period End: {$subscription->current_period_end}\n";
    echo "   - Polar ID: " . ($subscription->polar_subscription_id ?? 'NULL') . "\n";
    echo "   - Midtrans ID: " . ($subscription->midtrans_subscription_id ?? 'NULL') . "\n";
} else {
    echo "❌ User has NO subscription in database\n";
}

echo "\n--- Testing SubscriptionService ---\n\n";

// Test SubscriptionService
$subscriptionService = app(SubscriptionService::class);
$status = $subscriptionService->getUserSubscriptionStatus($user);

echo "Subscription Status from Service:\n";
echo "   - Status: {$status->status}\n";
echo "   - Plan: {$status->planName}\n";
echo "   - Trial Days: " . ($status->trialDaysRemaining ?? 'N/A') . "\n";
echo "   - Period End: " . ($status->periodEnd ?? 'N/A') . "\n";

echo "\n--- API Response Format ---\n\n";
echo json_encode([
    'success' => true,
    'data' => [
        'subscription' => $status->toArray(),
    ],
], JSON_PRETTY_PRINT);

echo "\n\n=== Test Complete ===\n";
