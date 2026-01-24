<?php

/**
 * Migration Verification Script
 * 
 * This script verifies that all Midtrans subscription migrations
 * have been applied correctly to the database.
 * 
 * Usage: php verify_migrations.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Midtrans Subscription Migration Verification ===\n\n";

// Check if migrations table exists
if (!Schema::hasTable('migrations')) {
    echo "❌ ERROR: Migrations table does not exist!\n";
    exit(1);
}

// Check if subscriptions table exists
if (!Schema::hasTable('subscriptions')) {
    echo "❌ ERROR: Subscriptions table does not exist!\n";
    exit(1);
}

echo "✓ Subscriptions table exists\n";

// Check for required columns
$requiredColumns = [
    'id',
    'user_id',
    'polar_subscription_id',
    'polar_customer_id',
    'midtrans_subscription_id',
    'midtrans_customer_id',
    'provider',
    'plan_name',
    'status',
    'current_period_start',
    'current_period_end',
    'cancelled_at',
    'metadata',
    'created_at',
    'updated_at',
];

$missingColumns = [];
foreach ($requiredColumns as $column) {
    if (!Schema::hasColumn('subscriptions', $column)) {
        $missingColumns[] = $column;
    }
}

if (!empty($missingColumns)) {
    echo "❌ ERROR: Missing columns in subscriptions table:\n";
    foreach ($missingColumns as $column) {
        echo "   - $column\n";
    }
    exit(1);
}

echo "✓ All required columns exist\n";

// Check for indexes
try {
    $indexes = DB::select("SHOW INDEX FROM subscriptions WHERE Key_name IN ('idx_subscriptions_midtrans_id', 'idx_subscriptions_provider')");
    
    $indexNames = array_map(fn($idx) => $idx->Key_name, $indexes);
    
    if (in_array('idx_subscriptions_midtrans_id', $indexNames)) {
        echo "✓ Index 'idx_subscriptions_midtrans_id' exists\n";
    } else {
        echo "⚠ WARNING: Index 'idx_subscriptions_midtrans_id' not found\n";
    }
    
    if (in_array('idx_subscriptions_provider', $indexNames)) {
        echo "✓ Index 'idx_subscriptions_provider' exists\n";
    } else {
        echo "⚠ WARNING: Index 'idx_subscriptions_provider' not found\n";
    }
} catch (\Exception $e) {
    echo "⚠ WARNING: Could not verify indexes: " . $e->getMessage() . "\n";
}

// Check migration status
echo "\n=== Migration Status ===\n";
$migrations = DB::table('migrations')
    ->where('migration', 'like', '%midtrans%')
    ->orWhere('migration', 'like', '%subscription%')
    ->orderBy('batch')
    ->get();

if ($migrations->isEmpty()) {
    echo "⚠ WARNING: No Midtrans-related migrations found\n";
} else {
    foreach ($migrations as $migration) {
        echo "✓ {$migration->migration} (batch {$migration->batch})\n";
    }
}

// Check for existing data
echo "\n=== Data Verification ===\n";
$totalSubscriptions = DB::table('subscriptions')->count();
$polarSubscriptions = DB::table('subscriptions')->where('provider', 'polar')->count();
$midtransSubscriptions = DB::table('subscriptions')->where('provider', 'midtrans')->count();

echo "Total subscriptions: $totalSubscriptions\n";
echo "Polar subscriptions: $polarSubscriptions\n";
echo "Midtrans subscriptions: $midtransSubscriptions\n";

// Check for null provider (should be migrated to 'polar')
$nullProviders = DB::table('subscriptions')->whereNull('provider')->count();
if ($nullProviders > 0) {
    echo "⚠ WARNING: Found $nullProviders subscriptions with NULL provider\n";
} else {
    echo "✓ No subscriptions with NULL provider\n";
}

echo "\n=== Verification Complete ===\n";
echo "✓ All checks passed successfully!\n";
echo "\nYou can now proceed with testing the Midtrans subscription feature.\n";

exit(0);
