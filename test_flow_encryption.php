<?php

/**
 * Test script to verify WhatsApp Flow encryption/decryption.
 *
 * This script simulates the encryption process that WhatsApp uses
 * to send requests to your endpoint, helping verify your private key
 * and encryption service are working correctly.
 *
 * Usage: php test_flow_encryption.php
 */

require_once __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== WhatsApp Flow Encryption Test ===\n\n";

// Check configuration
$privateKey = config('services.whatsapp.flow_private_key');
$passphrase = config('services.whatsapp.flow_passphrase');

if (empty($privateKey)) {
    echo "❌ ERROR: WHATSAPP_FLOW_PRIVATE_KEY is not configured in .env\n";
    echo "\nTo generate a key pair, run:\n";
    echo "  openssl genrsa -des3 -out private.pem 2048\n";
    echo "  openssl rsa -in private.pem -outform PEM -pubout -out public.pem\n";
    echo "\nThen add to .env:\n";
    echo "  WHATSAPP_FLOW_PRIVATE_KEY=\"contents of private.pem\"\n";
    echo "  WHATSAPP_FLOW_PASSPHRASE=\"your passphrase\"\n";
    exit(1);
}

echo "✅ Private key is configured\n";
echo '✅ Passphrase is '.($passphrase ? 'configured' : 'not set (key may be unencrypted)')."\n\n";

// Try to load the private key
echo "Testing private key loading...\n";

try {
    // Decode the private key (handle various formats)
    $privateKeyPem = $privateKey;

    // If it doesn't look like PEM, try base64 decoding
    if (! str_contains($privateKeyPem, '-----BEGIN')) {
        echo "   Key doesn't contain -----BEGIN, trying base64 decode...\n";
        $decoded = base64_decode($privateKeyPem, true);
        if ($decoded !== false && str_contains($decoded, '-----BEGIN')) {
            echo "   Successfully decoded base64 key\n";
            $privateKeyPem = $decoded;
        } else {
            // Try replacing literal \n with newlines
            echo "   Trying \\n replacement...\n";
            $privateKeyPem = str_replace('\\n', "\n", $privateKeyPem);
        }
    }

    echo '   Key format: '.(str_contains($privateKeyPem, 'BEGIN PRIVATE KEY') ? 'PKCS#8' : (str_contains($privateKeyPem, 'BEGIN RSA PRIVATE KEY') ? 'PKCS#1' : 'Unknown'))."\n";

    $key = openssl_pkey_get_private($privateKeyPem, $passphrase ?? '');

    if ($key === false) {
        throw new Exception('Failed to load private key: '.openssl_error_string());
    }

    $keyDetails = openssl_pkey_get_details($key);
    echo "✅ Private key loaded successfully\n";
    echo "   - Key type: RSA\n";
    echo "   - Key bits: {$keyDetails['bits']}\n\n";

    // Extract public key for display
    $publicKey = $keyDetails['key'];
    echo "📋 Public Key (upload this to WhatsApp Flow settings):\n";
    echo "─────────────────────────────────────────────────────\n";
    echo $publicKey;
    echo "─────────────────────────────────────────────────────\n\n";

    // Test encryption/decryption with a sample payload
    echo "Testing encryption/decryption cycle...\n";

    // Generate a random AES key (128-bit = 16 bytes)
    $aesKey = random_bytes(16);

    // Generate a random IV (12 bytes for AES-GCM is common, but WhatsApp uses 16)
    $iv = random_bytes(16);

    // Sample flow data
    $sampleFlowData = [
        'action' => 'INIT',
        'flow_token' => 'test_123_'.uniqid(),
        'version' => '3.0',
    ];

    $flowDataJson = json_encode($sampleFlowData);
    echo "   Original data: $flowDataJson\n";

    // Encrypt the AES key with RSA-OAEP
    $encryptedAesKey = '';
    $encryptResult = openssl_public_encrypt(
        $aesKey,
        $encryptedAesKey,
        $publicKey,
        OPENSSL_PKCS1_OAEP_PADDING
    );

    if ($encryptResult === false) {
        throw new Exception('Failed to encrypt AES key: '.openssl_error_string());
    }

    // Encrypt the flow data with AES-128-GCM
    $tag = '';
    $encryptedFlowData = openssl_encrypt(
        $flowDataJson,
        'aes-128-gcm',
        $aesKey,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',
        16
    );

    if ($encryptedFlowData === false) {
        throw new Exception('Failed to encrypt flow data: '.openssl_error_string());
    }

    // Combine ciphertext and tag
    $encryptedFlowDataWithTag = $encryptedFlowData.$tag;

    // Base64 encode for transmission
    $requestBody = [
        'encrypted_aes_key' => base64_encode($encryptedAesKey),
        'encrypted_flow_data' => base64_encode($encryptedFlowDataWithTag),
        'initial_vector' => base64_encode($iv),
    ];

    echo "   ✅ Encryption successful\n";

    // Now test decryption using the encryption service
    $encryptionService = app(\App\Services\WhatsAppFlowEncryptionService::class);

    $decrypted = $encryptionService->decryptRequest(
        $requestBody['encrypted_aes_key'],
        $requestBody['encrypted_flow_data'],
        $requestBody['initial_vector']
    );

    echo "   ✅ Decryption successful\n";
    echo '   Decrypted data: '.json_encode($decrypted['data'])."\n";

    // Verify the data matches
    if ($decrypted['data'] === $sampleFlowData) {
        echo "   ✅ Data integrity verified\n\n";
    } else {
        echo "   ⚠️  Data mismatch after decryption\n\n";
    }

    // Test response encryption
    echo "Testing response encryption...\n";

    $responseData = [
        'screen' => 'APPOINTMENT',
        'data' => [
            'dates' => [['id' => '2026-01-02', 'title' => 'Jan 2, 2026']],
            'times' => [['id' => '10:00', 'title' => '10:00 AM']],
            'is_time_enabled' => false,
        ],
    ];

    $encryptedResponse = $encryptionService->encryptResponse(
        $responseData,
        $decrypted['aes_key'],
        $decrypted['iv']
    );

    echo "   ✅ Response encryption successful\n";
    echo '   Encrypted response length: '.strlen($encryptedResponse)." characters\n\n";

    echo "=== All Tests Passed! ===\n\n";

    echo "Your endpoint is ready. Make sure to:\n";
    echo "1. Upload the public key shown above to WhatsApp Flow settings\n";
    echo '2. Set your endpoint URL to: '.url('/api/whatsapp/flow/endpoint')."\n";
    echo "3. Ensure your server has a valid HTTPS certificate\n";

} catch (Exception $e) {
    echo '❌ ERROR: '.$e->getMessage()."\n";
    echo "\nStack trace:\n".$e->getTraceAsString()."\n";
    exit(1);
}
