<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Http;

echo "=== TESTING KASIR API ENDPOINTS ===\n\n";

$kasir = User::where('email', 'kasir@gmail.com')->first();

// Get or create token
$token = $kasir->tokens()->first();
if (!$token) {
    $token = $kasir->createToken('test-token');
    echo "Created new token\n";
} else {
    echo "Using existing token: {$token->id}\n";
}

$tokenString = $token->plainTextToken ?? $token->token;

echo "Token: {$tokenString}\n\n";

// Test Products API
echo "Testing GET /api/pos/products\n";
$response = Http::withToken($tokenString)
    ->get('http://127.0.0.1:8000/api/pos/products');

echo "Status: " . $response->status() . "\n";
$data = $response->json();
echo "Response:\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

// Test Categories API
echo "Testing GET /api/pos/categories\n";
$response = Http::withToken($tokenString)
    ->get('http://127.0.0.1:8000/api/pos/categories');

echo "Status: " . $response->status() . "\n";
$data = $response->json();
echo "Response:\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n";
