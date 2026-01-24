<?php

/**
 * Configuration Verification Script
 * 
 * This script verifies that all required configuration for Midtrans subscription
 * is properly set up and accessible.
 * 
 * Usage: php verify_config.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Midtrans Subscription Configuration Verification ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// Check if config files exist
echo "=== Configuration Files ===\n";

$configFiles = [
    'config/subscription.php',
    'config/midtrans.php',
];

foreach ($configFiles as $file) {
    if (file_exists(base_path($file))) {
        echo "✓ $file exists\n";
        $success[] = "$file exists";
    } else {
        echo "⚠ WARNING: $file not found\n";
        $warnings[] = "$file not found";
    }
}

// Check environment variables
echo "\n=== Environment Variables ===\n";

$requiredEnvVars = [
    'MIDTRANS_SERVER_KEY' => 'Midtrans Server Key',
    'MIDTRANS_CLIENT_KEY' => 'Midtrans Client Key',
    'MIDTRANS_IS_PRODUCTION' => 'Midtrans Production Mode',
];

foreach ($requiredEnvVars as $var => $description) {
    $value = env($var);
    if ($value !== null && $value !== '') {
        // Mask sensitive values
        if (str_contains($var, 'KEY')) {
            $maskedValue = substr($value, 0, 10) . '...' . substr($value, -4);
            echo "✓ $var is set ($maskedValue)\n";
        } else {
            echo "✓ $var is set ($value)\n";
        }
        $success[] = "$description is configured";
    } else {
        echo "❌ ERROR: $var is not set\n";
        $errors[] = "$description is missing";
    }
}

// Check subscription URLs
echo "\n=== Subscription URLs ===\n";

$urlVars = [
    'MIDTRANS_SUBSCRIPTION_SUCCESS_URL' => 'Success URL',
    'MIDTRANS_SUBSCRIPTION_CANCEL_URL' => 'Cancel URL',
    'MIDTRANS_SUBSCRIPTION_ERROR_URL' => 'Error URL',
];

foreach ($urlVars as $var => $description) {
    $value = env($var);
    if ($value !== null && $value !== '') {
        echo "✓ $description: $value\n";
        $success[] = "$description is configured";
    } else {
        echo "⚠ WARNING: $var is not set\n";
        $warnings[] = "$description is missing";
    }
}

// Check config values
echo "\n=== Configuration Values ===\n";

try {
    // Check subscription config
    $provider = config('subscription.provider');
    echo "✓ Subscription provider: " . ($provider ?? 'not set') . "\n";
    
    if ($provider === null) {
        $warnings[] = "Subscription provider not configured";
    }
    
    // Check Midtrans config
    $serverKey = config('midtrans.server_key');
    $clientKey = config('midtrans.client_key');
    $isProduction = config('midtrans.is_production');
    
    if ($serverKey) {
        $maskedKey = substr($serverKey, 0, 10) . '...' . substr($serverKey, -4);
        echo "✓ Midtrans server key: $maskedKey\n";
        $success[] = "Midtrans server key loaded";
    } else {
        echo "❌ ERROR: Midtrans server key not loaded\n";
        $errors[] = "Midtrans server key not loaded from config";
    }
    
    if ($clientKey) {
        $maskedKey = substr($clientKey, 0, 10) . '...' . substr($clientKey, -4);
        echo "✓ Midtrans client key: $maskedKey\n";
        $success[] = "Midtrans client key loaded";
    } else {
        echo "❌ ERROR: Midtrans client key not loaded\n";
        $errors[] = "Midtrans client key not loaded from config";
    }
    
    echo "✓ Production mode: " . ($isProduction ? 'YES' : 'NO') . "\n";
    
    if ($isProduction) {
        echo "⚠ WARNING: Running in PRODUCTION mode!\n";
        $warnings[] = "Running in production mode - ensure this is intentional";
    } else {
        echo "✓ Running in SANDBOX mode (safe for testing)\n";
        $success[] = "Running in sandbox mode";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to load configuration: " . $e->getMessage() . "\n";
    $errors[] = "Configuration loading failed: " . $e->getMessage();
}

// Check subscription plans
echo "\n=== Subscription Plans ===\n";

try {
    $plans = config('subscription.plans', []);
    
    if (empty($plans)) {
        echo "⚠ WARNING: No subscription plans configured\n";
        $warnings[] = "No subscription plans found";
    } else {
        echo "✓ Found " . count($plans) . " subscription plan(s)\n";
        
        foreach ($plans as $planId => $plan) {
            echo "\n  Plan: $planId\n";
            echo "    Name: " . ($plan['name'] ?? 'N/A') . "\n";
            echo "    Price: " . ($plan['price'] ?? 'N/A') . " " . ($plan['currency'] ?? 'IDR') . "\n";
            echo "    Interval: " . ($plan['interval'] ?? 'N/A') . "\n";
            
            // Validate plan structure
            $requiredFields = ['name', 'price', 'interval', 'currency'];
            $missingFields = array_diff($requiredFields, array_keys($plan));
            
            if (!empty($missingFields)) {
                echo "    ⚠ WARNING: Missing fields: " . implode(', ', $missingFields) . "\n";
                $warnings[] = "Plan '$planId' is missing fields: " . implode(', ', $missingFields);
            } else {
                echo "    ✓ Plan structure is valid\n";
                $success[] = "Plan '$planId' is properly configured";
            }
        }
    }
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to load plans: " . $e->getMessage() . "\n";
    $errors[] = "Plans loading failed: " . $e->getMessage();
}

// Check routes
echo "\n=== Routes ===\n";

try {
    $routes = app('router')->getRoutes();
    
    $requiredRoutes = [
        'subscription.pricing',
        'subscription.checkout',
        'subscription.success',
        'subscription.cancel',
        'subscription.manage',
        'webhooks.midtrans.subscription',
    ];
    
    foreach ($requiredRoutes as $routeName) {
        if ($routes->hasNamedRoute($routeName)) {
            echo "✓ Route '$routeName' is registered\n";
            $success[] = "Route '$routeName' exists";
        } else {
            echo "❌ ERROR: Route '$routeName' not found\n";
            $errors[] = "Route '$routeName' is missing";
        }
    }
} catch (\Exception $e) {
    echo "⚠ WARNING: Could not verify routes: " . $e->getMessage() . "\n";
    $warnings[] = "Route verification failed: " . $e->getMessage();
}

// Check services
echo "\n=== Services ===\n";

try {
    $midtransService = app(\App\Services\MidtransSubscriptionService::class);
    echo "✓ MidtransSubscriptionService can be instantiated\n";
    $success[] = "MidtransSubscriptionService is available";
} catch (\Exception $e) {
    echo "❌ ERROR: MidtransSubscriptionService failed: " . $e->getMessage() . "\n";
    $errors[] = "MidtransSubscriptionService instantiation failed";
}

try {
    $subscriptionService = app(\App\Services\SubscriptionService::class);
    echo "✓ SubscriptionService can be instantiated\n";
    $success[] = "SubscriptionService is available";
} catch (\Exception $e) {
    echo "❌ ERROR: SubscriptionService failed: " . $e->getMessage() . "\n";
    $errors[] = "SubscriptionService instantiation failed";
}

// Check webhook endpoint accessibility
echo "\n=== Webhook Endpoint ===\n";

$webhookUrl = config('app.url') . '/api/webhooks/midtrans';
echo "Webhook URL: $webhookUrl\n";

if (filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
    echo "✓ Webhook URL format is valid\n";
    $success[] = "Webhook URL is properly formatted";
} else {
    echo "⚠ WARNING: Webhook URL format may be invalid\n";
    $warnings[] = "Webhook URL format validation failed";
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
    echo "✓ Configuration verification passed!\n";
    echo "\nNext steps:\n";
    echo "1. Review any warnings above\n";
    echo "2. Configure webhook URL in Midtrans dashboard: $webhookUrl\n";
    echo "3. Test subscription creation flow\n";
    echo "4. Test webhook delivery\n";
    exit(0);
} else {
    echo "❌ Configuration verification FAILED!\n";
    echo "\nPlease fix the errors above before proceeding.\n";
    exit(1);
}
