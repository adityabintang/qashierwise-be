# Midtrans Webhook Event Handling

## Overview

This document describes how the application handles webhook notifications from Midtrans for subscription events. Webhooks enable real-time updates of subscription status without polling the API.

## Webhook Configuration

### Webhook URLs

Configure these URLs in your Midtrans dashboard:

**Production**:
```
Payment Notification URL: https://yourdomain.com/api/webhooks/midtrans
Recurring Notification URL: https://yourdomain.com/api/webhooks/midtrans
Pay Account Notification URL: https://yourdomain.com/api/webhooks/midtrans
```

**Sandbox**:
```
Payment Notification URL: https://sandbox.yourdomain.com/api/webhooks/midtrans
Recurring Notification URL: https://sandbox.yourdomain.com/api/webhooks/midtrans
Pay Account Notification URL: https://sandbox.yourdomain.com/api/webhooks/midtrans
```

### Endpoint Configuration

**Route**: `POST /api/webhooks/midtrans`

**Middleware**: 
- No authentication (webhooks come from Midtrans servers)
- CSRF protection excluded
- Rate limiting enabled (60 requests per minute)

```php
// routes/api.php
Route::post('/webhooks/midtrans', [MidtransWebhookController::class, 'handleSubscriptionWebhook'])
    ->middleware('throttle:60,1')
    ->name('webhooks.midtrans.subscription');
```

## Webhook Types

### 1. Payment Notification

Sent when the first payment is processed.

**Event**: Initial subscription payment

**Payload Example**:
```json
{
  "transaction_time": "2026-01-24 12:00:00",
  "transaction_status": "settlement",
  "transaction_id": "txn_abc123",
  "status_message": "midtrans payment notification",
  "status_code": "200",
  "signature_key": "a1b2c3d4e5f6...",
  "payment_type": "credit_card",
  "order_id": "sub_1234567890_1",
  "merchant_id": "M123456",
  "masked_card": "481111-1114",
  "gross_amount": "99000.00",
  "fraud_status": "accept",
  "currency": "IDR",
  "card_type": "credit",
  "bank": "bni",
  "approval_code": "1234567890"
}
```

**Status Values**:
- `capture`: Payment authorized (credit card)
- `settlement`: Payment successful
- `pending`: Payment pending
- `deny`: Payment denied
- `cancel`: Payment cancelled
- `expire`: Payment expired
- `failure`: Payment failed

### 2. Recurring Notification

Sent for subsequent recurring payments.

**Event**: Recurring subscription payment

**Payload Example**:
```json
{
  "transaction_time": "2026-02-24 12:00:00",
  "transaction_status": "settlement",
  "transaction_id": "txn_def456",
  "subscription_id": "sub_1234567890",
  "status_message": "midtrans payment notification",
  "status_code": "200",
  "signature_key": "g7h8i9j0k1l2...",
  "payment_type": "credit_card",
  "order_id": "sub_1234567890_2",
  "merchant_id": "M123456",
  "gross_amount": "99000.00",
  "fraud_status": "accept",
  "currency": "IDR"
}
```

### 3. Pay Account Notification

Sent when subscription status changes.

**Event**: Subscription status update

**Payload Example**:
```json
{
  "status_code": "200",
  "status_message": "Success",
  "subscription_id": "sub_1234567890",
  "account_id": "acc_1234567890",
  "account_status": "DISABLED"
}
```

**Account Status Values**:
- `ENABLED`: Subscription active
- `DISABLED`: Subscription cancelled/disabled
- `PENDING`: Subscription pending activation

## Signature Validation

### Why Validate Signatures?

Signature validation ensures:
1. Webhook came from Midtrans (authenticity)
2. Payload wasn't tampered with (integrity)
3. Protection against replay attacks

### Signature Algorithm

Midtrans uses **SHA512** hashing:

```
signature = SHA512(order_id + status_code + gross_amount + server_key)
```

### Implementation

```php
private function validateSignature(array $payload): bool
{
    $serverKey = config('midtrans.server_key');
    
    // Extract required fields
    $orderId = $payload['order_id'] ?? '';
    $statusCode = $payload['status_code'] ?? '';
    $grossAmount = $payload['gross_amount'] ?? '';
    $signatureKey = $payload['signature_key'] ?? '';
    
    // Build signature string
    $signatureString = $orderId . $statusCode . $grossAmount . $serverKey;
    
    // Calculate expected signature
    $expectedSignature = hash('sha512', $signatureString);
    
    // Compare using timing-safe comparison
    return hash_equals($expectedSignature, $signatureKey);
}
```

### Validation Flow

