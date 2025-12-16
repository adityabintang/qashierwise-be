<?php
/**
 * Test script untuk simulate Polar.sh subscription.canceled webhook
 * Jalankan: php test_cancel_webhook.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get current subscription
$user = \App\Models\User::find(1);
$subscription = $user->subscription;

if (!$subscription) {
    echo "User tidak punya subscription. Jalankan test_webhook.php dulu.\n";
    exit(1);
}

echo "Current subscription:\n";
echo "- ID: {$subscription->polar_subscription_id}\n";
echo "- Plan: {$subscription->plan_name}\n";
echo "- Status: {$subscription->status}\n";
echo "- Cancelled at: " . ($subscription->cancelled_at ?? 'null') . "\n\n";

// Simulate subscription.canceled webhook
$webhookPayload = [
    'type' => 'subscription.canceled',
    'data' => [
        'id' => $subscription->polar_subscription_id,
        'customer_id' => $subscription->polar_customer_id,
        'canceled_at' => now()->toIso8601String(),
    ],
];

echo "Simulating cancellation webhook...\n";
echo json_encode($webhookPayload, JSON_PRETTY_PRINT) . "\n\n";

// Process webhook
$subscriptionService = app(\App\Services\SubscriptionService::class);
$subscriptionService->processWebhookEvent($webhookPayload);

echo "Webhook processed!\n\n";

// Show updated status
$subscription->refresh();
$status = $subscriptionService->getUserSubscriptionStatus($user);

echo "Updated subscription status:\n";
echo "- Status: {$status->status}\n";
echo "- Plan: {$status->planName}\n";
echo "- Cancelled at: " . ($subscription->cancelled_at ?? 'null') . "\n";
echo "- Period end: " . ($status->periodEnd ? $status->periodEnd->format('Y-m-d H:i:s') : 'N/A') . "\n";
