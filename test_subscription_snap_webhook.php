<?php

/**
 * Test script for Midtrans Snap Subscription Webhook
 * 
 * This script simulates a Midtrans webhook notification for a successful
 * subscription payment to verify the webhook handler is working correctly.
 * 
 * Usage: php test_subscription_snap_webhook.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Configuration
$baseUrl = env('APP_URL', 'http://localhost:8000');
$webhookUrl = "{$baseUrl}/api/webhooks/midtrans/subscription";
$serverKey = env('MIDTRANS_SERVER_KEY', '');

// Test user ID (change this to an actual user ID from your database)
$testUserId = 1;
$testPlanId = 'standard'; // or 'pro'

// Generate test order ID
$orderId = "SUB-{$testUserId}-" . time() . "-test";
$transactionId = "TXN-" . time();
$grossAmount = 99000; // Standard plan price

// Calculate signature
$statusCode = '200';
$signatureString = $orderId . $statusCode . $grossAmount . $serverKey;
$signatureKey = hash('sha512', $signatureString);

// Build webhook payload
$payload = [
    'transaction_time' => date('Y-m-d H:i:s'),
    'transaction_status' => 'settlement',
    'transaction_id' => $transactionId,
    'status_message' => 'midtrans payment notification',
    'status_code' => $statusCode,
    'signature_key' => $signatureKey,
    'payment_type' => 'credit_card',
    'order_id' => $orderId,
    'merchant_id' => env('MIDTRANS_MERCHANT_ID', ''),
    'gross_amount' => (string) $grossAmount,
    'fraud_status' => 'accept',
    'currency' => 'IDR',
    
    // Custom fields for subscription
    'custom_field1' => $testPlanId,
    'custom_field2' => 'subscription',
    'custom_field3' => (string) $testUserId,
];

echo "=== Midtrans Snap Subscription Webhook Test ===\n\n";
echo "Webhook URL: {$webhookUrl}\n";
echo "Order ID: {$orderId}\n";
echo "Transaction ID: {$transactionId}\n";
echo "User ID: {$testUserId}\n";
echo "Plan ID: {$testPlanId}\n";
echo "Amount: Rp " . number_format($grossAmount, 0, ',', '.') . "\n";
echo "\nPayload:\n";
echo json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

echo "Sending webhook notification...\n";

try {
    $response = Http::timeout(30)
        ->withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
        ->post($webhookUrl, $payload);

    echo "\n=== Response ===\n";
    echo "Status Code: " . $response->status() . "\n";
    echo "Body: " . $response->body() . "\n\n";

    if ($response->successful()) {
        echo "✅ Webhook processed successfully!\n\n";
        
        // Check if subscription was created
        echo "Checking subscription in database...\n";
        $subscription = \App\Models\Subscription::where('user_id', $testUserId)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($subscription) {
            echo "✅ Subscription found!\n";
            echo "   - ID: {$subscription->id}\n";
            echo "   - Plan: {$subscription->plan_name}\n";
            echo "   - Status: {$subscription->status}\n";
            echo "   - Provider: {$subscription->provider}\n";
            echo "   - Period Start: {$subscription->current_period_start}\n";
            echo "   - Period End: {$subscription->current_period_end}\n";
            
            $metadata = json_decode($subscription->metadata, true);
            if ($metadata) {
                echo "   - Order ID: " . ($metadata['order_id'] ?? 'N/A') . "\n";
                echo "   - Transaction ID: " . ($metadata['transaction_id'] ?? 'N/A') . "\n";
            }
        } else {
            echo "❌ No subscription found for user {$testUserId}\n";
            echo "   Check logs for errors: storage/logs/laravel.log\n";
        }
    } else {
        echo "❌ Webhook failed with status: " . $response->status() . "\n";
        echo "   Response: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "Check logs for detailed information: storage/logs/laravel.log\n";