```php
public function handleSubscriptionWebhook(Request $request): JsonResponse
{
    $payload = $request->all();
    
    // Step 1: Validate signature
    if (!$this->validateSignature($payload)) {
        Log::warning('Invalid webhook signature', [
            'payload' => $payload,
            'ip' => $request->ip(),
        ]);
        
        return response()->json(['status' => 'invalid signature'], 403);
    }
    
    // Step 2: Process webhook
    try {
        $this->processWebhook($payload);
        return response()->json(['status' => 'ok'], 200);
    } catch (\Exception $e) {
        Log::error('Webhook processing failed', [
            'error' => $e->getMessage(),
            'payload' => $payload,
        ]);
        
        // Return 200 to prevent retries
        return response()->json(['status' => 'ok'], 200);
    }
}
```

## Event Processing

### Processing Flow

```
Webhook Received
    ↓
Validate Signature
    ↓
Extract Event Type
    ↓
Route to Handler
    ↓
Update Database
    ↓
Send Notifications
    ↓
Return Response
```

### Event Routing

```php
private function processWebhook(array $payload): void
{
    $transactionStatus = $payload['transaction_status'] ?? null;
    $subscriptionId = $payload['subscription_id'] ?? null;
    $accountStatus = $payload['account_status'] ?? null;
    
    // Determine event type
    if ($accountStatus) {
        // Pay Account Notification
        $this->handleAccountStatusChange($payload);
    } elseif ($subscriptionId) {
        // Recurring Notification
        $this->handleRecurringPayment($payload);
    } else {
        // Payment Notification
        $this->handlePaymentNotification($payload);
    }
}
```

### Payment Notification Handler

```php
private function handlePaymentNotification(array $payload): void
{
    $orderId = $payload['order_id'];
    $transactionStatus = $payload['transaction_status'];
    
    // Extract subscription ID from order_id (format: sub_xxx_1)
    $subscriptionId = $this->extractSubscriptionId($orderId);
    
    // Find subscription
    $subscription = Subscription::where('midtrans_subscription_id', $subscriptionId)->first();
    
    if (!$subscription) {
        Log::warning('Subscription not found for webhook', [
            'subscription_id' => $subscriptionId,
            'order_id' => $orderId,
        ]);
        return;
    }
    
    // Update based on status
    switch ($transactionStatus) {
        case 'capture':
        case 'settlement':
            $subscription->update([
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);
            
            Log::info('Subscription activated', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);
            break;
            
        case 'pending':
            $subscription->update(['status' => 'pending']);
            break;
            
        case 'deny':
        case 'cancel':
        case 'expire':
        case 'failure':
            $subscription->update(['status' => 'cancelled']);
            
            Log::warning('Subscription payment failed', [
                'subscription_id' => $subscription->id,
                'status' => $transactionStatus,
            ]);
            break;
    }
}
```

### Recurring Payment Handler

```php
private function handleRecurringPayment(array $payload): void
{
    $subscriptionId = $payload['subscription_id'];
    $transactionStatus = $payload['transaction_status'];
    
    $subscription = Subscription::where('midtrans_subscription_id', $subscriptionId)->first();
    
    if (!$subscription) {
        Log::warning('Subscription not found for recurring payment', [
            'subscription_id' => $subscriptionId,
        ]);
        return;
    }
    
    if ($transactionStatus === 'settlement') {
        // Extend subscription period
        $subscription->update([
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
        
        Log::info('Subscription renewed', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
        ]);
    } else {
        // Payment failed - handle dunning
        Log::warning('Recurring payment failed', [
            'subscription_id' => $subscription->id,
            'status' => $transactionStatus,
        ]);
        
        // Keep subscription active for grace period
        // Implement dunning logic here
    }
}
```

### Account Status Handler

```php
private function handleAccountStatusChange(array $payload): void
{
    $subscriptionId = $payload['subscription_id'];
    $accountStatus = $payload['account_status'];
    
    $subscription = Subscription::where('midtrans_subscription_id', $subscriptionId)->first();
    
    if (!$subscription) {
        return;
    }
    
    switch ($accountStatus) {
        case 'ENABLED':
            $subscription->update(['status' => 'active']);
            break;
            
        case 'DISABLED':
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
            break;
            
        case 'PENDING':
            $subscription->update(['status' => 'pending']);
            break;
    }
}
```

## Idempotency

### Why Idempotency Matters

Midtrans may send the same webhook multiple times. Your handler must be idempotent to prevent:
- Duplicate subscription activations
- Multiple notification sends
- Data inconsistencies

### Implementation Strategy

```php
private function processWebhook(array $payload): void
{
    $orderId = $payload['order_id'] ?? null;
    $transactionId = $payload['transaction_id'] ?? null;
    
    // Check if already processed
    $cacheKey = "webhook_processed:{$transactionId}";
    
    if (Cache::has($cacheKey)) {
        Log::info('Webhook already processed', [
            'transaction_id' => $transactionId,
        ]);
        return;
    }
    
    // Process webhook
    DB::transaction(function () use ($payload) {
        $this->handlePaymentNotification($payload);
    });
    
    // Mark as processed (cache for 24 hours)
    Cache::put($cacheKey, true, now()->addDay());
}
```

## Error Handling

