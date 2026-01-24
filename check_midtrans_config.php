<?php

/**
 * Midtrans Configuration Checker
 * 
 * This script verifies that Midtrans is properly configured
 * Run: php check_midtrans_config.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Midtrans Configuration Checker ===\n\n";

// Check if config file exists
echo "1. Checking config file...\n";
$configPath = config_path('subscription.php');
if (file_exists($configPath)) {
    echo "   ✓ config/subscription.php exists\n";
} else {
    echo "   ✗ config/subscription.php NOT FOUND\n";
    echo "   Please create the configuration file\n";
}

// Check environment variables
echo "\n2. Checking environment variables...\n";

$requiredEnvVars = [
    'MIDTRANS_SERVER_KEY',
    'MIDTRANS_CLIENT_KEY',
    'MIDTRANS_IS_PRODUCTION',
    'MIDTRANS_SUBSCRIPTION_SUCCESS_URL',
    'MIDTRANS_SUBSCRIPTION_CANCEL_URL',
    'MIDTRANS_SUBSCRIPTION_ERROR_URL',
];

$missingVars = [];
foreach ($requiredEnvVars as $var) {
    $value = env($var);
    if ($value === null) {
        echo "   ✗ $var: NOT SET\n";
        $missingVars[] = $var;
    } else {
        // Mask sensitive values
        if (strpos($var, 'KEY') !== false) {
            $masked = substr($value, 0, 10) . '...' . substr($value, -4);
            echo "   ✓ $var: $masked\n";
        } else {
            echo "   ✓ $var: $value\n";
        }
    }
}

// Check config values
echo "\n3. Checking config values...\n";

try {
    $serverKey = config('subscription.midtrans.server_key');
    $clientKey = config('subscription.midtrans.client_key');
    $isProduction = config('subscription.midtrans.is_production');
    
    if ($serverKey) {
        $masked = substr($serverKey, 0, 10) . '...' . substr($serverKey, -4);
        echo "   ✓ Server Key: $masked\n";
        
        // Check if it's sandbox or production key
        if (strpos($serverKey, 'SB-') === 0) {
            echo "   ℹ Key Type: SANDBOX\n";
        } elseif (strpos($serverKey, 'Mid-') === 0) {
            echo "   ℹ Key Type: PRODUCTION\n";
        } else {
            echo "   ⚠ Key Type: UNKNOWN FORMAT\n";
        }
    } else {
        echo "   ✗ Server Key: NOT SET\n";
    }
    
    if ($clientKey) {
        $masked = substr($clientKey, 0, 10) . '...' . substr($clientKey, -4);
        echo "   ✓ Client Key: $masked\n";
    } else {
        echo "   ✗ Client Key: NOT SET\n";
    }
    
    echo "   ✓ Is Production: " . ($isProduction ? 'YES' : 'NO') . "\n";
    
    // Check if production flag matches key type
    if ($serverKey) {
        $isSandboxKey = strpos($serverKey, 'SB-') === 0;
        $isProductionKey = strpos($serverKey, 'Mid-') === 0 && strpos($serverKey, 'SB-') === false;
        
        if ($isProduction && $isSandboxKey) {
            echo "   ⚠ WARNING: Production mode enabled but using SANDBOX key!\n";
        } elseif (!$isProduction && $isProductionKey) {
            echo "   ⚠ WARNING: Sandbox mode enabled but using PRODUCTION key!\n";
        } else {
            echo "   ✓ Production flag matches key type\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ✗ Error loading config: " . $e->getMessage() . "\n";
}

// Check subscription plans
echo "\n4. Checking subscription plans...\n";

try {
    $plans = config('subscription.plans');
    if ($plans && is_array($plans)) {
        echo "   ✓ Plans configured: " . count($plans) . "\n";
        foreach ($plans as $planId => $plan) {
            echo "     - $planId: " . ($plan['name'] ?? 'N/A') . " (Rp " . number_format($plan['price'] ?? 0) . ")\n";
        }
    } else {
        echo "   ✗ No plans configured\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error loading plans: " . $e->getMessage() . "\n";
}

// Check URLs
echo "\n5. Checking subscription URLs...\n";

try {
    $successUrl = config('subscription.urls.success');
    $cancelUrl = config('subscription.urls.cancel');
    $errorUrl = config('subscription.urls.error');
    
    if ($successUrl) {
        echo "   ✓ Success URL: $successUrl\n";
    } else {
        echo "   ✗ Success URL: NOT SET\n";
    }
    
    if ($cancelUrl) {
        echo "   ✓ Cancel URL: $cancelUrl\n";
    } else {
        echo "   ✗ Cancel URL: NOT SET\n";
    }
    
    if ($errorUrl) {
        echo "   ✓ Error URL: $errorUrl\n";
    } else {
        echo "   ✗ Error URL: NOT SET\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error loading URLs: " . $e->getMessage() . "\n";
}

// Check if MidtransSubscriptionService exists
echo "\n6. Checking service classes...\n";

if (class_exists('App\Services\MidtransSubscriptionService')) {
    echo "   ✓ MidtransSubscriptionService exists\n";
} else {
    echo "   ✗ MidtransSubscriptionService NOT FOUND\n";
}

if (class_exists('App\Services\SubscriptionService')) {
    echo "   ✓ SubscriptionService exists\n";
} else {
    echo "   ✗ SubscriptionService NOT FOUND\n";
}

// Check routes
echo "\n7. Checking routes...\n";

try {
    $routes = app('router')->getRoutes();
    
    $requiredRoutes = [
        'subscription.pricing',
        'subscription.checkout',
        'subscription.success',
        'subscription.cancel',
        'subscription.manage',
    ];
    
    // Check webhook route separately (it may not have a name)
    $webhookRouteExists = false;
    foreach ($routes as $route) {
        if ($route->uri() === 'api/webhooks/midtrans' && in_array('POST', $route->methods())) {
            $webhookRouteExists = true;
            break;
        }
    }
    
    foreach ($requiredRoutes as $routeName) {
        if ($routes->hasNamedRoute($routeName)) {
            echo "   ✓ Route '$routeName' exists\n";
        } else {
            echo "   ✗ Route '$routeName' NOT FOUND\n";
        }
    }
    
    // Check webhook route
    if ($webhookRouteExists) {
        echo "   ✓ Webhook route 'POST /api/webhooks/midtrans' exists\n";
    } else {
        echo "   ✗ Webhook route 'POST /api/webhooks/midtrans' NOT FOUND\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error checking routes: " . $e->getMessage() . "\n";
}

// Test API connection (optional)
echo "\n8. Testing API connection...\n";

if ($serverKey && $clientKey) {
    echo "   ℹ To test API connection, create a test subscription through your application\n";
    echo "   ℹ Or use the Midtrans dashboard to send a test webhook\n";
} else {
    echo "   ✗ Cannot test API connection - credentials not configured\n";
}

// Summary
echo "\n=== Summary ===\n";

if (empty($missingVars)) {
    echo "✓ All environment variables are set\n";
} else {
    echo "✗ Missing environment variables:\n";
    foreach ($missingVars as $var) {
        echo "  - $var\n";
    }
    echo "\nPlease add these to your .env file\n";
}

echo "\n=== Next Steps ===\n";
echo "1. If any checks failed, review the configuration\n";
echo "2. Clear config cache: php artisan config:clear\n";
echo "3. Test subscription creation through your application\n";
echo "4. Monitor logs: tail -f storage/logs/laravel.log\n";
echo "5. Refer to docs/MIDTRANS_ACCOUNT_SETUP_GUIDE.md for detailed setup\n";

echo "\n";
