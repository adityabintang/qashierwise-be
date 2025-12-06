<?php
/**
 * Test script untuk simulate Polar.sh webhook
 * Jalankan: php test_webhook.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate subscription.created webhook
$webhookPayload = [
    'type' => 'subscription.created',
    'data' => [
        'id' => 'sub_test_' . uniqid(),
        'customer_id' => 'cus_test_' . uniqid(),
        'product_id' => config('polar.products.standard'), // Ubah ke 'standard' jika mau test Standard
        'status' => 'active',
        'current_period_start' => now()->toIso8601String(),
        'current_period_end' => now()->addMonth()->toIso8601String(),
        'metadata' => [
            'user_id' => '1', // Ganti dengan user ID yang mau di-test
            'plan_id' => 'standard', // Ubah ke 'standard' jika mau test Standard
        ],
    ],
];

echo "Testing webhook with payload:\n";
echo json_encode($webhookPayload, JSON_PRETTY_PRINT) . "\n\n";

// Process webhook
$subscriptionService = app(\App\Services\SubscriptionService::class);
$subscriptionService->processWebhookEvent($webhookPayload);

echo "\nWebhook processed! Check database for subscription record.\n";

// Show subscription status
$user = \App\Models\User::find($webhookPayload['data']['metadata']['user_id']);
if ($user) {
    $status = $subscriptionService->getUserSubscriptionStatus($user);
    echo "\nUser subscription status:\n";
    echo "Status: {$status->status}\n";
    echo "Plan: {$status->planName}\n";
    echo "Period End: " . ($status->periodEnd ? $status->periodEnd->format('Y-m-d H:i:s') : 'N/A') . "\n";
}
