<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Subscription;

echo "=== Analyzing Subscription Metadata ===\n\n";

$subscription = Subscription::find(7);

echo "Subscription ID: {$subscription->id}\n";
echo "Created at: {$subscription->created_at}\n";
echo "Amount: {$subscription->amount}\n\n";

echo "Raw Metadata:\n";
echo $subscription->metadata . "\n\n";

$metadata = json_decode($subscription->metadata, true);

if ($metadata) {
    echo "Parsed Metadata:\n";
    foreach ($metadata as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
    echo "\n";
    
    // Analyze the metadata
    echo "=== Analysis ===\n\n";
    
    if (isset($metadata['order_id'])) {
        echo "✅ Order ID: {$metadata['order_id']}\n";
        
        // Check if this is a test order
        if (strpos($metadata['order_id'], 'test') !== false) {
            echo "⚠️ This appears to be a TEST order (contains 'test' in order_id)\n";
        }
    }
    
    if (isset($metadata['transaction_id'])) {
        echo "✅ Transaction ID: {$metadata['transaction_id']}\n";
    }
    
    if (isset($metadata['amount'])) {
        echo "✅ Amount in metadata: Rp " . number_format($metadata['amount'], 0, ',', '.') . "\n";
        
        if ($subscription->amount != $metadata['amount']) {
            echo "⚠️ Amount mismatch! Subscription amount: {$subscription->amount}, Metadata amount: {$metadata['amount']}\n";
        }
    }
    
    if (isset($metadata['payment_type'])) {
        echo "✅ Payment type: {$metadata['payment_type']}\n";
    }
    
    echo "\n";
    
    // Check if this looks like a Snap transaction
    if (isset($metadata['order_id']) && !isset($metadata['subscription_id'])) {
        echo "🔍 FINDING: This looks like a Snap payment transaction, NOT a subscription!\n";
        echo "   - Has order_id and transaction_id\n";
        echo "   - Does NOT have subscription_id in metadata\n";
        echo "   - This explains why midtrans_subscription_id is NULL\n\n";
        
        echo "💡 EXPLANATION:\n";
        echo "   This subscription was likely created from a one-time Snap payment,\n";
        echo "   not from Midtrans Subscription API.\n\n";
        
        echo "   Midtrans has 2 different products:\n";
        echo "   1. Snap (one-time payments) - Creates transaction_id\n";
        echo "   2. Subscription API (recurring) - Creates subscription_id\n\n";
        
        echo "   Your current flow seems to be using Snap for subscription payments,\n";
        echo "   which is why you don't get a midtrans_subscription_id.\n\n";
    }
    
} else {
    echo "❌ Failed to parse metadata as JSON\n";
}

echo "=== Checking Webhook Handler ===\n\n";

// Let's check how the webhook creates subscriptions
$webhookController = app(\App\Http\Controllers\Api\MidtransWebhookController::class);

echo "Webhook controller exists: " . (class_exists(\App\Http\Controllers\Api\MidtransWebhookController::class) ? "✅" : "❌") . "\n";

echo "\n=== Conclusion ===\n\n";

echo "Based on the metadata analysis:\n\n";

echo "1. ⚠️ This subscription was created from a SNAP payment (one-time)\n";
echo "2. ⚠️ Snap payments don't have subscription_id from Midtrans\n";
echo "3. ⚠️ That's why midtrans_subscription_id is NULL\n\n";

echo "To properly implement recurring subscriptions, you need to:\n\n";

echo "Option A: Use Midtrans Subscription API\n";
echo "  - Create subscription through /v1/subscriptions endpoint\n";
echo "  - Get subscription_id from Midtrans\n";
echo "  - Store it in midtrans_subscription_id\n";
echo "  - Can cancel through API\n\n";

echo "Option B: Continue with Snap (current approach)\n";
echo "  - Use Snap for each payment\n";
echo "  - Manually track subscription status\n";
echo "  - Cannot cancel through Midtrans API\n";
echo "  - Need to handle recurring billing manually\n\n";

echo "Current implementation seems to be Option B (Snap-based)\n";
echo "but the code expects Option A (Subscription API)\n\n";

echo "=== Investigation Complete ===\n";
