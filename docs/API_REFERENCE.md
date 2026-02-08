# QRIS Multi-Provider API Reference

## Overview

This document provides a complete reference for the QRIS multi-provider API endpoints. All endpoints require authentication unless otherwise specified.

## Base URL

```
https://yourdomain.com/api
```

## Authentication

All API requests require a Bearer token in the Authorization header:

```
Authorization: Bearer {your_access_token}
```

## Response Format

All responses follow this standard format:

### Success Response

```json
{
  "success": true,
  "data": {
    // Response data
  },
  "message": "Optional success message"
}
```

### Error Response

```json
{
  "success": false,
  "error": "Error message",
  "code": "ERROR_CODE",
  "details": {
    // Optional error details
  }
}
```

## Endpoints

### Provider Credentials

#### List Provider Credentials

Get all configured payment providers for the authenticated user.

**Endpoint**: `GET /api/provider-credentials`

**Headers**:
```
Authorization: Bearer {token}
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "provider": "midtrans",
      "is_active": false,
      "created_at": "2025-01-01T00:00:00Z",
      "updated_at": "2025-01-01T00:00:00Z"
    },
    {
      "id": 2,
      "provider": "xendit",
      "is_active": true,
      "created_at": "2025-01-02T00:00:00Z",
      "updated_at": "2025-01-02T00:00:00Z"
    }
  ]
}
```

#### Store Provider Credentials

Save new payment provider credentials.

**Endpoint**: `POST /api/provider-credentials`

**Headers**:
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body**:
```json
{
  "provider": "xendit",
  "api_key": "xnd_development_your_api_key",
  "secret_key": "your_webhook_verification_token"
}
```

**Parameters**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| provider | string | Yes | Provider name: 'midtrans' or 'xendit' |
| api_key | string | Yes | Provider API key |
| secret_key | string | Yes | Provider secret key or webhook token |

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Provider credentials saved successfully",
  "data": {
    "id": 2,
    "provider": "xendit",
    "is_active": false,
    "created_at": "2025-01-02T00:00:00Z"
  }
}
```

**Error Responses**:

- **400 Bad Request**: Invalid provider or missing fields
```json
{
  "success": false,
  "error": "Invalid provider type",
  "code": "INVALID_PROVIDER"
}
```

- **422 Unprocessable Entity**: Validation failed
```json
{
  "success": false,
  "error": "Validation failed",
  "code": "VALIDATION_ERROR",
  "details": {
    "api_key": ["The api key field is required."]
  }
}
```

#### Activate Provider

Set a provider as the active provider for QRIS generation.

**Endpoint**: `POST /api/provider-credentials/{id}/activate`

**Headers**:
```
Authorization: Bearer {token}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Provider activated successfully",
  "data": {
    "id": 2,
    "provider": "xendit",
    "is_active": true
  }
}
```

**Error Responses**:

- **404 Not Found**: Provider credential not found
```json
{
  "success": false,
  "error": "Provider credential not found",
  "code": "NOT_FOUND"
}
```

#### Delete Provider Credentials

Remove provider credentials.

**Endpoint**: `DELETE /api/provider-credentials/{id}`

**Headers**:
```
Authorization: Bearer {token}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Provider credentials deleted successfully"
}
```

### QRIS Generation

#### Generate QRIS Code

Generate a dynamic QRIS code using the active provider.

**Endpoint**: `POST /api/qris/generate`

**Headers**:
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body**:
```json
{
  "amount": 50000,
  "order_id": "ORDER-2025-001",
  "description": "Payment for order #001",
  "customer_name": "John Doe"
}
```

**Parameters**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| amount | integer | Yes | Amount in IDR (minimum: 1) |
| order_id | string | No | Custom order ID (auto-generated if not provided) |
| description | string | No | Transaction description |
| customer_name | string | No | Customer name |

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "qr_string": "00020101021126...",
    "reference_id": "qr_123456789",
    "external_id": "ORDER-2025-001",
    "amount": 50000,
    "provider": "xendit",
    "status": "pending",
    "created_at": "2025-01-01T12:00:00Z",
    "expires_at": "2025-01-01T12:15:00Z"
  }
}
```

**Response Fields**:

