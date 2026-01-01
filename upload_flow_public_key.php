<?php

/**
 * Script to upload WhatsApp Flow public key to Meta
 * 
 * This script uploads the business public key to Meta's WhatsApp Business API
 * which is required for WhatsApp Flow endpoint encryption.
 * 
 * Usage: php upload_flow_public_key.php
 * 
 * Prerequisites:
 * - WHATSAPP_PHONE_NUMBER_ID must be set in .env
 * - A valid WhatsApp access token from the connected account
 * 
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/reference/whatsapp-business-encryption#set-business-public-key
 */

require __DIR__ . '/vendor/autoload.php';

use App\Models\WhatsAppAccount;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== WhatsApp Flow Public Key Upload Script ===\n\n";

// Get the public key from file
$publicKeyPath = __DIR__ . '/whatsapp_flow_public.pem';
if (!file_exists($publicKeyPath)) {
    echo "ERROR: Public key file not found at: {$publicKeyPath}\n";
    echo "Please generate a key pair first using generate_flow_keys.php\n";
    exit(1);
}

$publicKey = file_get_contents($publicKeyPath);
echo "Public key loaded from: {$publicKeyPath}\n";
echo "Public key preview:\n" . substr($publicKey, 0, 100) . "...\n\n";

// Get WhatsApp account from database using Eloquent (to auto-decrypt access_token)
$whatsappAccount = WhatsAppAccount::whereNotNull('phone_number_id')
    ->whereNotNull('access_token')
    ->first();

if (!$whatsappAccount) {
    echo "ERROR: No WhatsApp account found with phone_number_id and access_token.\n";
    echo "Please connect a WhatsApp Business account first via Embedded Signup.\n";
    exit(1);
}

$phoneNumberId = $whatsappAccount->phone_number_id;
$accessToken = $whatsappAccount->access_token; // Auto-decrypted by Eloquent cast

echo "Using WhatsApp Phone Number ID: {$phoneNumberId}\n";
echo "Access Token: " . substr($accessToken, 0, 20) . "...\n\n";

$apiVersion = config('services.whatsapp.api_version', 'v22.0');

// Step 1: Check if phone number is registered, if not register it first
echo "Step 1: Checking/Registering phone number with Cloud API...\n";

$registerUrl = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/register";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $registerUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'messaging_product' => 'whatsapp',
        'pin' => '123456', // 6-digit PIN for two-step verification
    ]),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);

$registerResponse = curl_exec($ch);
$registerHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Register Response Code: {$registerHttpCode}\n";
echo "Register Response: {$registerResponse}\n\n";

$registerData = json_decode($registerResponse, true);
if ($registerHttpCode === 200 && isset($registerData['success']) && $registerData['success'] === true) {
    echo "Phone number registered successfully!\n\n";
} elseif (isset($registerData['error']['code']) && $registerData['error']['code'] === 100) {
    // Error 100 often means already registered, which is fine
    echo "Phone number may already be registered (continuing...)\n\n";
} else {
    echo "Warning: Registration response unclear, attempting to continue...\n\n";
}

// Step 2: Upload public key to Meta
echo "Step 2: Uploading public key...\n";
$url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/whatsapp_business_encryption";

echo "Uploading public key to: {$url}\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'business_public_key' => $publicKey,
    ]),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "CURL ERROR: {$error}\n";
    exit(1);
}

echo "HTTP Response Code: {$httpCode}\n";
echo "Response: {$response}\n\n";

$responseData = json_decode($response, true);

if ($httpCode === 200 && isset($responseData['success']) && $responseData['success'] === true) {
    echo "SUCCESS! Public key has been uploaded to Meta.\n";
    echo "\nNext steps:\n";
    echo "1. Go to WhatsApp Flow Manager in Meta Business Suite\n";
    echo "2. The 'Enter public key' status should now be resolved\n";
    echo "3. If not, try clicking 'Refresh' or wait a few minutes\n";
    echo "4. Your endpoint URL should be: " . config('app.url') . "/api/whatsapp/flow/endpoint\n";
} else {
    echo "FAILED to upload public key.\n";
    
    if (isset($responseData['error'])) {
        echo "Error Code: " . ($responseData['error']['code'] ?? 'unknown') . "\n";
        echo "Error Type: " . ($responseData['error']['type'] ?? 'unknown') . "\n";
        echo "Error Message: " . ($responseData['error']['message'] ?? 'unknown') . "\n";
        
        if (isset($responseData['error']['error_subcode'])) {
            echo "Error Subcode: " . $responseData['error']['error_subcode'] . "\n";
        }
    }
    
    echo "\nTroubleshooting:\n";
    echo "1. Verify the access token is valid and not expired\n";
    echo "2. Ensure the access token has the required permissions\n";
    echo "3. Check that the phone number ID is correct\n";
    echo "4. Try refreshing the access token via Embedded Signup\n";
}
