# Midtrans Subscription API Integration

## Overview

This document provides detailed information about the Midtrans Subscription API integration in the application. The integration enables recurring subscription payments for Standard and Pro plans.

## API Configuration

### Base URLs

- **Production**: `https://api.midtrans.com/v1`
- **Sandbox**: `https://api.sandbox.midtrans.com/v1`

The environment is controlled by the `MIDTRANS_IS_PRODUCTION` configuration.

### Authentication

All API requests use **HTTP Basic Authentication**:

```
Authorization: Basic base64(server_key:)
```

The server key is base64-encoded with a trailing colon. Example:
```php
$auth = base64_encode($serverKey . ':');
```

### Required Headers

```
Authorization: Basic {encoded_credentials}
Content-Type: application/json
Accept: application/json
```

## API Endpoints

### 1. Create Subscription

Creates a new recurring subscription for a customer.

**Endpoint**: `POST /v1/subscriptions`

**Request Body**:
```json
{
  "name": "Standard Monthly Subscription",
  "amount": "99000",
  "currency": "IDR",
  "payment_type": "credit_card",
  "token": "customer_payment_token",
  "schedule": {
    "interval": 1,
    "interval_unit": "month",
    "max_interval": 12,
    "start_time": "2026-01-24 07:00:00 +0700"
  },
  "metadata": {
    "user_id": "123",
    "plan_id": "standard"
  },
  "customer_details": {
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+628123456789"
  }
}
```

**Parameters**:
- `name` (string, required): Subscription name
- `amount` (string, required): Subscription amount in IDR
- `currency` (string, required): Currency code (IDR)
- `payment_type` (string, required): Payment method (credit_card, gopay, etc.)
- `token` (string, required): Customer payment token from Snap
- `schedule` (object, required): Recurring schedule configuration
  - `interval` (integer): Number of interval units between charges
  - `interval_unit` (string): Unit of time (day, week, month)
  - `max_interval` (integer): Maximum number of recurring charges
  - `start_time` (string): First charge date/time in ISO 8601 format
- `metadata` (object, optional): Custom metadata for tracking
- `customer_details` (object, required): Customer information

**Response** (201 Created):
```json
{
  "id": "sub_1234567890",
  "name": "Standard Monthly Subscription",
  "amount": "99000",
  "currency": "IDR",
  "payment_type": "credit_card",
  "token": "customer_payment_token",
  "status": "active",
  "schedule": {
    "interval": 1,
    "interval_unit": "month",
    "max_interval": 12,
    "start_time": "2026-01-24T00:00:00Z",
    "previous_execution_at": null,
    "next_execution_at": "2026-01-24T00:00:00Z"
  },
  "metadata": {
    "user_id": "123",
    "plan_id": "standard"
  },
  "customer_details": {
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+628123456789"
  },
  "created_at": "2026-01-24T00:00:00Z"
}
```

**Implementation**:
```php
// app/Services/MidtransSubscriptionService.php
public function createSubscription(User $user, string $planId, string $paymentToken): array
{
    $plan = config("subscription.plans.{$planId}");
    
    $payload = [
        'name' => "{$plan['name']} Monthly Subscription",
        'amount' => (string) $plan['price'],
        'currency' => $plan['currency'],
        'payment_type' => 'credit_card',
        'token' => $paymentToken,
        'schedule' => [
            'interval' => $plan['interval_count'],
            'interval_unit' => $plan['interval'],
            'max_interval' => 12,
            'start_time' => now()->addDay()->format('Y-m-d H:i:s O'),
        ],
        'metadata' => [
            'user_id' => (string) $user->id,
            'plan_id' => $planId,
        ],
        'customer_details' => [
            'first_name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '',
        ],
    ];
    
    $response = Http::withBasicAuth($this->serverKey, '')
        ->post("{$this->baseUrl}/subscriptions", $payload);
    
    return $response->json();
}
```

### 2. Get Subscription

Retrieves details of an existing subscription.

**Endpoint**: `GET /v1/subscriptions/{subscription_id}`

**Response** (200 OK):
```json
{
  "id": "sub_1234567890",
  "name": "Standard Monthly Subscription",
  "amount": "99000",
  "currency": "IDR",
  "payment_type": "credit_card",
  "token": "customer_payment_token",
  "status": "active",
  "schedule": {
    "interval": 1,
    "interval_unit": "month",
    "max_interval": 12,
    "start_time": "2026-01-24T00:00:00Z",
    "previous_execution_at": "2026-02-24T00:00:00Z",
    "next_execution_at": "2026-03-24T00:00:00Z"
  },
  "customer_details": {
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+628123456789"
  },
  "created_at": "2026-01-24T00:00:00Z"
}
```

**Implementation**:
```php
public function getSubscription(string $subscriptionId): ?array
{
    $response = Http::withBasicAuth($this->serverKey, '')
        ->get("{$this->baseUrl}/subscriptions/{$subscriptionId}");
    
    if ($response->successful()) {
        return $response->json();
    }
    
    return null;
}
```