| Field | Type | Description |
|-------|------|-------------|
| qr_string | string | QR code data (use to generate QR image) |
| reference_id | string | Provider's transaction reference |
| external_id | string | Your order/transaction ID |
| amount | integer | Transaction amount in IDR |
| provider | string | Provider used ('xendit' or 'midtrans') |
| status | string | Transaction status ('pending') |
| created_at | string | ISO 8601 timestamp |
| expires_at | string | ISO 8601 timestamp (when QR expires) |

**Error Responses**:

- **400 Bad Request**: Invalid request
```json
{
  "success": false,
  "error": "Amount must be positive",
  "code": "INVALID_AMOUNT"
}
```

- **401 Unauthorized**: Invalid credentials
```json
{
  "success": false,
  "error": "Invalid Xendit credentials",
  "code": "INVALID_CREDENTIALS"
}
```

- **404 Not Found**: No active provider
```json
{
  "success": false,
  "error": "No payment provider configured for this user",
  "code": "NO_ACTIVE_PROVIDER"
}
```

- **503 Service Unavailable**: Provider unavailable
```json
{
  "success": false,
  "error": "Payment provider is temporarily unavailable. Please try again later.",
  "code": "PROVIDER_UNAVAILABLE"
}
```

### Transaction Management

#### Get Transaction Status

Check the status of a specific transaction.

**Endpoint**: `GET /api/qris/transactions/{external_id}`

**Headers**:
```
Authorization: Bearer {token}
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "external_id": "ORDER-2025-001",
    "reference_id": "qr_123456789",
    "amount": 50000,
    "status": "success",
    "provider": "xendit",
    "qr_string": "00020101021126...",
    "created_at": "2025-01-01T12:00:00Z",
    "paid_at": "2025-01-01T12:05:00Z"
  }
}
```

**Status Values**:

| Status | Description |
|--------|-------------|
| pending | Awaiting payment |
| success | Payment received |
| expired | QR code expired without payment |
| failed | Payment failed |

**Error Responses**:

- **404 Not Found**: Transaction not found
```json
{
  "success": false,
  "error": "Transaction not found",
  "code": "NOT_FOUND"
}
```

#### List Transactions

Get a list of all transactions for the authenticated user.

**Endpoint**: `GET /api/qris/transactions`

**Headers**:
```
Authorization: Bearer {token}
```

**Query Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| status | string | Filter by status (pending, success, expired, failed) |
| provider | string | Filter by provider (xendit, midtrans) |
| from_date | string | Filter from date (ISO 8601) |
| to_date | string | Filter to date (ISO 8601) |
| page | integer | Page number (default: 1) |
| per_page | integer | Items per page (default: 15, max: 100) |

**Example Request**:
```
GET /api/qris/transactions?status=success&provider=xendit&page=1&per_page=20
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "external_id": "ORDER-2025-001",
      "reference_id": "qr_123456789",
      "amount": 50000,
      "status": "success",
      "provider": "xendit",
      "created_at": "2025-01-01T12:00:00Z",
      "paid_at": "2025-01-01T12:05:00Z"
    },
    {
      "external_id": "ORDER-2025-002",
      "reference_id": "qr_987654321",
      "amount": 75000,
      "status": "pending",
      "provider": "xendit",
      "created_at": "2025-01-01T13:00:00Z",
      "paid_at": null
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 2,
    "last_page": 1
  }
}
```

### Webhooks

Webhook endpoints are called by payment providers to notify about payment status changes.

#### Xendit Webhook

Receives payment notifications from Xendit.

**Endpoint**: `POST /api/webhooks/xendit`

**Headers**:
```
Content-Type: application/json
x-callback-token: {webhook_verification_token}
```

**Request Body** (from Xendit):
```json
{
  "id": "qr_123456789",
  "external_id": "ORDER-2025-001",
  "type": "DYNAMIC",
  "status": "PAID",
  "amount": 50000,
  "currency": "IDR",
  "qr_string": "00020101021126...",
  "callback_url": "https://yourdomain.com/api/webhooks/xendit",
  "created": "2025-01-01T10:00:00Z",
  "updated": "2025-01-01T12:00:00Z",
  "paid_at": "2025-01-01T12:00:00Z"
}
```

**Response** (200 OK):
```json
{
  "success": true
}
```

**Error Responses**:

- **401 Unauthorized**: Invalid signature
```json
{
  "success": false,
  "error": "Invalid webhook signature",
  "code": "INVALID_SIGNATURE"
}
```

#### Midtrans Webhook

Receives payment notifications from Midtrans.

