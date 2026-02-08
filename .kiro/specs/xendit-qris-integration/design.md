# Design Document: Xendit QRIS Integration

## Overview

This design implements multi-provider QRIS support, allowing merchants to choose between Midtrans and Xendit for generating dynamic QRIS codes. The architecture uses a provider factory pattern to instantiate the appropriate payment provider based on user configuration. Xendit credentials are securely encrypted and stored, with webhook handling for payment notifications.

## Architecture

### High-Level Flow

```
User Configuration
    ↓
Provider Selection (Midtrans/Xendit)
    ↓
Credential Storage (Encrypted)
    ↓
ProviderFactory
    ↓
PaymentProvider (Midtrans/Xendit)
    ↓
QRIS Generation
    ↓
Webhook Handling
    ↓
Transaction Status Update
```

### Component Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                         │
│  (Controllers, Services, API Endpoints)                      │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│              ProviderFactory                                 │
│  - Instantiates correct provider based on config             │
│  - Handles credential decryption                             │
└────────────────────┬────────────────────────────────────────┘
                     │
        ┌────────────┴────────────┐
        │                         │
┌───────▼──────────┐    ┌────────▼──────────┐
│ MidtransProvider │    │ XenditProvider    │
│ (Existing)       │    │ (New)             │
└───────┬──────────┘    └────────┬──────────┘
        │                        │
        └────────────┬───────────┘
                     │
        ┌────────────▼────────────┐
        │ PaymentProviderInterface│
        │ - generateQris()        │
        │ - verifyWebhook()       │
        │ - parseResponse()       │
        └────────────┬────────────┘
                     │
        ┌────────────▼────────────┐
        │  QrisTransaction Model  │
        │  - status               │
        │  - qr_string            │
        │  - reference_id         │
        └────────────────────────┘
```

## Components and Interfaces

### 1. PaymentProviderInterface

```php
interface PaymentProviderInterface
{
    /**
     * Generate a dynamic QRIS code
     * @param QrisRequest $request
     * @return QrisResponse
     * @throws ProviderException
     */
    public function generateQris(QrisRequest $request): QrisResponse;

    /**
     * Verify webhook signature from provider
     * @param array $payload
     * @param string $signature
     * @return bool
     */
    public function verifyWebhook(array $payload, string $signature): bool;

    /**
     * Parse webhook payload into standard format
     * @param array $payload
     * @return WebhookTransaction
     */
    public function parseWebhookPayload(array $payload): WebhookTransaction;

    /**
     * Get provider name
     * @return string
     */
    public function getProviderName(): string;
}
```

### 2. XenditProvider Implementation

```php
class XenditProvider implements PaymentProviderInterface
{
    private string $apiKey;
    private string $secretKey;
    private string $baseUrl = 'https://api.xendit.co';
    private HttpClient $httpClient;

    public function __construct(string $apiKey, string $secretKey)
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->httpClient = new HttpClient();
    }

    public function generateQris(QrisRequest $request): QrisResponse
    {
        $payload = [
            'external_id' => $request->externalId,
            'type' => 'DYNAMIC',
            'currency' => 'IDR',
            'amount' => $request->amount,
            'callback_url' => $request->callbackUrl,
        ];

        $response = $this->httpClient->post(
            "{$this->baseUrl}/qr_codes",
            $payload,
            $this->getHeaders()
        );

        if (!$response->isSuccessful()) {
            throw new ProviderException($response->getErrorMessage());
        }

        return new QrisResponse(
            qrString: $response->get('qr_string'),
            referenceId: $response->get('id'),
            externalId: $request->externalId,
            amount: $request->amount,
            provider: 'xendit'
        );
    }

    public function verifyWebhook(array $payload, string $signature): bool
    {
        $computedSignature = hash_hmac(
            'sha256',
            json_encode($payload),
            $this->secretKey
        );

        return hash_equals($computedSignature, $signature);
    }

    public function parseWebhookPayload(array $payload): WebhookTransaction
    {
        return new WebhookTransaction(
            externalId: $payload['external_id'],
            status: $this->mapXenditStatus($payload['status']),
            amount: $payload['amount'],
            paidAt: $payload['paid_at'] ?? null,
            provider: 'xendit'
        );
    }

    public function getProviderName(): string
    {
        return 'xendit';
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
            'Content-Type' => 'application/json',
        ];
    }

    private function mapXenditStatus(string $xenditStatus): string
    {
        $statusMap = [
            'ACTIVE' => 'pending',
            'PAID' => 'success',
            'EXPIRED' => 'expired',
            'FAILED' => 'failed',
        ];

        return $statusMap[$xenditStatus] ?? 'unknown';
    }
}
```

### 3. ProviderFactory

```php
class ProviderFactory
{
    public static function create(string $providerType, array $credentials): PaymentProviderInterface
    {
        return match($providerType) {
            'midtrans' => new MidtransProvider(
                $credentials['server_key'],
                $credentials['client_key']
            ),
            'xendit' => new XenditProvider(
                $credentials['api_key'],
                $credentials['secret_key']
            ),
            default => throw new UnsupportedProviderException(
                "Provider '{$providerType}' is not supported"
            )
        };
    }
}
```

### 4. PaymentProviderCredential Model

```php
class PaymentProviderCredential extends Model
{
    protected $table = 'payment_provider_credentials';

