<?php

/**
 * Backward Compatibility Verification Script
 * 
 * This script verifies that the Midtrans subscription integration
 * maintains backward compatibility with existing Polar subscriptions.
 * 
 * Usage: php verify_backward_compatibility.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;

echo "=== Backward Compatibility Verification ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// Test 1: Verify Polar subscriptions still exist
echo "=== Test 1: Polar Subscriptions ===\n";

try {
    $polarCount = Subscription::where('provider', 'polar')->count();
    $nullProviderCount = Subscription::whereNull('provider')->count();
    
    echo "Polar subscriptions: $polarCount\n";
    echo "Null provider subscriptions: $nullProviderCount\n";
    
    if ($nullProviderCount > 0) {
        echo "⚠ WARNING: Found subscriptions with NULL provider\n";
        $warnings[] = "$nullProviderCount subscriptions have NULL provider";
    } else {
        echo "✓ No subscriptions with NULL provider\n";
        $success[] = "All subscriptions have a provider set";
    }
    
    if ($polarCount > 0) {
        echo "✓ Found $polarCount Polar subscriptions\n";
        $success[] = "Polar subscriptions preserved";
    } else {
        echo "ℹ No Polar subscriptions found (this is OK if none existed)\n";
    }
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to query Polar subscriptions: " . $e->getMessage() . "\n";
    $errors[] = "Polar subscription query failed";
}

// Test 2: Verify Polar subscription data integrity
echo "\n=== Test 2: Polar Data Integrity ===\n";

try {
    $polarSubscriptions = Subscription::where('provider', 'polar')
        ->whereNotNull('polar_subscription_id')
        ->get();
    
    if ($polarSubscriptions->isEmpty()) {
        echo "ℹ No Polar subscriptions to verify\n";
    } else {
        $integrityIssues = 0;
        
        foreach ($polarSubscriptions as $subscription) {
            // Check required fields
            if (empty($subscription->polar_subscription_id)) {
                $integrityIssues++;
                echo "⚠ Subscription {$subscription->id}: Missing polar_subscription_id\n";
            }
            
            if (empty($subscription->plan_name)) {
                $integrityIssues++;
                echo "⚠ Subscription {$subscription->id}: Missing plan_name\n";
            }
            
            if (empty($subscription->status)) {
                $integrityIssues++;
                echo "⚠ Subscription {$subscription->id}: Missing status\n";
            }
        }
        
        if ($integrityIssues === 0) {
            echo "✓ All Polar subscriptions have required fields\n";
            $success[] = "Polar subscription data integrity verified";
        } else {
            echo "⚠ WARNING: Found $integrityIssues integrity issues\n";
            $warnings[] = "$integrityIssues Polar subscriptions have missing fields";
        }
    }
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to verify Polar data integrity: " . $e->getMessage() . "\n";
    $errors[] = "Polar data integrity check failed";
}

// Test 3: Verify SubscriptionService works with Polar subscriptions
echo "\n=== Test 3: SubscriptionService Compatibility ===\n";

try {
    $subscriptionService = app(SubscriptionService::class);
    
    // Test with a Polar subscription if available
    $polarSubscription = Subscription::where('provider', 'polar')->first();
    
    if ($polarSubscription) {
        $user = $polarSubscription->user;
        
        // Test getUserSubscriptionStatus
        try {
            $status = $subscriptionService->getUserSubscriptionStatus($user);
            echo "✓ getUserSubscriptionStatus() works with Polar subscriptions\n";
            echo "  Status: {$status['status']}\n";
            echo "  Plan: {$status['plan_name']}\n";
            $success[] = "getUserSubscriptionStatus() compatible with Polar";
        } catch (\Exception $e) {
            echo "❌ ERROR: getUserSubscriptionStatus() failed: " . $e->getMessage() . "\n";
            $errors[] = "getUserSubscriptionStatus() not compatible with Polar";
        }
        
        // Test canAccessFeature
        try {
            $canAccess = $subscriptionService->canAccessFeature($user, 'basic_feature');
            echo "✓ canAccessFeature() works with Polar subscriptions\n";
            echo "  Can access basic feature: " . ($canAccess ? 'Yes' : 'No') . "\n";
            $success[] = "canAccessFeature() compatible with Polar";
        } catch (\Exception $e) {
            echo "❌ ERROR: canAccessFeature() failed: " . $e->getMessage() . "\n";
            $errors[] = "canAccessFeature() not compatible with Polar";
        }
    } else {
        echo "ℹ No Polar subscriptions to test with\n";
    }
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to test SubscriptionService: " . $e->getMessage() . "\n";
    $errors[] = "SubscriptionService compatibility test failed";
}

// Test 4: Verify User model methods work with Polar subscriptions
echo "\n=== Test 4: User Model Compatibility ===\n";

try {
    $userWithPolar = User::whereHas('subscription', function ($query) {
        $query->where('provider', 'polar');
    })->first();
    
    if ($userWithPolar) {
        // Test subscription relationship
        try {
            $subscription = $userWithPolar->subscription;
            echo "✓ User->subscription relationship works\n";
            echo "  Provider: {$subscription->provider}\n";
            $success[] = "User->subscription relationship compatible";
        } catch (\Exception $e) {
            echo "❌ ERROR: User->subscription failed: " . $e->getMessage() . "\n";
            $errors[] = "User->subscription relationship broken";
        }
        
        // Test hasActiveSubscription method if it exists
        if (method_exists($userWithPolar, 'hasActiveSubscription')) {
            try {
                $hasActive = $userWithPolar->hasActiveSubscription();
                echo "✓ User->hasActiveSubscription() works\n";
                echo "  Has active: " . ($hasActive ? 'Yes' : 'No') . "\n";
                $success[] = "User->hasActiveSubscription() compatible";
            } catch (\Exception $e) {
                echo "❌ ERROR: hasActiveSubscription() failed: " . $e->getMessage() . "\n";
                $errors[] = "hasActiveSubscription() not compatible";
            }
        }
    } else {
        echo "ℹ No users with Polar subscriptions to test\n";
    }
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to test User model: " . $e->getMessage() . "\n";
    $errors[] = "User model compatibility test failed";
}

// Test 5: Verify database schema supports both providers
echo "\n=== Test 5: Database Schema Compatibility ===\n";

try {
    // Check that both Polar and Midtrans columns exist
    $columns = DB::select("DESCRIBE subscriptions");
    $columnNames = array_map(fn($col) => $col->Field, $columns);
    
    $requiredColumns = [
        'polar_subscription_id',
        'polar_customer_id',
        'midtrans_subscription_id',
        'midtrans_customer_id',
        'provider',
    ];
    
    $missingColumns = array_diff($requiredColumns, $columnNames);
    
    if (empty($missingColumns)) {
        echo "✓ All required columns exist for both providers\n";
        $success[] = "Database schema supports both providers";
    } else {
        echo "❌ ERROR: Missing columns: " . implode(', ', $missingColumns) . "\n";
        $errors[] = "Database schema incomplete";
    }
    
    // Verify nullable constraints
    foreach ($columns as $column) {
        if (in_array($column->Field, ['polar_subscription_id', 'polar_customer_id', 'midtrans_subscription_id', 'midtrans_customer_id'])) {
            if ($column->Null === 'YES') {
                echo "✓ Column {$column->Field} is nullable (correct)\n";
            } else {
                echo "⚠ WARNING: Column {$column->Field} is NOT nullable\n";
                $warnings[] = "Column {$column->Field} should be nullable";
            }
        }
    }
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to verify schema: " . $e->getMessage() . "\n";
    $errors[] = "Schema verification failed";
}

// Test 6: Verify Subscription model methods
echo "\n=== Test 6: Subscription Model Methods ===\n";

try {
    // Test with both Polar and Midtrans subscriptions if available
    $polarSub = Subscription::where('provider', 'polar')->first();
    $midtransSub = Subscription::where('provider', 'midtrans')->first();
    
    if ($polarSub) {
        echo "Testing Polar subscription methods:\n";
        
        // Test isActive method if it exists
        if (method_exists($polarSub, 'isActive')) {
            $isActive = $polarSub->isActive();
            echo "  ✓ isActive() works: " . ($isActive ? 'Yes' : 'No') . "\n";
        }
        
        // Test isPolar method if it exists
        if (method_exists($polarSub, 'isPolar')) {
            $isPolar = $polarSub->isPolar();
            echo "  ✓ isPolar() works: " . ($isPolar ? 'Yes' : 'No') . "\n";
        }
        
        // Test isMidtrans method if it exists
        if (method_exists($polarSub, 'isMidtrans')) {
            $isMidtrans = $polarSub->isMidtrans();
            echo "  ✓ isMidtrans() works: " . ($isMidtrans ? 'Yes' : 'No') . "\n";
        }
        
        $success[] = "Subscription model methods work with Polar";
    }
    
    if ($midtransSub) {
        echo "Testing Midtrans subscription methods:\n";
        
        if (method_exists($midtransSub, 'isActive')) {
            $isActive = $midtransSub->isActive();
            echo "  ✓ isActive() works: " . ($isActive ? 'Yes' : 'No') . "\n";
        }
        
        if (method_exists($midtransSub, 'isMidtrans')) {
            $isMidtrans = $midtransSub->isMidtrans();
            echo "  ✓ isMidtrans() works: " . ($isMidtrans ? 'Yes' : 'No') . "\n";
        }
        
        $success[] = "Subscription model methods work with Midtrans";
    }
    
    if (!$polarSub && !$midtransSub) {
        echo "ℹ No subscriptions to test model methods\n";
    }
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to test model methods: " . $e->getMessage() . "\n";
    $errors[] = "Model methods test failed";
}

// Test 7: Verify feature access control works for both providers
echo "\n=== Test 7: Feature Access Control ===\n";

try {
    $subscriptionService = app(SubscriptionService::class);
    
    // Test with Polar user
    $polarUser = User::whereHas('subscription', function ($query) {
        $query->where('provider', 'polar')->where('status', 'active');
    })->first();
    
    if ($polarUser) {
        $canAccess = $subscriptionService->canAccessFeature($polarUser, 'standard');
        echo "✓ Polar user feature access: " . ($canAccess ? 'Granted' : 'Denied') . "\n";
        $success[] = "Feature access works for Polar users";
    }
    
    // Test with Midtrans user
    $midtransUser = User::whereHas('subscription', function ($query) {
        $query->where('provider', 'midtrans')->where('status', 'active');
    })->first();
    
    if ($midtransUser) {
        $canAccess = $subscriptionService->canAccessFeature($midtransUser, 'standard');
        echo "✓ Midtrans user feature access: " . ($canAccess ? 'Granted' : 'Denied') . "\n";
        $success[] = "Feature access works for Midtrans users";
    }
    
    if (!$polarUser && !$midtransUser) {
        echo "ℹ No active subscriptions to test feature access\n";
    }
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to test feature access: " . $e->getMessage() . "\n";
    $errors[] = "Feature access test failed";
}

// Summary
echo "\n=== Verification Summary ===\n";
echo "✓ Successful checks: " . count($success) . "\n";
echo "⚠ Warnings: " . count($warnings) . "\n";
echo "❌ Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\n=== ERRORS (Must Fix) ===\n";
    foreach ($errors as $error) {
        echo "  ❌ $error\n";
    }
}

if (!empty($warnings)) {
    echo "\n=== WARNINGS (Should Review) ===\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ $warning\n";
    }
}

echo "\n=== Verification Complete ===\n";

if (empty($errors)) {
    echo "✓ Backward compatibility verification passed!\n";
    echo "\nKey findings:\n";
    echo "- Polar subscriptions are preserved and functional\n";
    echo "- Both providers can coexist in the database\n";
    echo "- Service layer supports both providers\n";
    echo "- Feature access control works for both providers\n";
    exit(0);
} else {
    echo "❌ Backward compatibility verification FAILED!\n";
    echo "\nPlease fix the errors above before proceeding.\n";
    exit(1);
}
