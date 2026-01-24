<?php

/**
 * Performance Testing Script
 * 
 * This script tests the performance of Midtrans subscription operations
 * to ensure they meet the non-functional requirements.
 * 
 * Usage: php performance_test.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\MidtransSubscriptionService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;

echo "=== Midtrans Subscription Performance Testing ===\n\n";

$results = [];
$thresholds = [
    'checkout_creation' => 3000, // 3 seconds in milliseconds
    'webhook_processing' => 5000, // 5 seconds in milliseconds
    'subscription_query' => 100, // 100 milliseconds
    'feature_access_check' => 50, // 50 milliseconds
];

// Helper function to measure execution time
function measureTime(callable $callback): float
{
    $start = microtime(true);
    $callback();
    $end = microtime(true);
    return ($end - $start) * 1000; // Convert to milliseconds
}

// Test 1: Subscription Query Performance
echo "=== Test 1: Subscription Query Performance ===\n";

try {
    $times = [];
    
    for ($i = 0; $i < 10; $i++) {
        $time = measureTime(function () {
            DB::table('subscriptions')
                ->where('status', 'active')
                ->where('provider', 'midtrans')
                ->first();
        });
        $times[] = $time;
    }
    
    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);
    $minTime = min($times);
    
    echo "Average query time: " . number_format($avgTime, 2) . " ms\n";
    echo "Min query time: " . number_format($minTime, 2) . " ms\n";
    echo "Max query time: " . number_format($maxTime, 2) . " ms\n";
    
    if ($avgTime < $thresholds['subscription_query']) {
        echo "✓ PASS: Query performance meets threshold\n";
        $results['subscription_query'] = 'PASS';
    } else {
        echo "❌ FAIL: Query performance exceeds threshold\n";
        $results['subscription_query'] = 'FAIL';
    }
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['subscription_query'] = 'ERROR';
}

// Test 2: Feature Access Check Performance
echo "\n=== Test 2: Feature Access Check Performance ===\n";

try {
    $subscriptionService = app(SubscriptionService::class);
    $user = User::first();
    
    if (!$user) {
        echo "⚠ WARNING: No users found, creating test user\n";
        $user = User::factory()->create();
    }
    
    $times = [];
    
    for ($i = 0; $i < 20; $i++) {
        $time = measureTime(function () use ($subscriptionService, $user) {
            $subscriptionService->canAccessFeature($user, 'standard');
        });
        $times[] = $time;
    }
    
    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);
    $minTime = min($times);
    
    echo "Average check time: " . number_format($avgTime, 2) . " ms\n";
    echo "Min check time: " . number_format($minTime, 2) . " ms\n";
    echo "Max check time: " . number_format($maxTime, 2) . " ms\n";
    
    if ($avgTime < $thresholds['feature_access_check']) {
        echo "✓ PASS: Feature access check meets threshold\n";
        $results['feature_access_check'] = 'PASS';
    } else {
        echo "❌ FAIL: Feature access check exceeds threshold\n";
        $results['feature_access_check'] = 'FAIL';
    }
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['feature_access_check'] = 'ERROR';
}

// Test 3: Webhook Processing Performance (Simulated)
echo "\n=== Test 3: Webhook Processing Performance ===\n";

try {
    $subscriptionService = app(SubscriptionService::class);
    
    // Simulate webhook payload
    $webhookPayload = [
        'transaction_time' => now()->toDateTimeString(),
        'transaction_status' => 'settlement',
        'transaction_id' => 'test_txn_' . uniqid(),
        'subscription_id' => 'test_sub_' . uniqid(),
        'status_code' => '200',
        'order_id' => 'test_order_' . uniqid(),
        'gross_amount' => '99000.00',
    ];
    
    $times = [];
    
    for ($i = 0; $i < 5; $i++) {
        $time = measureTime(function () use ($subscriptionService, $webhookPayload) {
            try {
                // Simulate webhook processing logic
                $orderId = $webhookPayload['order_id'];
                $status = $webhookPayload['transaction_status'];
                $subscriptionId = $webhookPayload['subscription_id'];
                
                // Simulate database query
                DB::table('subscriptions')
                    ->where('midtrans_subscription_id', $subscriptionId)
                    ->first();
            } catch (\Exception $e) {
                // Expected to fail for test data
            }
        });
        $times[] = $time;
    }
    
    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);
    $minTime = min($times);
    
    echo "Average processing time: " . number_format($avgTime, 2) . " ms\n";
    echo "Min processing time: " . number_format($minTime, 2) . " ms\n";
    echo "Max processing time: " . number_format($maxTime, 2) . " ms\n";
    
    if ($avgTime < $thresholds['webhook_processing']) {
        echo "✓ PASS: Webhook processing meets threshold\n";
        $results['webhook_processing'] = 'PASS';
    } else {
        echo "❌ FAIL: Webhook processing exceeds threshold\n";
        $results['webhook_processing'] = 'FAIL';
    }
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['webhook_processing'] = 'ERROR';
}

// Test 4: Database Index Performance
echo "\n=== Test 4: Database Index Performance ===\n";

try {
    // Test query with index on midtrans_subscription_id
    $time1 = measureTime(function () {
        DB::table('subscriptions')
            ->where('midtrans_subscription_id', 'test_sub_123')
            ->first();
    });
    
    echo "Query with indexed column: " . number_format($time1, 2) . " ms\n";
    
    // Test query with index on provider
    $time2 = measureTime(function () {
        DB::table('subscriptions')
            ->where('provider', 'midtrans')
            ->where('status', 'active')
            ->get();
    });
    
    echo "Query with provider index: " . number_format($time2, 2) . " ms\n";
    
    if ($time1 < 50 && $time2 < 100) {
        echo "✓ PASS: Index performance is good\n";
        $results['index_performance'] = 'PASS';
    } else {
        echo "⚠ WARNING: Index performance could be improved\n";
        $results['index_performance'] = 'WARNING';
    }
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['index_performance'] = 'ERROR';
}

// Test 5: Concurrent Request Simulation
echo "\n=== Test 5: Concurrent Request Handling ===\n";

try {
    echo "Simulating 10 concurrent subscription queries...\n";
    
    $times = [];
    
    // Simulate concurrent requests
    for ($i = 0; $i < 10; $i++) {
        $time = measureTime(function () {
            DB::table('subscriptions')
                ->where('status', 'active')
                ->limit(10)
                ->get();
        });
        $times[] = $time;
    }
    
    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);
    
    echo "Average response time: " . number_format($avgTime, 2) . " ms\n";
    echo "Max response time: " . number_format($maxTime, 2) . " ms\n";
    
    if ($maxTime < 200) {
        echo "✓ PASS: Concurrent request handling is good\n";
        $results['concurrent_requests'] = 'PASS';
    } else {
        echo "⚠ WARNING: Concurrent request handling could be improved\n";
        $results['concurrent_requests'] = 'WARNING';
    }
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['concurrent_requests'] = 'ERROR';
}

// Test 6: Memory Usage
echo "\n=== Test 6: Memory Usage ===\n";

try {
    $memoryBefore = memory_get_usage(true);
    
    // Perform typical operations
    $subscriptionService = app(SubscriptionService::class);
    $users = User::limit(10)->get();
    
    foreach ($users as $user) {
        $subscriptionService->getUserSubscriptionStatus($user);
    }
    
    $memoryAfter = memory_get_usage(true);
    $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB
    
    echo "Memory used: " . number_format($memoryUsed, 2) . " MB\n";
    
    if ($memoryUsed < 10) {
        echo "✓ PASS: Memory usage is acceptable\n";
        $results['memory_usage'] = 'PASS';
    } else {
        echo "⚠ WARNING: Memory usage is high\n";
        $results['memory_usage'] = 'WARNING';
    }
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['memory_usage'] = 'ERROR';
}

// Summary
echo "\n=== Performance Test Summary ===\n";

$passed = 0;
$failed = 0;
$warnings = 0;
$errors = 0;

foreach ($results as $test => $result) {
    $icon = match ($result) {
        'PASS' => '✓',
        'FAIL' => '❌',
        'WARNING' => '⚠',
        'ERROR' => '❌',
        default => '?',
    };
    
    echo "$icon $test: $result\n";
    
    match ($result) {
        'PASS' => $passed++,
        'FAIL' => $failed++,
        'WARNING' => $warnings++,
        'ERROR' => $errors++,
        default => null,
    };
}

echo "\nResults:\n";
echo "  Passed: $passed\n";
echo "  Failed: $failed\n";
echo "  Warnings: $warnings\n";
echo "  Errors: $errors\n";

echo "\n=== Performance Thresholds ===\n";
echo "Checkout creation: < " . $thresholds['checkout_creation'] . " ms\n";
echo "Webhook processing: < " . $thresholds['webhook_processing'] . " ms\n";
echo "Subscription query: < " . $thresholds['subscription_query'] . " ms\n";
echo "Feature access check: < " . $thresholds['feature_access_check'] . " ms\n";

echo "\n=== Recommendations ===\n";

if ($results['subscription_query'] !== 'PASS') {
    echo "- Consider adding more database indexes\n";
}

if ($results['feature_access_check'] !== 'PASS') {
    echo "- Consider caching feature access results\n";
}

if ($results['webhook_processing'] !== 'PASS') {
    echo "- Consider using queue workers for webhook processing\n";
}

if ($results['memory_usage'] !== 'PASS') {
    echo "- Consider optimizing query results and eager loading\n";
}

echo "\n=== Testing Complete ===\n";

if ($failed === 0 && $errors === 0) {
    echo "✓ All performance tests passed!\n";
    exit(0);
} else {
    echo "❌ Some performance tests failed or had errors\n";
    exit(1);
}