### 3. Update Subscription

Updates an existing subscription's details.

**Endpoint**: `PATCH /v1/subscriptions/{subscription_id}`

**Request Body**:
```json
{
  "name": "Updated Subscription Name",
  "amount": "199000",
  "schedule": {
    "interval": 1,
    "interval_unit": "month"
  }
}
```

**Response** (200 OK):
```json
{
  "status_message": "Subscription is updated.",
  "status_code": "200"
}
```

**Implementation**:
```php
public function updateSubscription(string $subscriptionId, array $data): array
{
    $response = Http::withBasicAuth($this->serverKey, '')
        ->patch("{$this->baseUrl}/subscriptions/{$subscriptionId}", $data);
    
    return $response->json();
}
```

### 4. Disable Subscription

Cancels/disables a subscription to prevent future charges.

**Endpoint**: `POST /v1/subscriptions/{subscription_id}/disable`

**Response** (200 OK):
```json
{
  "status_message": "Subscription is updated.",
  "status_code": "200"
}
```

**Implementation**:
```php
public function cancelSubscription(string $subscriptionId): bool
{
    $response = Http::withBasicAuth($this->serverKey, '')
        ->post("{$this->baseUrl}/subscriptions/{$subscriptionId}/disable");
    
    return $response->successful();
}
```

### 5. Enable Subscription

Re-enables a previously disabled subscription.

**Endpoint**: `POST /v1/subscriptions/{subscription_id}/enable`

**Response** (200 OK):
```json
{
  "status_message": "Subscription is updated.",
  "status_code": "200"
}
```

**Implementation**:
```php
public function enableSubscription(string $subscriptionId): bool
{
    $response = Http::withBasicAuth($this->serverKey, '')
        ->post("{$this->baseUrl}/subscriptions/{$subscriptionId}/enable");
    
    return $response->successful();
}
```

## Error Handling

### HTTP Status Codes

- **200 OK**: Request successful
- **201 Created**: Subscription created successfully
- **400 Bad Request**: Invalid request parameters
- **401 Unauthorized**: Invalid authentication credentials
- **404 Not Found**: Subscription not found
- **500 Internal Server Error**: Midtrans server error

### Error Response Format

```json
{
  "status_code": "400",
  "status_message": "Invalid request parameters",
  "validation_messages": [
    "amount must be a valid number"
  ]
}
```

### Error Handling Implementation

```php
try {
    $response = Http::withBasicAuth($this->serverKey, '')
        ->post("{$this->baseUrl}/subscriptions", $payload);
    
    if (!$response->successful()) {
        Log::error('Midtrans API error', [
            'status' => $response->status(),
            'body' => $response->body(),
            'payload' => $payload,
        ]);
        
        throw new \Exception('Failed to create subscription: ' . $response->body());
    }
    
    return $response->json();
} catch (\Exception $e) {
    Log::error('Subscription creation failed', [
        'error' => $e->getMessage(),
        'user_id' => $user->id,
        'plan_id' => $planId,
    ]);
    
    throw $e;
}
```

## Rate Limiting

Midtrans API has rate limits:
- **Production**: 1000 requests per minute
- **Sandbox**: 100 requests per minute

Implement exponential backoff for rate limit errors (HTTP 429).

## Testing

### Sandbox Testing

Use sandbox credentials for testing:
```env
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=false
```

### Test Cards

Midtrans provides test card numbers for sandbox:

**Successful Payment**:
- Card: 4811 1111 1111 1114
- CVV: 123
- Expiry: Any future date

**Failed Payment**:
- Card: 4911 1111 1111 1113
- CVV: 123
- Expiry: Any future date

## Best Practices

1. **Always validate responses**: Check HTTP status codes and response structure
2. **Log all API calls**: Include request/response for debugging
3. **Use timeouts**: Set reasonable timeout values (30 seconds recommended)
4. **Implement retry logic**: Retry failed requests with exponential backoff
5. **Store subscription IDs**: Always save Midtrans subscription ID in database
6. **Handle webhooks**: Use webhooks for real-time status updates
7. **Test thoroughly**: Test all scenarios in sandbox before production

## Security Considerations

1. **Never expose server key**: Keep server key in environment variables
2. **Use HTTPS**: All API calls must use HTTPS
3. **Validate webhook signatures**: Always verify webhook authenticity
4. **Sanitize user input**: Validate all user-provided data before API calls
5. **Rotate credentials**: Periodically rotate API keys

## References

- [Midtrans Subscription API Documentation](https://docs.midtrans.com/reference/create-subscription)
- [Midtrans Authentication Guide](https://docs.midtrans.com/reference/authentication)
- [Midtrans Error Codes](https://docs.midtrans.com/reference/error-codes)
