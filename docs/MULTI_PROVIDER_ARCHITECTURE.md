# Multi-Provider Architecture Documentation

## Overview

This document describes the architecture and design patterns used to support multiple QRIS payment providers (Midtrans and Xendit) in the application. The system uses a provider factory pattern with a common interface to enable seamless switching between providers.

## Architecture Principles

### 1. Provider Abstraction

All payment providers implement a common interface (`PaymentProviderInterface`), ensuring consistent behavior regardless of the underlying provider.

### 2. Factory Pattern

The `ProviderFactory` instantiates the appropriate provider based on user configuration, encapsulating provider-specific logic.

### 3. Secure Credential Management

Provider credentials are encrypted at rest and only decrypted when needed for API calls.

### 4. Backward Compatibility

The system maintains full backward compatibility with existing Midtrans integrations while adding Xendit support.

## Component Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Controllers  │  │   Services   │  │     Jobs     │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
└─────────┼──────────────────┼──────────────────┼─────────────┘
          │                  │                  │
          └──────────────────┼──────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│                      QrisService                             │
│  - generateQris()                                            │
│  - handleWebhook()                                           │
│  - getActiveProviderCredential()                             │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│                    ProviderFactory                           │
│  - create(providerType, credentials)                         │
│  - Instantiates correct provider                             │
│  - Handles credential decryption                             │
└────────────────────────────┬────────────────────────────────┘
                             │
                ┌────────────┴────────────┐
                │                         │
┌───────────────▼──────────┐    ┌────────▼──────────────┐
│   MidtransProvider       │    │   XenditProvider      │
│   (Existing)             │    │   (New)               │
│                          │    │                       │
│ - generateQris()         │    │ - generateQris()      │
│ - verifyWebhook()        │    │ - verifyWebhook()     │
│ - parseWebhookPayload()  │    │ - parseWebhookPayload()│
│ - getProviderName()      │    │ - getProviderName()   │
└───────────┬──────────────┘    └────────┬──────────────┘
            │                            │
            └────────────┬───────────────┘
                         │
        ┌────────────────▼────────────────┐
        │  PaymentProviderInterface       │
        │  (Contract)                     │
        │                                 │
        │ + generateQris()                │
        │ + verifyWebhook()               │
        │ + parseWebhookPayload()         │
        │ + getProviderName()             │
        └─────────────────────────────────┘
```

## Core Components

### 1. PaymentProviderInterface

**Location**: `app/Contracts/PaymentProviderInterface.php`

**Purpose**: Defines the contract that all payment providers must implement.

**Methods**:

```php
interface PaymentProviderInterface
{
    /**
     * Generate a dynamic QRIS code
     * 
     * @param QrisRequest $request The QRIS generation request
     * @return QrisResponse The generated QRIS response
     * @throws ProviderException If generation fails
     */
    public function generateQris(QrisRequest $request): QrisResponse;

    /**
     * Verify webhook signature from provider
     * 
     * @param array $payload The webhook payload
     * @param string $signature The signature to verify
     * @return bool True if signature is valid
     */
    public function verifyWebhook(array $payload, string $signature): bool;

    /**
     * Parse webhook payload into standard format
     * 
     * @param array $payload The raw webhook payload
     * @return WebhookTransaction The parsed transaction data
     */
    public function parseWebhookPayload(array $payload): WebhookTransaction;

