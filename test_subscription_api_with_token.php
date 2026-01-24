<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== Testing Subscription API with Token ===\n\n";

// Get user
$user = User::find(1);
if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "User: {$user->email} (ID: {$user->id})\n\n";

// Create token
echo "1. Creating API token...\n";
$token = $user->createToken('test-token')->plainTextToken;
echo "   Token: " . substr($token, 0, 20) . "...\n\n";

// Test API endpoint
echo "2. Testing /api/subscription/status endpoint...\n";
$baseUrl = 'http://127.0.0.1:8000';
$url = $baseUrl . '/api/subscription/status';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n";
echo "   Response:\n";
echo "   " . str_replace("\n", "\n   ", json_encode(json_decode($response), JSON_PRETTY_PRINT)) . "\n\n";

// Parse response
$data = json_decode($response, true);
if ($data && isset($data['data']['subscription'])) {
    $sub = $data['data']['subscription'];
    echo "3. Subscription Data:\n";
    echo "   - Status: {$sub['status']}\n";
    echo "   - Plan: {$sub['plan_name']}\n";
    echo "   - Period End: " . ($sub['period_end'] ?? 'N/A') . "\n\n";
    
    if ($sub['status'] === 'active') {
        echo "✅ API is returning active subscription correctly!\n";
    } else {
        echo "❌ API is NOT returning active subscription\n";
        echo "   Current status: {$sub['status']}\n";
    }
} else {
    echo "❌ Failed to parse API response\n";
}

// Clean up token
$user->tokens()->delete();
echo "\n=== Test Complete ===\n";