    protected $fillable = [
        'user_id',
        'provider',
        'api_key_encrypted',
        'secret_key_encrypted',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getApiKeyAttribute(): string
    {
        return Crypt::decryptString($this->api_key_encrypted);
    }

    public function getSecretKeyAttribute(): string
    {
        return Crypt::decryptString($this->secret_key_encrypted);
    }

    public function setApiKeyAttribute(string $value): void
    {
        $this->attributes['api_key_encrypted'] = Crypt::encryptString($value);
    }

    public function setSecretKeyAttribute(string $value): void
    {
        $this->attributes['secret_key_encrypted'] = Crypt::encryptString($value);
    }
}
```

### 5. QrisService Enhancement

```php
class QrisService
{
    private ProviderFactory $providerFactory;
    private PaymentProviderCredential $credentialModel;

    public function generateQris(QrisRequest $request): QrisResponse
    {
        // Get active provider for user
        $credential = $this->getActiveProviderCredential($request->userId);

        if (!$credential) {
            throw new NoActiveProviderException(
                'No payment provider configured for this user'
            );
        }

        // Create provider instance
        $provider = $this->providerFactory->create(
            $credential->provider,
            [
                'api_key' => $credential->api_key,
                'secret_key' => $credential->secret_key,
            ]
        );

        // Generate QRIS
        $response = $provider->generateQris($request);

        // Store transaction
        $this->storeQrisTransaction($response);

        return $response;
    }

    public function handleWebhook(string $provider, array $payload, string $signature): void
    {
        $credential = PaymentProviderCredential::where('provider', $provider)
            ->firstOrFail();

        $providerInstance = $this->providerFactory->create(
            $provider,
            [
                'api_key' => $credential->api_key,
                'secret_key' => $credential->secret_key,
            ]
        );

        // Verify webhook signature
        if (!$providerInstance->verifyWebhook($payload, $signature)) {
            throw new InvalidWebhookException('Invalid webhook signature');
        }

        // Parse and update transaction
        $transaction = $providerInstance->parseWebhookPayload($payload);
        $this->updateTransactionStatus($transaction);
    }