    /**
     * Get the provider name
     * 
     * @return string The provider identifier (e.g., 'xendit', 'midtrans')
     */
    public function getProviderName(): string;
}
```

**Design Rationale**:
- Ensures all providers have consistent method signatures
- Enables polymorphism and dependency injection
- Facilitates testing with mock providers
- Makes adding new providers straightforward

### 2. ProviderFactory

**Location**: `app/Services/PaymentProviders/ProviderFactory.php`

**Purpose**: Creates provider instances based on configuration.

**Implementation**:

```php
class ProviderFactory
{
    /**
     * Create a payment provider instance
     * 
     * @param string $providerType The provider type ('midtrans' or 'xendit')
     * @param array $credentials The provider credentials
     * @return PaymentProviderInterface The provider instance
     * @throws UnsupportedProviderException If provider type is not supported
     */
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

**Design Rationale**:
- Centralizes provider instantiation logic
- Uses PHP 8 match expression for clean syntax
- Throws specific exception for unsupported providers
- Easy to extend with new providers

### 3. XenditProvider

**Location**: `app/Services/PaymentProviders/XenditProvider.php`

**Purpose**: Implements Xendit-specific QRIS integration.

**Key Features**:
- QRIS code generation via Xendit API
- Webhook signature verification using HMAC-SHA256
- Status mapping from Xendit to standard format
- Error handling and logging

**Status Mapping**:

```php
private function mapXenditStatus(string $xenditStatus): string
{
    return match($xenditStatus) {
        'ACTIVE' => 'pending',
        'PAID' => 'success',
        'EXPIRED' => 'expired',
        'FAILED' => 'failed',
        default => 'unknown'
    };
}
```

### 4. QrisService

**Location**: `app/Services/QrisService.php`

**Purpose**: Orchestrates QRIS operations across providers.

**Key Methods**:

```php
class QrisService
{
    /**
     * Generate a QRIS code using the active provider
     */
    public function generateQris(QrisRequest $request): QrisResponse
    {
        // Get active provider credential
        $credential = $this->getActiveProviderCredential($request->userId);
        
        // Create provider instance
        $provider = ProviderFactory::create(
            $credential->provider,
            [
                'api_key' => $credential->api_key,
                'secret_key' => $credential->secret_key,
            ]
        );
        
        // Generate QRIS
        return $provider->generateQris($request);
    }

    /**
     * Handle webhook notification from provider
     */
    public function handleWebhook(string $provider, array $payload, string $signature): void
    {
        // Get provider credential
        $credential = PaymentProviderCredential::where('provider', $provider)->firstOrFail();
        
        // Create provider instance
        $providerInstance = ProviderFactory::create(
            $provider,
            [
                'api_key' => $credential->api_key,
                'secret_key' => $credential->secret_key,
            ]
        );
        
        // Verify signature
        if (!$providerInstance->verifyWebhook($payload, $signature)) {
            throw new InvalidWebhookException('Invalid webhook signature');
        }
        
        // Parse and update transaction
        $transaction = $providerInstance->parseWebhookPayload($payload);
        $this->updateTransactionStatus($transaction);
    }
}
```

## Data Models

### PaymentProviderCredential

**Location**: `app/Models/PaymentProviderCredential.php`

**Purpose**: Stores encrypted provider credentials.

**Schema**:

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

**Key Features**:
- Automatic encryption/decryption via accessors/mutators
- One active provider per user constraint
- Unique constraint on (user_id, provider)

**Encryption Implementation**:

```php
class PaymentProviderCredential extends Model
{
    // Decrypt when accessing
    public function getApiKeyAttribute(): string
    {
        return Crypt::decryptString($this->api_key_encrypted);
    }

    // Encrypt when setting
    public function setApiKeyAttribute(string $value): void
    {
        $this->attributes['api_key_encrypted'] = Crypt::encryptString($value);
    }
}
```

### QrisTransaction (Enhanced)

**Location**: `app/Models/QrisTransaction.php`

**Enhanced Schema**:

```sql
ALTER TABLE qris_transactions ADD COLUMN (
    provider VARCHAR(50) DEFAULT 'midtrans',
    reference_id VARCHAR(255),
    paid_at TIMESTAMP NULL
);
```

**Purpose**: Tracks which provider was used for each transaction.

## Data Transfer Objects (DTOs)

### QrisRequest

**Purpose**: Encapsulates QRIS generation request data.

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
```

### QrisResponse

**Purpose**: Standardizes QRIS generation response across providers.

```php
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
```

### WebhookTransaction

**Purpose**: Standardizes webhook data across providers.

```php
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

### Exception Hierarchy

```
Exception
    └── ProviderException (Base)
        ├── UnsupportedProviderException
        ├── NoActiveProviderException
        ├── InvalidWebhookException
        └── CredentialEncryptionException
```

### Error Response Format

```php
class ErrorResponse
{
    public function __construct(
        public string $error,
        public string $code,
        public ?array $details = null
    ) {}
}
```

**Example Responses**:

```json
// Invalid credentials
{
    "error": "Invalid Xendit credentials",
    "code": "INVALID_CREDENTIALS"
}

// Provider unavailable
{
    "error": "Payment provider is temporarily unavailable",
    "code": "PROVIDER_UNAVAILABLE"
}

// No active provider
{
    "error": "No payment provider configured for this user",
    "code": "NO_ACTIVE_PROVIDER"
}
```

## Security Considerations

### 1. Credential Encryption

**Implementation**: Laravel's `Crypt` facade with AES-256-CBC

**Key Management**:
- Encryption key stored in `APP_KEY` environment variable
- Key rotation requires re-encryption of all credentials
- Never log or expose decrypted credentials

### 2. Webhook Signature Verification

**Xendit**: HMAC-SHA256 signature verification

```php
$computedSignature = hash_hmac(
    'sha256',
    json_encode($payload),
    $this->secretKey
);

return hash_equals($computedSignature, $signature);
```

**Security Features**:
- Timing-safe comparison using `hash_equals()`
- Signature calculated over entire payload
- Rejects webhooks with invalid signatures

### 3. Audit Logging

**What's Logged**:
- Credential access attempts
- Provider API calls
- Webhook receipts
- Signature verification results
- Transaction status changes

**Implementation**:

```php
Log::info('Provider credential accessed', [
    'user_id' => $userId,
    'provider' => $provider,
    'action' => 'generate_qris',
    'ip' => request()->ip()
]);
```

## Testing Strategy

### Unit Tests

**Provider Tests**:
- Test each provider method independently
- Mock external API calls
- Test error handling
- Test status mapping

**Factory Tests**:
- Test provider instantiation
- Test unsupported provider handling
- Test credential passing

### Property-Based Tests

**Properties Tested**:
1. Provider Factory Creates Correct Instance
2. Credential Encryption Round Trip
3. QRIS Response Contains Required Fields
4. Webhook Signature Verification Consistency
5. Status Mapping Idempotence
6. Active Provider Selection
7. Backward Compatibility
8. Error Message Sanitization

### Integration Tests

**Scenarios**:
- Full QRIS generation flow
- Webhook handling end-to-end
- Provider switching
- Backward compatibility with Midtrans

## Adding New Providers

To add a new payment provider:

### 1. Create Provider Class

```php
class NewProvider implements PaymentProviderInterface
{
    public function generateQris(QrisRequest $request): QrisResponse
    {
        // Implement provider-specific logic
    }

    public function verifyWebhook(array $payload, string $signature): bool
    {
        // Implement signature verification
    }

    public function parseWebhookPayload(array $payload): WebhookTransaction
    {
        // Parse provider-specific payload
    }

    public function getProviderName(): string
    {
        return 'newprovider';
    }
}
```

### 2. Update ProviderFactory

```php
public static function create(string $providerType, array $credentials): PaymentProviderInterface
{
    return match($providerType) {
        'midtrans' => new MidtransProvider(...),
        'xendit' => new XenditProvider(...),
        'newprovider' => new NewProvider(...), // Add here
        default => throw new UnsupportedProviderException(...)
    };
}
```

### 3. Add Webhook Route

```php
// routes/api.php
Route::post('/webhooks/newprovider', [NewProviderWebhookController::class, 'handle']);
```

### 4. Update Documentation

- Add provider to `XENDIT_INTEGRATION.md`
- Update `MERCHANT_CONFIGURATION_GUIDE.md`
- Add webhook setup to `WEBHOOK_SETUP_AND_TESTING.md`

### 5. Write Tests

- Unit tests for provider methods
- Integration tests for full flow
- Property-based tests for correctness

## Performance Considerations

### 1. Credential Caching

Credentials are decrypted on each use. Consider caching:

```php
// Cache decrypted credentials for 1 hour
$credentials = Cache::remember(
    "provider_credentials_{$userId}_{$provider}",
    3600,
    fn() => $this->getDecryptedCredentials($userId, $provider)
);
```

### 2. Async Webhook Processing

For high-volume webhooks, process asynchronously:

```php
// Queue webhook processing
ProcessWebhookJob::dispatch($provider, $payload, $signature);
```

### 3. Database Indexing

Ensure proper indexes:

```sql
CREATE INDEX idx_qris_external_id ON qris_transactions(external_id);
CREATE INDEX idx_qris_provider_status ON qris_transactions(provider, status);
CREATE INDEX idx_credentials_user_active ON payment_provider_credentials(user_id, is_active);
```

## Monitoring and Observability

### Metrics to Track

1. **Provider Usage**:
   - Requests per provider
   - Success/failure rates
   - Average response times

2. **Webhook Health**:
   - Webhooks received
   - Signature verification failures
   - Processing errors

3. **Transaction Status**:
   - Pending transactions
   - Success rate
   - Average time to payment

### Logging Best Practices

```php
// Structured logging
Log::info('QRIS generated', [
    'provider' => $provider,
    'external_id' => $externalId,
    'amount' => $amount,
    'duration_ms' => $duration
]);

// Error logging with context
Log::error('Provider API call failed', [
    'provider' => $provider,
    'endpoint' => $endpoint,
    'status_code' => $statusCode,
    'error' => $errorMessage
]);
```

## Migration Strategy

### Phase 1: Add Xendit Support

1. ✅ Create database migrations
2. ✅ Implement XenditProvider
3. ✅ Update ProviderFactory
4. ✅ Add webhook handling
5. ✅ Write tests

### Phase 2: Backward Compatibility

1. ✅ Set default provider for existing users
2. ✅ Maintain existing Midtrans functionality
3. ✅ Test migration with existing data

### Phase 3: Provider Selection UI

1. ✅ Create provider management interface
2. ✅ Add credential configuration forms
3. ✅ Implement provider activation

### Phase 4: Production Rollout

1. Deploy to staging
2. Test with real provider credentials
3. Monitor for issues
4. Gradual rollout to production

## Conclusion

The multi-provider architecture provides:

- **Flexibility**: Easy to add new providers
- **Maintainability**: Clean separation of concerns
- **Security**: Encrypted credentials and verified webhooks
- **Reliability**: Comprehensive error handling and logging
- **Testability**: Well-defined interfaces and DTOs

This architecture supports the current needs while being extensible for future payment providers.
