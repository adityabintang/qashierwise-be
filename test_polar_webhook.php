<?php
/**
 * Test script to debug Polar webhook signature validation
 * Run: php test_polar_webhook.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\PolarService;
use App\Services\PlanConfig;
use App\Models\Subscription;
use App\Models\User;

echo "=== Polar Webhook Debug Tool ===\n\n";

// 1. Check configuration
echo "1. Checking configuration...\n";
$webhookSecret = config('polar.webhook_secret');
$apiToken = config('polar.api_token');
$sandbox = config('polar.sandbox');
$productStandard = config('polar.products.standard');

echo "   - POLAR_WEBHOOK_SECRET: " . ($webhookSecret ? substr($webhookSecret, 0, 10) . '...' : 'NOT SET') . "\n";
echo "   - POLAR_API_TOKEN: " . ($apiToken ? 'SET' : 'NOT SET') . "\n";
echo "   - POLAR_SANDBOX: " . ($sandbox ? 'true' : 'false') . "\n";
echo "   - POLAR_PRODUCT_STANDARD: " . ($productStandard ?: 'NOT SET') . "\n\n";

// 2. Check database for subscriptions
echo "2. Checking subscriptions in database...\n";
$subscriptions = Subscription::with('user')->get();
if ($subscriptions->isEmpty()) {
    echo "   - No subscriptions found in database\n";
} else {
    foreach ($subscriptions as $sub) {
        $userEmail = $sub->user ? $sub->user->email : 'N/A';
        echo "   - ID: {$sub->id}, User: {$sub->user_id} ({$userEmail}), Plan: {$sub->plan_name}, Status: {$sub->status}\n";
    }
}
echo "\n";

// 3. Check users
echo "3. Checking users...\n";
$users = User::take(5)->get();
foreach ($users as $user) {
    $hasSub = $user->subscription ? 'Yes' : 'No';
    echo "   - ID: {$user->id}, Email: {$user->email}, Has Subscription: {$hasSub}\n";
}
echo "\n";

// 4. Test signature validation with sample
echo "4. Testing signature validation...\n";
$polarService = app(PolarService::class);

// Simulate a webhook payload
$testPayload = json_encode([
    'type' => 'subscription.created',
    'data' => [
        'id' => 'test-sub-id',
        'customer_id' => 'test-customer-id',
        'product_id' => $productStandard,
        'status' => 'active',
        'metadata' => ['user_id' => '1', 'plan_id' => 'standard'],
    ]
]);

// Standard Webhooks format
$webhookId = 'msg_' . uniqid();
$timestamp = (string) time();

// Decode secret if it has whsec_ prefix
$secretKey = $webhookSecret;
if (str_starts_with($webhookSecret, 'whsec_')) {
    $secretKey = base64_decode(substr($webhookSecret, 6));
    echo "   - Secret has whsec_ prefix, decoded\n";
}

// Generate signature: HMAC-SHA256(webhook_id.timestamp.payload, secret)
$signedPayload = "{$webhookId}.{$timestamp}.{$testPayload}";
$expectedSig = base64_encode(hash_hmac('sha256', $signedPayload, $secretKey, true));
$testSignature = "v1," . $expectedSig;

echo "   - Webhook ID: {$webhookId}\n";
echo "   - Timestamp: {$timestamp}\n";
echo "   - Test payload: " . substr($testPayload, 0, 50) . "...\n";
echo "   - Generated signature: {$testSignature}\n";

$isValid = $polarService->validateWebhookSignature($testPayload, $testSignature, $webhookId, $timestamp);
echo "   - Validation result: " . ($isValid ? 'VALID ✓' : 'INVALID ✗') . "\n\n";

// 5. Instructions
echo "5. Next steps:\n";
echo "   - Check Polar sandbox webhook settings\n";
echo "   - Ensure webhook URL is: https://api.qashierwise.com/api/webhooks/polar\n";
echo "   - Ensure webhook secret matches POLAR_WEBHOOK_SECRET in .env\n";
echo "   - Check Laravel logs: storage/logs/laravel.log\n";
echo "\n";

// 6. Manual subscription creation test
echo "6. Would you like to manually create a subscription for testing? (Check code)\n";
echo "   Uncomment the code below and run again to create test subscription.\n\n";

/*
// Uncomment to manually create subscription for user ID 1
$user = User::find(1);
if ($user) {
    $subscription = Subscription::updateOrCreate(
        ['user_id' => $user->id],
        [
            'polar_subscription_id' => 'manual-test-' . time(),
            'polar_customer_id' => 'manual-customer-' . time(),
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]
    );
    echo "   Created subscription ID: {$subscription->id}\n";
}
*/

echo "=== Done ===\n";