    private function getActiveProviderCredential(int $userId): ?PaymentProviderCredential
    {
        return PaymentProviderCredential::where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    private function storeQrisTransaction(QrisResponse $response): void
    {
        QrisTransaction::create([
            'external_id' => $response->externalId,
            'qr_string' => $response->qrString,
            'reference_id' => $response->referenceId,
            'amount' => $response->amount,
            'provider' => $response->provider,
            'status' => 'pending',
        ]);
    }

    private function updateTransactionStatus(WebhookTransaction $transaction): void
    {
        QrisTransaction::where('external_id', $transaction->externalId)
            ->update([
                'status' => $transaction->status,
                'paid_at' => $transaction->paidAt,
            ]);
    }
}
```

## Data Models

### PaymentProviderCredential Table

```sql
CREATE TABLE payment_provider_credentials (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    api_key_encrypted LONGTEXT NOT NULL,
    secret_key_encrypted LONGTEXT NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_provider (user_id, provider)
);
```

### QrisTransaction Table (Enhanced)

```sql
ALTER TABLE qris_transactions ADD COLUMN (
    provider VARCHAR(50) DEFAULT 'midtrans',
    reference_id VARCHAR(255),
    paid_at TIMESTAMP NULL
);
```

### DTOs

```php
class QrisRequest
{
    public function __construct(
        public int $userId,
        public string $externalId,
        public int $amount,
        public string $callbackUrl,
    ) {}
}

class QrisResponse
{
    public function __construct(
        public string $qrString,
        public string $referenceId,
        public string $externalId,
        public int $amount,
        public string $provider,
    ) {}
}

class WebhookTransaction
{
    public function __construct(
        public string $externalId,
        public string $status,
        public int $amount,
        public ?string $paidAt,
        public string $provider,
    ) {}
}
```

## Error Handling

### Custom Exceptions

```php
class ProviderException extends Exception {}
class UnsupportedProviderException extends ProviderException {}
class NoActiveProviderException extends ProviderException {}
class InvalidWebhookException extends ProviderException {}
class CredentialEncryptionException extends ProviderException {}
```

### Error Responses

```php
// Invalid credentials
{
    "error": "Invalid Xendit credentials",
    "code": "INVALID_CREDENTIALS"
}

// Provider unreachable
{
    "error": "Payment provider is temporarily unavailable. Please try again later.",
    "code": "PROVIDER_UNAVAILABLE"
}

// Missing parameters
{
    "error": "Missing required parameters: amount, externalId",
    "code": "VALIDATION_ERROR"
}
```

## Testing Strategy

### Unit Tests
- Test ProviderFactory instantiation for both providers
- Test credential encryption/decryption
- Test QrisService provider selection logic
- Test webhook signature verification
- Test status mapping for both providers
- Test error handling and exception throwing

### Property-Based Tests
- **Property 1: Provider Consistency** - For any valid QrisRequest, both providers should return QrisResponse with required fields
- **Property 2: Webhook Round Trip** - For any webhook payload, parsing and storing should preserve transaction data
- **Property 3: Credential Encryption** - For any credentials, encrypt then decrypt should return original values
- **Property 4: Status Mapping Idempotence** - Mapping provider status multiple times should return same result

### Integration Tests
- Test full QRIS generation flow with Xendit API (mocked)
- Test webhook handling end-to-end
- Test provider switching for same user
- Test backward compatibility with existing Midtrans flow

## Correctness Properties

A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.

### Property 1: Provider Factory Creates Correct Instance

**For any** valid provider type and credentials, the ProviderFactory should instantiate the correct provider class that implements PaymentProviderInterface.

**Validates: Requirements 6.1, 6.2, 6.3**

### Property 2: Credential Encryption Round Trip

**For any** valid API key and secret key, encrypting then decrypting should return the original values unchanged.

**Validates: Requirements 2.1, 2.2, 2.3**

### Property 3: QRIS Response Contains Required Fields

**For any** successful QRIS generation, the QrisResponse should contain qrString, referenceId, externalId, amount, and provider fields.

**Validates: Requirements 1.4, 4.2, 4.3**

### Property 4: Webhook Signature Verification Consistency

**For any** valid webhook payload and signature, verifyWebhook should return true for the correct provider and false for incorrect signatures.

**Validates: Requirements 5.1, 5.2**

### Property 5: Status Mapping Idempotence

**For any** provider status string, mapping it to standard status multiple times should return the same result.

**Validates: Requirements 5.3, 5.4**

### Property 6: Active Provider Selection

**For any** user with multiple configured providers, getActiveProviderCredential should return only the provider marked as active.

**Validates: Requirements 3.2, 3.3, 3.4**

### Property 7: Backward Compatibility

**For any** existing Midtrans transaction, the system should continue to process it without modification when Xendit is added.

**Validates: Requirements 7.1, 7.2, 7.3**

### Property 8: Error Message Sanitization

**For any** provider error, the error message returned to users should not contain sensitive information like API keys or internal details.

**Validates: Requirements 8.5**

## Security Considerations

1. **Credential Encryption**: All API keys and secrets are encrypted using Laravel's Crypt facade with APP_KEY
2. **Webhook Signature Verification**: All webhooks are verified using HMAC-SHA256 before processing
3. **Audit Logging**: All credential access attempts are logged for security auditing
4. **Error Message Sanitization**: Sensitive information is never exposed in error messages
5. **HTTPS Only**: All API calls to providers use HTTPS
6. **Rate Limiting**: Implement rate limiting on webhook endpoints to prevent abuse

## Migration Path

1. Create `payment_provider_credentials` table
2. Add `provider` and `reference_id` columns to `qris_transactions`
3. Create XenditProvider class
4. Update ProviderFactory to support Xendit
5. Create migration to set existing users' provider to 'midtrans'
6. Add provider selection UI to settings
7. Update webhook routes to support both providers
