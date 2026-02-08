<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\RSA;
use RuntimeException;

/**
 * Service for handling WhatsApp Flow endpoint encryption/decryption.
 *
 * WhatsApp Flow endpoints receive encrypted requests and must return encrypted responses.
 * This service implements the encryption protocol as specified in:
 * https://developers.facebook.com/docs/whatsapp/flows/guides/implementingyourflowendpoint
 *
 * Encryption uses:
 * - RSA-OAEP with SHA-256 for both hash and MGF1 (key exchange)
 * - AES-128-GCM for payload encryption
 */
class WhatsAppFlowEncryptionService
{
    private ?string $privateKey;

    private ?string $passphrase;

    public function __construct()
    {
        $this->privateKey = config('services.whatsapp.flow_private_key');
        $this->passphrase = config('services.whatsapp.flow_passphrase');
    }

    /**
     * Decrypt the incoming request from WhatsApp Flow.
     *
     * @param  string  $encryptedAesKey  Base64 encoded encrypted AES key
     * @param  string  $encryptedFlowData  Base64 encoded encrypted flow data
     * @param  string  $initialVector  Base64 encoded IV
     * @return array The decrypted flow data
     *
     * @throws RuntimeException If decryption fails
     */
    public function decryptRequest(
        string $encryptedAesKey,
        string $encryptedFlowData,
        string $initialVector
    ): array {
        if (empty($this->privateKey)) {
            throw new RuntimeException('WhatsApp Flow private key not configured');
        }

        try {
            // Decode the private key (it may be base64 encoded in env)
            $privateKeyPem = $this->decodePrivateKey($this->privateKey);

            // Load and configure RSA with OAEP padding using SHA-256 for both hash and MGF1
            // This is required by WhatsApp Flow spec: RSA/ECB/OAEPWithSHA-256AndMGF1Padding
            $rsa = RSA::load($privateKeyPem, $this->passphrase ?? '');
            $rsa = $rsa->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');

            // Decrypt the AES key using RSA-OAEP with SHA-256
            $encryptedAesKeyBytes = base64_decode($encryptedAesKey);
            $aesKey = $rsa->decrypt($encryptedAesKeyBytes);

            if ($aesKey === false) {
                throw new RuntimeException('Failed to decrypt AES key');
            }

            // Decode the encrypted flow data and IV
            $encryptedFlowDataBytes = base64_decode($encryptedFlowData);
            $ivBytes = base64_decode($initialVector);

            // The encrypted data includes the auth tag at the end (last 16 bytes for GCM)
            $tagLength = 16;
            $ciphertext = substr($encryptedFlowDataBytes, 0, -$tagLength);
            $tag = substr($encryptedFlowDataBytes, -$tagLength);

            // Decrypt using AES-128-GCM
            $decryptedData = openssl_decrypt(
                $ciphertext,
                'aes-128-gcm',
                $aesKey,
                OPENSSL_RAW_DATA,
                $ivBytes,
                $tag
            );

            if ($decryptedData === false) {
                throw new RuntimeException('Failed to decrypt flow data: '.openssl_error_string());
            }

            // Parse the decrypted JSON
            $flowData = json_decode($decryptedData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Failed to parse decrypted data as JSON: '.json_last_error_msg());
            }

            // Store the AES key and IV for encrypting the response
            return [
                'data' => $flowData,
                'aes_key' => $aesKey,
                'iv' => $ivBytes,
            ];

        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('WhatsApp Flow decryption error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new RuntimeException('Decryption failed: '.$e->getMessage());
        }
    }

    /**
     * Encrypt the response to send back to WhatsApp Flow.
     *
     * @param  array  $responseData  The response data to encrypt
     * @param  string  $aesKey  The AES key from the request
     * @param  string  $iv  The IV from the request (will be flipped for response)
     * @return string Base64 encoded encrypted response
     *
     * @throws RuntimeException If encryption fails
     */
    public function encryptResponse(array $responseData, string $aesKey, string $iv): string
    {
        try {
            // Convert response to JSON
            $responseJson = json_encode($responseData);
            if ($responseJson === false) {
                throw new RuntimeException('Failed to encode response as JSON: '.json_last_error_msg());
            }

            // Flip the IV bytes for the response (as per WhatsApp spec)
            $flippedIv = $this->flipIv($iv);

            // Encrypt using AES-128-GCM
            $tag = '';
            $encrypted = openssl_encrypt(
                $responseJson,
                'aes-128-gcm',
                $aesKey,
                OPENSSL_RAW_DATA,
                $flippedIv,
                $tag,
                '',
                16
            );

            if ($encrypted === false) {
                throw new RuntimeException('Failed to encrypt response: '.openssl_error_string());
            }

            // Combine ciphertext and tag, then base64 encode
            return base64_encode($encrypted.$tag);

        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('WhatsApp Flow encryption error', [
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Encryption failed: '.$e->getMessage());
        }
    }

    /**
     * Flip the IV bytes for response encryption.
     *
     * WhatsApp requires the response IV to be the bitwise flip (NOT) of the request IV.
     * Using PHP's bitwise NOT operator (~) as shown in official Meta documentation.
     *
     * @param  string  $iv  The original IV bytes
     * @return string The flipped IV bytes
     */
    private function flipIv(string $iv): string
    {
        return ~$iv;
    }

    /**
     * Decode the private key from environment.
     *
     * The private key may be stored as:
     * - Plain PEM format
     * - Base64 encoded PEM format
     * - Single line with \n replaced by actual newlines
     *
     * @param  string  $key  The raw key from environment
     * @return string The decoded PEM key
     */
    private function decodePrivateKey(string $key): string
    {
        // If it already looks like a PEM key, return as-is
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        // Try base64 decoding
        $decoded = base64_decode($key, true);
        if ($decoded !== false && str_contains($decoded, '-----BEGIN')) {
            return $decoded;
        }

        // Try replacing literal \n with newlines
        $withNewlines = str_replace('\\n', "\n", $key);
        if (str_contains($withNewlines, '-----BEGIN')) {
            return $withNewlines;
        }

        // Assume it's a base64-encoded key without headers
        return "-----BEGIN PRIVATE KEY-----\n".
            chunk_split($key, 64, "\n").
            "-----END PRIVATE KEY-----\n";
    }

    /**
     * Validate that the service is properly configured.
     *
     * @return bool True if configured, false otherwise
     */
    public function isConfigured(): bool
    {
        return ! empty($this->privateKey);
    }

    /**
     * Generate a health check signature to verify the endpoint is working.
     *
     * @return array Health check response
     */
    public function healthCheck(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'has_passphrase' => ! empty($this->passphrase),
            'openssl_version' => OPENSSL_VERSION_TEXT,
        ];
    }
}