**Endpoint**: `POST /api/webhooks/midtrans`

**Headers**:
```
Content-Type: application/json
```

**Request Body** (from Midtrans):
```json
{
  "transaction_time": "2025-01-01 12:00:00",
  "transaction_status": "settlement",
  "transaction_id": "mid_123456",
  "status_message": "midtrans payment notification",
  "status_code": "200",
  "signature_key": "abc123...",
  "payment_type": "qris",
  "order_id": "ORDER-2025-001",
  "merchant_id": "M123456",
  "gross_amount": "50000.00",
  "fraud_status": "accept",
  "currency": "IDR"
}
```

**Response** (200 OK):
```json
{
  "success": true
}
```

## Error Codes

| Code | Description |
|------|-------------|
| INVALID_PROVIDER | Unsupported provider type |
| INVALID_CREDENTIALS | Provider API credentials are invalid |
| NO_ACTIVE_PROVIDER | No payment provider configured |
| PROVIDER_UNAVAILABLE | Provider API is temporarily unavailable |
| INVALID_AMOUNT | Transaction amount is invalid |
| INVALID_SIGNATURE | Webhook signature verification failed |
| VALIDATION_ERROR | Request validation failed |
| NOT_FOUND | Resource not found |
| UNAUTHORIZED | Authentication failed |

## Rate Limiting

API endpoints are rate-limited to prevent abuse:

- **QRIS Generation**: 60 requests per minute per user
- **Webhook Endpoints**: 1000 requests per minute (global)
- **Other Endpoints**: 120 requests per minute per user

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1640995200
```

## Pagination

List endpoints support pagination with these parameters:

- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15, max: 100)

Pagination metadata is included in the `meta` field:

```json
{
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7,
    "from": 1,
    "to": 15
  }
}
```

## Webhooks Best Practices

1. **Verify Signatures**: Always verify webhook signatures before processing
2. **Respond Quickly**: Return 200 OK within 5 seconds
3. **Process Asynchronously**: Queue webhook processing for complex operations
4. **Handle Duplicates**: Implement idempotency to handle duplicate webhooks
5. **Log Everything**: Log all webhook receipts for debugging

## Testing

### Test Credentials

Use test credentials for development:

**Xendit**:
- API Key: `xnd_development_your_test_key`
- Webhook Token: `your_test_webhook_token`

**Midtrans**:
- Server Key: `SB-Mid-server-your_test_key`
- Client Key: `SB-Mid-client-your_test_key`

### Test Webhook

Test webhooks manually using curl:

```bash
# Test Xendit webhook
curl -X POST https://yourdomain.com/api/webhooks/xendit \
  -H "Content-Type: application/json" \
  -H "x-callback-token: your_webhook_token" \
  -d '{
    "id": "qr_test_123",
    "external_id": "TEST-ORDER-001",
    "status": "PAID",
    "amount": 50000,
    "paid_at": "2025-01-01T12:00:00Z"
  }'
```

## SDKs and Libraries

### PHP Example

```php
use Illuminate\Support\Facades\Http;

// Generate QRIS
$response = Http::withToken($token)
    ->post('https://yourdomain.com/api/qris/generate', [
        'amount' => 50000,
        'order_id' => 'ORDER-2025-001'
    ]);

$qrisData = $response->json()['data'];
```

### JavaScript Example

```javascript
// Generate QRIS
const response = await fetch('https://yourdomain.com/api/qris/generate', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    amount: 50000,
    order_id: 'ORDER-2025-001'
  })
});

const data = await response.json();
const qrisData = data.data;
```

### Python Example

```python
import requests

# Generate QRIS
response = requests.post(
    'https://yourdomain.com/api/qris/generate',
    headers={
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json'
    },
    json={
        'amount': 50000,
        'order_id': 'ORDER-2025-001'
    }
)

qris_data = response.json()['data']
```

## Support

For API issues or questions:

- Check [Error Handling Guide](ERROR_HANDLING.md)
- Review [Webhook Setup Guide](WEBHOOK_SETUP_AND_TESTING.md)
- Check application logs: `storage/logs/laravel.log`
- Contact support with request ID from error response

## Changelog

### Version 2.0 (2025-01-01)
- Added multi-provider support
- Added Xendit provider
- Enhanced credential management
- Improved error handling

### Version 1.0 (2024-12-01)
- Initial release with Midtrans support