### Retry Strategy

Always return HTTP 200 to prevent Midtrans from retrying:

```php
try {
    $this->processWebhook($payload);
    return response()->json(['status' => 'ok'], 200);
} catch (\Exception $e) {
    // Log error but return 200
    Log::error('Webhook processing failed', [
        'error' => $e->getMessage(),
        'payload' => $payload,
    ]);
    
    return response()->json(['status' => 'ok'], 200);
}
```

### Failed Webhook Recovery

For failed webhooks, implement a reconciliation job:

```php
// app/Console/Commands/ReconcileSubscriptions.php
public function handle()
{
    $subscriptions = Subscription::where('provider', 'midtrans')
        ->where('status', 'active')
        ->get();
    
    foreach ($subscriptions as $subscription) {
        $midtransData = $this->midtransService->getSubscription(
            $subscription->midtrans_subscription_id
        );
        
        if ($midtransData && $midtransData['status'] !== $subscription->status) {
            $subscription->update(['status' => $midtransData['status']]);
            
            Log::info('Subscription reconciled', [
                'subscription_id' => $subscription->id,
                'old_status' => $subscription->status,
                'new_status' => $midtransData['status'],
            ]);
        }
    }
}
```

## Logging

### What to Log

**Always Log**:
- All webhook receipts (with payload)
- Signature validation failures
- Processing errors
- Status changes

**Never Log**:
- Full credit card numbers
- CVV codes
- Server keys

### Logging Implementation

```php
// Successful webhook
Log::info('Webhook received', [
    'type' => 'payment_notification',
    'order_id' => $payload['order_id'],
    'status' => $payload['transaction_status'],
    'subscription_id' => $subscriptionId,
]);

// Failed validation
Log::warning('Invalid webhook signature', [
    'order_id' => $payload['order_id'] ?? 'unknown',
    'ip' => $request->ip(),
    'timestamp' => now(),
]);

// Processing error
Log::error('Webhook processing failed', [
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
    'payload' => $payload,
]);
```

## Testing Webhooks

### Local Testing with ngrok

1. Install ngrok: `npm install -g ngrok`
2. Start your local server: `php artisan serve`
3. Expose with ngrok: `ngrok http 8000`
4. Use ngrok URL in Midtrans dashboard: `https://abc123.ngrok.io/api/webhooks/midtrans`

### Manual Webhook Testing

Send test webhooks using curl:

```bash
curl -X POST http://localhost:8000/api/webhooks/midtrans \
  -H "Content-Type: application/json" \
  -d '{
    "transaction_time": "2026-01-24 12:00:00",
    "transaction_status": "settlement",
    "transaction_id": "test_txn_123",
    "status_code": "200",
    "signature_key": "calculated_signature",
    "order_id": "sub_test_1",
    "gross_amount": "99000.00"
  }'
```

### Automated Testing

```php
// tests/Feature/MidtransWebhookTest.php
public function test_webhook_processes_payment_notification()
{
    $subscription = Subscription::factory()->create([
        'midtrans_subscription_id' => 'sub_test_123',
        'status' => 'pending',
    ]);
    
    $payload = [
        'order_id' => 'sub_test_123_1',
        'transaction_status' => 'settlement',
        'status_code' => '200',
        'gross_amount' => '99000.00',
    ];
    
    // Calculate valid signature
    $payload['signature_key'] = $this->generateSignature($payload);
    
    $response = $this->postJson('/api/webhooks/midtrans', $payload);
    
    $response->assertStatus(200);
    $subscription->refresh();
    $this->assertEquals('active', $subscription->status);
}
```

## Security Best Practices

1. **Always validate signatures**: Never process unverified webhooks
2. **Use HTTPS**: Webhook endpoint must use HTTPS in production
3. **Rate limiting**: Prevent abuse with rate limiting
4. **IP whitelisting**: Consider whitelisting Midtrans IPs
5. **Logging**: Log all webhook activity for audit trail
6. **Idempotency**: Handle duplicate webhooks gracefully
7. **Error handling**: Return 200 even on errors to prevent retries

## Troubleshooting

### Webhook Not Received

1. Check Midtrans dashboard webhook configuration
2. Verify endpoint is publicly accessible
3. Check firewall/security group settings
4. Review application logs for errors
5. Test with ngrok for local development

### Invalid Signature

1. Verify server key is correct
2. Check signature calculation logic
3. Ensure payload fields match exactly
4. Review Midtrans documentation for changes

### Duplicate Processing

1. Implement idempotency checks
2. Use database transactions
3. Cache processed webhook IDs
4. Add unique constraints where appropriate

## References

- [Midtrans Webhook Documentation](https://docs.midtrans.com/en/after-payment/http-notification)
- [Midtrans Signature Validation](https://docs.midtrans.com/en/after-payment/http-notification#verifying-notification-authenticity)
- [Midtrans Subscription Events](https://docs.midtrans.com/reference/subscription-webhook)
