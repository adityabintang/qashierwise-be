<?php

/**
 * Generate RSA key pair for WhatsApp Flow endpoint.
 *
 * This script generates a 2048-bit RSA key pair and outputs:
 * 1. The base64-encoded private key for your .env file
 * 2. The public key to upload to WhatsApp Flow settings
 *
 * Usage: php generate_flow_keys.php
 */
echo "=== WhatsApp Flow Key Pair Generator ===\n\n";

// Find OpenSSL config
$opensslConfig = null;
$possiblePaths = [
    'C:/xampp/apache/conf/openssl.cnf',
    'C:/xampp/php/extras/openssl/openssl.cnf',
    'C:/laragon/etc/ssl/openssl.cnf',
    'C:/Program Files/Common Files/SSL/openssl.cnf',
    getenv('OPENSSL_CONF'),
];

foreach ($possiblePaths as $path) {
    if ($path && file_exists($path)) {
        $opensslConfig = $path;
        break;
    }
}

// Generate a new RSA key pair
$config = [
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];

if ($opensslConfig) {
    $config['config'] = $opensslConfig;
    echo "Using OpenSSL config: $opensslConfig\n";
}

echo "Generating 2048-bit RSA key pair...\n";

// Suppress warnings and get the error string
$keyPair = @openssl_pkey_new($config);

if ($keyPair === false) {
    $error = openssl_error_string();
    echo "❌ First attempt failed: $error\n";

    // Try without config
    echo "Trying without explicit config...\n";
    $config2 = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];
    $keyPair = @openssl_pkey_new($config2);

    if ($keyPair === false) {
        echo "❌ ERROR: Failed to generate key pair\n";
        echo "OpenSSL errors:\n";
        while ($err = openssl_error_string()) {
            echo "  - $err\n";
        }
        echo "\n";
        echo "Please generate keys manually using:\n";
        echo "  openssl genrsa -out private.pem 2048\n";
        echo "  openssl rsa -in private.pem -pubout -out public.pem\n";
        echo "\nThen base64 encode the private key and add to .env:\n";
        echo "  WHATSAPP_FLOW_PRIVATE_KEY=<base64 encoded private.pem contents>\n";
        exit(1);
    }
}

// Export private key
$privateKeyPem = '';
$exportResult = openssl_pkey_export($keyPair, $privateKeyPem, null, $config);

if (! $exportResult || empty($privateKeyPem)) {
    echo "❌ ERROR: Failed to export private key\n";
    echo "OpenSSL errors:\n";
    while ($err = openssl_error_string()) {
        echo "  - $err\n";
    }
    exit(1);
}

// Get public key
$keyDetails = openssl_pkey_get_details($keyPair);
$publicKeyPem = $keyDetails['key'];

echo "✅ Key pair generated successfully!\n\n";

// Base64 encode the private key for .env
$privateKeyBase64 = base64_encode($privateKeyPem);

echo "═══════════════════════════════════════════════════════════\n";
echo "STEP 1: Add this to your .env file\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo 'WHATSAPP_FLOW_PRIVATE_KEY='.$privateKeyBase64."\n\n";

echo "═══════════════════════════════════════════════════════════\n";
echo "STEP 2: Upload this PUBLIC KEY to WhatsApp Flow settings\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo $publicKeyPem."\n";

// Save the public key to a file for easy copying
$publicKeyFile = __DIR__.'/whatsapp_flow_public.pem';
file_put_contents($publicKeyFile, $publicKeyPem);
echo "Public key also saved to: $publicKeyFile\n\n";

// Verify the keys work together
echo "═══════════════════════════════════════════════════════════\n";
echo "Verifying keys...\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Test encryption/decryption
$testData = 'Hello WhatsApp Flow!';
$encrypted = '';
openssl_public_encrypt($testData, $encrypted, $publicKeyPem, OPENSSL_PKCS1_OAEP_PADDING);

$decrypted = '';
openssl_private_decrypt($encrypted, $decrypted, $privateKeyPem, OPENSSL_PKCS1_OAEP_PADDING);

if ($decrypted === $testData) {
    echo "✅ Key pair verified - encryption/decryption works!\n\n";
} else {
    echo "❌ Key pair verification failed\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════════════\n";
echo "Next steps:\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "1. Copy the WHATSAPP_FLOW_PRIVATE_KEY line above and replace\n";
echo "   the existing one in your .env file\n";
echo "2. Go to WhatsApp Flow settings in Meta Business Suite\n";
echo "3. Upload the public key shown above (or from $publicKeyFile)\n";
echo "4. Run: php test_flow_encryption.php to verify everything works\n";
