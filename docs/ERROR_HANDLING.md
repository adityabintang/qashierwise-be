# Multi-Provider QRIS Error Handling

This document describes the error handling and sanitization system implemented for the Multi-Provider QRIS BYOK feature.

## Overview

The error handling system provides:
- **Encryption error sanitization** - Prevents exposure of technical encryption details
- **RLS violation handling** - Generic security messages for unauthorized access
- **Provider error classification** - Distinguishes network, credential, and provider errors
- **User-friendly error mapping** - Translates technical errors to actionable messages

## Exception Classes

### EncryptionException
Thrown when encryption or decryption operations fail.

```php
throw new EncryptionException('Encryption operation failed');
```

**User Message**: "Unable to process your credentials securely. Please try again or contact support if the issue persists."

### RLSViolationException
Thrown when Row Level Security policies are violated.

```php
throw new RLSViolationException('Access denied');
```

**User Message**: "You do not have permission to access this resource."

### ProviderException
Thrown when payment provider operations fail. Supports error type classification.

```php
// Network error
throw ProviderException::networkError('xendit');

// Credential error
throw ProviderException::credentialError('doku', 'Invalid client_id', 'AUTH_FAILED');

// Provider error
throw ProviderException::providerError('midtrans', 'Amount exceeds limit', 'AMOUNT_LIMIT');
```

**Error Types**:
- `network` - Connection/timeout issues
- `credential` - Invalid API keys/authentication
- `provider` - Provider-specific business logic errors
- `unknown` - Unclassified errors

### NoActiveProviderException
Thrown when QRIS generation is attempted without an active provider.

```php
throw new NoActiveProviderException();
```

**User Message**: "Please configure and activate a payment provider before generating QRIS codes."

### UnsupportedProviderException
Thrown when an unsupported payment provider is requested.

```php
throw new UnsupportedProviderException('stripe');
```

**User Message**: "Unsupported payment provider: stripe"

## Middleware

### SanitizeProviderErrors

Applied to provider-related routes to catch and sanitize errors.

**Features**:
- Logs technical details for debugging
- Returns sanitized error messages to users
- Classifies unknown exceptions by analyzing error messages
- Detects RLS violations from database exceptions

**Usage**:
```php
Route::prefix('providers')->middleware('sanitize.provider.errors')->group(function () {
    // Provider routes
});
```

## Error Response Format

All errors follow a consistent JSON format:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "User-friendly error message",
    "provider": "provider_name",  // For provider errors
    "error_type": "network"        // For provider errors
  }
}
```

## Error Codes

| Code | Description | HTTP Status |
|------|-------------|-------------|
| `ENCRYPTION_ERROR` | Encryption/decryption failed | 500 |
| `ACCESS_DENIED` | RLS violation or unauthorized access | 403 |
| `PROVIDER_NETWORK_ERROR` | Network connectivity issue | 503 |
| `PROVIDER_CREDENTIAL_ERROR` | Invalid API credentials | 401 |
| `PROVIDER_ERROR` | Provider-specific error | 400 |
| `NO_ACTIVE_PROVIDER` | No provider configured | 400 |
| `UNSUPPORTED_PROVIDER` | Invalid provider name | 400 |
| `NETWORK_ERROR` | Generic network error | 503 |
| `CREDENTIAL_ERROR` | Generic credential error | 401 |
| `INTERNAL_ERROR` | Unclassified error | 500 |

## Logging

All errors are logged with appropriate context:

- **Encryption errors**: Logged as `error` with technical details
- **RLS violations**: Logged as `warning` with user/IP information
- **Provider errors**: Logged as `error` with provider and error type
- **Unknown errors**: Logged as `error` with full stack trace

## Security Considerations

1. **No Technical Details**: Error messages never expose:
   - Encryption algorithms or key details
   - Database schema or query information
   - Internal application structure
   - Stack traces or file paths

2. **RLS Violations**: Always return generic "Access Denied" message regardless of:
   - Which table was accessed
   - What data was requested
   - Why access was denied

3. **Provider Errors**: Sanitize provider-specific errors while preserving:
   - Error type classification
   - Actionable guidance for users
   - Provider name for context

## Testing

Comprehensive test coverage includes:
- Unit tests for middleware error handling
- Integration tests for API endpoints
- Error classification accuracy
- Message sanitization verification

Run tests:
```bash
php artisan test tests/Unit/Middleware/SanitizeProviderErrorsTest.php
php artisan test tests/Feature/ProviderErrorHandlingIntegrationTest.php
```

## Requirements Validation

This implementation satisfies:
- **Requirement 12.1**: User-friendly error message mapping
- **Requirement 12.2**: Encryption error sanitization
- **Requirement 12.3**: Provider error classification (network vs credential)
- **Requirement 12.5**: RLS violation error sanitization
