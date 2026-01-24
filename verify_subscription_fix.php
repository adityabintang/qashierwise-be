<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== Midtrans Subscription Fix Verification ===\n\n";

// Check migration status
echo "1. Checking migration status...\n";
$migration = DB::table('migrations')
    ->where('migration', 'like', '%make_polar_fields_nullable%')
    ->first();

if ($migration) {
    echo "   ✅ Migration applied: {$migration->migration}\n";
} else {
    echo "   ❌ Migration not found!\n";
    exit(1);
}

// Check table schema
echo "\n2. Checking table schema...\n";
$columns = DB::select("
    SELECT column_name, is_nullable, data_type 
    FROM information_schema.columns 
    WHERE table_name = 'subscriptions' 
    AND column_name IN ('polar_subscription_id', 'polar_customer_id', 'provider')
    ORDER BY column_name
");

foreach ($columns as $column) {
    $nullable = $column->is_nullable === 'YES' ? '✅ nullable' : '❌ NOT NULL';
    echo "   - {$column->column_name}: {$column->data_type} ({$nullable})\n";
}

// Test Midtrans subscription creation
echo "\n3. Testing Midtrans subscription creation...\n";
$user = User::first();
if (!$user) {
    echo "   ❌ No users found\n";
    exit(1);
}

try {
    $subscription = Subscription::create([
        'user_id' => $user->id,
        'provider' => 'midtrans',
        'plan_name' => 'standard',
        'status' => 'active',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'metadata' => json_encode([
            'order_id' => 'VERIFY-TEST-' . time(),
            'amount' => '99000',
        ]),
    ]);
    
    echo "   ✅ Midtrans subscription created (ID: {$subscription->id})\n";
    echo "      - Provider: {$subscription->provider}\n";
    echo "      - Polar ID: " . ($subscription->polar_subscription_id ?? 'NULL') . "\n";
    echo "      - Midtrans ID: " . ($subscription->midtrans_subscription_id ?? 'NULL') . "\n";
    
    // Clean up
    $subscription->delete();
    echo "   ✅ Test subscription cleaned up\n";
    
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test Polar subscription creation (should still work)
echo "\n4. Testing Polar subscription creation...\n";
try {
    $subscription = Subscription::create([
        'user_id' => $user->id,
        'provider' => 'polar',
        'polar_subscription_id' => 'polar_test_' . time(),
        'polar_customer_id' => 'cust_test_' . time(),
        'plan_name' => 'pro',
        'status' => 'active',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);
    
    echo "   ✅ Polar subscription created (ID: {$subscription->id})\n";
    echo "      - Provider: {$subscription->provider}\n";
    echo "      - Polar ID: {$subscription->polar_subscription_id}\n";
    
    // Clean up
    $subscription->delete();
    echo "   ✅ Test subscription cleaned up\n";
    
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Check existing subscriptions
echo "\n5. Checking existing subscriptions...\n";
$subscriptions = Subscription::all();
echo "   Total subscriptions: {$subscriptions->count()}\n";

if ($subscriptions->count() > 0) {
    $byProvider = $subscriptions->groupBy('provider');
    foreach ($byProvider as $provider => $subs) {
        echo "   - {$provider}: {$subs->count()}\n";
    }
}

echo "\n=== ✅ All Verifications Passed! ===\n";
echo "\nThe Midtrans subscription fix is working correctly:\n";
echo "- Migration applied successfully\n";
echo "- Polar fields are now nullable\n";
echo "- Midtrans subscriptions can be created\n";
echo "- Polar subscriptions still work\n";
echo "- Multi-provider support is fully functional\n";
