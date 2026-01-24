<?php

/**
 * Test script untuk mensimulasikan Midtrans subscription webhook
 * 
 * Usage: php test_subscription_webhook.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Configuration
$baseUrl = env('APP_URL', 'http://127.0.0.1:8000');
$serverKey = env('MIDTRANS_SERVER_KEY');

// Find a test user
$user = User::first();

if (!$user) {
    echo "❌ No users found in database. Please create a user first.\n";
    exit(1);
}

echo "🧪 Testing Midtrans Subscription Webhook\n";
echo "========================================\n\n";

echo "📋 Test Configuration:\n";
echo "   Base URL: {$baseUrl}\n";
echo "   User ID: {$user->id}\n";
echo "   User Email: {$user->email}\n";
echo "   Plan: standard\n\n";

// Generate test order ID
$orderId = "SUB-{$user->id}-" . time() . "-test";
$grossAmount = "99000.00";
$statusCode = "200";

// Calculate signature
$signatureString = $orderId . $statusCode . $grossAmount . $serverKey;
$signature = hash('sha512', $signatureString);

// Build webhook payload (simulating successful payment)
$payload = [
    'transaction_time' => date('Y-m-d H:i:s'),
    'transaction_status' => 'settlement',
    'transaction_id' => 'test-' . uniqid(),
    'status_message' => 'midtrans payment notification',
    'status_code' => $statusCode,
    'signature_key' => $signature,
    'payment_type' => 'credit_card',
    'order_id' => $orderId,
    'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
    'gross_amount' => $grossAmount,
    'fraud_status' => 'accept',
    'currency' => 'IDR',
    'custom_field1' => 'standard',  // plan_id
    'custom_field2' => 'subscription',  // payment_type
    'custom_field3' => (string) $user->id,  // user_id
];

echo "📤 Sending webhook to: {$baseUrl}/api/webhooks/midtrans/subscription\n\n";
echo "📦 Payload:\n";
echo json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

try {
    // Send webhook request
    $response = Http::timeout(30)
        ->post("{$baseUrl}/api/webhooks/midtrans/subscription", $payload);

    echo "📥 Response:\n";
    echo "   Status: {$response->status()}\n";
    echo "   Body: " . $response->body() . "\n\n";

    if ($response->successful()) {
        echo "✅ Webhook sent successfully!\n\n";
        
        // Check if subscription was created
        $user->refresh();
        $subscription = $user->subscription;
        
        if ($subscription) {
            echo "✅ Subscription created/updated:\n";
            echo "   ID: {$subscription->id}\n";
            echo "   Plan: {$subscription->plan_name}\n";
            echo "   Status: {$subscription->status}\n";
            echo "   Provider: {$subscription->provider}\n";
            echo "   Period Start: {$subscription->current_period_start}\n";
            echo "   Period End: {$subscription->current_period_end}\n";
            
            if ($subscription->metadata) {
                echo "   Metadata: " . json_encode(json_decode($subscription->metadata), JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            echo "⚠️  Subscription not found. Check logs for errors.\n";
        }
    } else {
        echo "❌ Webhook failed with status: {$response->status()}\n";
        echo "   Error: {$response->body()}\n";
    }
} catch (\Exception $e) {
    echo "❌ Exception occurred:\n";
    echo "   Message: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n📊 Check logs for detailed information:\n";
echo "   tail -f storage/logs/laravel.log | grep subscription\n\n";
