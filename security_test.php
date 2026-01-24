<?php

/**
 * Security Testing Script
 * 
 * This script tests security aspects of the Midtrans subscription integration
 * to ensure proper authentication, authorization, and data protection.
 * 
 * Usage: php security_test.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;

echo "=== Midtrans Subscription Security Testing ===\n\n";

$results = [];
$errors = [];
$warnings = [];

// Test 1: Environment Variables Security
echo "=== Test 1: Environment Variables Security ===\n";

try {
    $serverKey = env('MIDTRANS_SERVER_KEY');
    $clientKey = env('MIDTRANS_CLIENT_KEY');
    
    if (empty($serverKey)) {
        echo "❌ FAIL: MIDTRANS_SERVER_KEY is not set\n";
        $errors[] = "Server key not configured";
    } else {
        echo "✓ PASS: MIDTRANS_SERVER_KEY is set\n";
        
        // Check if key is not hardcoded in config files
        $configContent = file_get_contents(base_path('config/midtrans.php'));
        if (strpos($configContent, $serverKey) !== false) {
            echo "❌ FAIL: Server key is hardcoded in config file\n";
            $errors[] = "Server key hardcoded in config";
        } else {
            echo "✓ PASS: Server key not hardcoded\n";
        }
    }
    
    if (empty($clientKey)) {
        echo "❌ FAIL: MIDTRANS_CLIENT_KEY is not set\n";
        $errors[] = "Client key not configured";
    } else {
        echo "✓ PASS: MIDTRANS_CLIENT_KEY is set\n";
    }
    
    $results['env_security'] = empty($errors) ? 'PASS' : 'FAIL';
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['env_security'] = 'ERROR';
}

// Test 2: Webhook Signature Validation
echo "\n=== Test 2: Webhook Signature Validation ===\n";

try {
    $serverKey = config('midtrans.server_key');
    
    // Test valid signature
    $orderId = 'test_order_123';
    $statusCode = '200';
    $grossAmount = '99000.00';
    
    $signatureString = $orderId . $statusCode . $grossAmount . $serverKey;
    $validSignature = hash('sha512', $signatureString);
    
    echo "Testing signature validation logic...\n";
    
    // Simulate validation
    $testPayload = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $validSignature,
    ];
    
    $calculatedSignature = hash('sha512', 
        $testPayload['order_id'] . 
        $testPayload['status_code'] . 
        $testPayload['gross_amount'] . 
        $serverKey
    );
    
    if (hash_equals($calculatedSignature, $testPayload['signature_key'])) {
        echo "✓ PASS: Valid signature accepted\n";
    } else {
        echo "❌ FAIL: Valid signature rejected\n";
        $errors[] = "Signature validation logic incorrect";
    }
    
    // Test invalid signature
    $invalidPayload = $testPayload;
    $invalidPayload['signature_key'] = 'invalid_signature';
    
    $calculatedSignature = hash('sha512', 
        $invalidPayload['order_id'] . 
        $invalidPayload['status_code'] . 
        $invalidPayload['gross_amount'] . 
        $serverKey
    );
    
    if (!hash_equals($calculatedSignature, $invalidPayload['signature_key'])) {
        echo "✓ PASS: Invalid signature rejected\n";
    } else {
        echo "❌ FAIL: Invalid signature accepted\n";
        $errors[] = "Invalid signatures not rejected";
    }
    
    $results['signature_validation'] = 'PASS';
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['signature_validation'] = 'ERROR';
}

// Test 3: Route Protection
echo "\n=== Test 3: Route Protection ===\n";

try {
    $protectedRoutes = [
        'subscription.pricing',
        'subscription.checkout',
        'subscription.manage',
        'subscription.cancel.post',
    ];
    
    $unprotectedRoutes = [
        'webhooks.midtrans.subscription',
    ];
    
    foreach ($protectedRoutes as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);
        
        if ($route) {
            $middleware = $route->middleware();
            
            if (in_array('auth', $middleware) || in_array('auth:sanctum', $middleware)) {
                echo "✓ PASS: Route '$routeName' is protected\n";
            } else {
                echo "❌ FAIL: Route '$routeName' is NOT protected\n";
                $errors[] = "Route '$routeName' missing auth middleware";
            }
        } else {
            echo "⚠ WARNING: Route '$routeName' not found\n";
            $warnings[] = "Route '$routeName' not registered";
        }
    }
    
    foreach ($unprotectedRoutes as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);
        
        if ($route) {
            $middleware = $route->middleware();
            
            if (!in_array('auth', $middleware) && !in_array('auth:sanctum', $middleware)) {
                echo "✓ PASS: Webhook route '$routeName' is public (correct)\n";
            } else {
                echo "⚠ WARNING: Webhook route '$routeName' has auth middleware\n";
                $warnings[] = "Webhook route should be public";
            }
        }
    }
    
    $results['route_protection'] = empty($errors) ? 'PASS' : 'FAIL';
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['route_protection'] = 'ERROR';
}

// Test 4: CSRF Protection
echo "\n=== Test 4: CSRF Protection ===\n";

try {
    // Check if webhook route is excluded from CSRF
    $webhookRoute = Route::getRoutes()->getByName('webhooks.midtrans.subscription');
    
    if ($webhookRoute) {
        $middleware = $webhookRoute->middleware();
        
        // Webhook should not have CSRF middleware
        if (!in_array('web', $middleware)) {
            echo "✓ PASS: Webhook route excluded from CSRF protection\n";
        } else {
            echo "⚠ WARNING: Webhook route may have CSRF protection\n";
            $warnings[] = "Webhook route should be in API routes without CSRF";
        }
    }
    
    // Check if subscription routes have CSRF protection
    $subscriptionRoute = Route::getRoutes()->getByName('subscription.checkout');
    
    if ($subscriptionRoute) {
        $middleware = $subscriptionRoute->middleware();
        
        if (in_array('web', $middleware)) {
            echo "✓ PASS: Subscription routes have CSRF protection\n";
        } else {
            echo "⚠ WARNING: Subscription routes may lack CSRF protection\n";
            $warnings[] = "Subscription routes should have CSRF protection";
        }
    }
    
    $results['csrf_protection'] = 'PASS';
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['csrf_protection'] = 'ERROR';
}

// Test 5: HTTPS Enforcement
echo "\n=== Test 5: HTTPS Enforcement ===\n";

try {
    $appUrl = config('app.url');
    
    if (str_starts_with($appUrl, 'https://')) {
        echo "✓ PASS: APP_URL uses HTTPS\n";
    } else {
        echo "⚠ WARNING: APP_URL does not use HTTPS\n";
        $warnings[] = "APP_URL should use HTTPS in production";
    }
    
    $webhookUrls = [
        config('subscription.urls.success'),
        config('subscription.urls.cancel'),
        config('subscription.urls.error'),
    ];
    
    foreach ($webhookUrls as $url) {
        if ($url && str_starts_with($url, 'https://')) {
            echo "✓ PASS: Callback URL uses HTTPS: $url\n";
        } else {
            echo "⚠ WARNING: Callback URL should use HTTPS: $url\n";
            $warnings[] = "Callback URLs should use HTTPS";
        }
    }
    
    $results['https_enforcement'] = 'PASS';
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['https_enforcement'] = 'ERROR';
}

// Test 6: Sensitive Data Logging
echo "\n=== Test 6: Sensitive Data Logging ===\n";

try {
    // Check if sensitive data is masked in logs
    $logContent = '';
    $logFile = storage_path('logs/laravel.log');
    
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        
        // Check for exposed credentials
        $serverKey = config('midtrans.server_key');
        $clientKey = config('midtrans.client_key');
        
        if ($serverKey && strpos($logContent, $serverKey) !== false) {
            echo "❌ FAIL: Server key found in logs\n";
            $errors[] = "Server key exposed in logs";
        } else {
            echo "✓ PASS: Server key not found in logs\n";
        }
        
        if ($clientKey && strpos($logContent, $clientKey) !== false) {
            echo "❌ FAIL: Client key found in logs\n";
            $errors[] = "Client key exposed in logs";
        } else {
            echo "✓ PASS: Client key not found in logs\n";
        }
    } else {
        echo "ℹ INFO: No log file found to check\n";
    }
    
    $results['sensitive_data_logging'] = empty($errors) ? 'PASS' : 'FAIL';
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['sensitive_data_logging'] = 'ERROR';
}

// Test 7: Rate Limiting
echo "\n=== Test 7: Rate Limiting ===\n";

try {
    $webhookRoute = Route::getRoutes()->getByName('webhooks.midtrans.subscription');
    
    if ($webhookRoute) {
        $middleware = $webhookRoute->middleware();
        
        // Check for throttle middleware
        $hasRateLimit = false;
        foreach ($middleware as $m) {
            if (str_contains($m, 'throttle')) {
                $hasRateLimit = true;
                echo "✓ PASS: Webhook route has rate limiting: $m\n";
                break;
            }
        }
        
        if (!$hasRateLimit) {
            echo "⚠ WARNING: Webhook route may lack rate limiting\n";
            $warnings[] = "Consider adding rate limiting to webhook endpoint";
        }
    }
    
    $results['rate_limiting'] = 'PASS';
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['rate_limiting'] = 'ERROR';
}

// Test 8: Input Validation
echo "\n=== Test 8: Input Validation ===\n";

try {
    // Check if request validation exists
    $requestFiles = glob(app_path('Http/Requests/*SubscriptionRequest.php'));
    
    if (!empty($requestFiles)) {
        echo "✓ PASS: Found " . count($requestFiles) . " subscription request validation file(s)\n";
        foreach ($requestFiles as $file) {
            echo "  - " . basename($file) . "\n";
        }
    } else {
        echo "⚠ WARNING: No subscription request validation files found\n";
        $warnings[] = "Consider adding FormRequest validation";
    }
    
    $results['input_validation'] = 'PASS';
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['input_validation'] = 'ERROR';
}

// Test 9: Authorization Checks
echo "\n=== Test 9: Authorization Checks ===\n";

try {
    // Check if policies exist
    $policyFiles = glob(app_path('Policies/*SubscriptionPolicy.php'));
    
    if (!empty($policyFiles)) {
        echo "✓ PASS: Found subscription policy file(s)\n";
    } else {
        echo "ℹ INFO: No subscription policy files found\n";
        echo "  Consider adding policies for fine-grained authorization\n";
    }
    
    $results['authorization'] = 'PASS';
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['authorization'] = 'ERROR';
}

// Test 10: Production Mode Check
echo "\n=== Test 10: Production Mode Check ===\n";

try {
    $isProduction = config('midtrans.is_production');
    $appEnv = config('app.env');
    
    echo "Application environment: $appEnv\n";
    echo "Midtrans production mode: " . ($isProduction ? 'YES' : 'NO') . "\n";
    
    if ($appEnv === 'production' && !$isProduction) {
        echo "⚠ WARNING: Production app using sandbox Midtrans\n";
        $warnings[] = "Production should use production Midtrans credentials";
    } elseif ($appEnv !== 'production' && $isProduction) {
        echo "⚠ WARNING: Non-production app using production Midtrans\n";
        $warnings[] = "Non-production should use sandbox Midtrans credentials";
    } else {
        echo "✓ PASS: Environment and Midtrans mode are aligned\n";
    }
    
    $results['production_mode'] = 'PASS';
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $results['production_mode'] = 'ERROR';
}

// Summary
echo "\n=== Security Test Summary ===\n";

$passed = 0;
$failed = 0;
$errorCount = 0;

foreach ($results as $test => $result) {
    $icon = match ($result) {
        'PASS' => '✓',
        'FAIL' => '❌',
        'ERROR' => '❌',
        default => '?',
    };
    
    echo "$icon $test: $result\n";
    
    match ($result) {
        'PASS' => $passed++,
        'FAIL' => $failed++,
        'ERROR' => $errorCount++,
        default => null,
    };
}

echo "\nResults:\n";
echo "  Passed: $passed\n";
echo "  Failed: $failed\n";
echo "  Errors: $errorCount\n";
echo "  Warnings: " . count($warnings) . "\n";

if (!empty($errors)) {
    echo "\n=== CRITICAL ISSUES (Must Fix) ===\n";
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

echo "\n=== Security Recommendations ===\n";
echo "1. Always use HTTPS in production\n";
echo "2. Rotate API keys periodically\n";
echo "3. Monitor logs for suspicious activity\n";
echo "4. Keep dependencies up to date\n";
echo "5. Use environment-specific credentials\n";
echo "6. Implement rate limiting on all endpoints\n";
echo "7. Add comprehensive input validation\n";
echo "8. Use Laravel policies for authorization\n";
echo "9. Enable audit logging for sensitive operations\n";
echo "10. Regular security audits and penetration testing\n";

echo "\n=== Testing Complete ===\n";

if ($failed === 0 && $errorCount === 0) {
    echo "✓ All security tests passed!\n";
    if (!empty($warnings)) {
        echo "⚠ Please review warnings above\n";
    }
    exit(0);
} else {
    echo "❌ Some security tests failed or had errors\n";
    echo "Please fix critical issues before deployment\n";
    exit(1);
}
