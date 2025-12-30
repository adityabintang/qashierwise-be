# Webhook Setup and Testing Guide

## Overview

Webhooks are HTTP callbacks that payment providers use to notify your application about payment status changes. This guide covers webhook setup, testing, and troubleshooting for both Midtrans and Xendit providers.

## Table of Contents

1. [Understanding Webhooks](#understanding-webhooks)
2. [Webhook Security](#webhook-security)
3. [Midtrans Webhook Setup](#midtrans-webhook-setup)
4. [Xendit Webhook Setup](#xendit-webhook-setup)
5. [Testing Webhooks](#testing-webhooks)
6. [Troubleshooting](#troubleshooting)
7. [Monitoring and Logging](#monitoring-and-logging)

## Understanding Webhooks

### What are Webhooks?

Webhooks are automated messages sent from payment providers to your application when specific events occur (e.g., payment received, QR code expired).

### Why Use Webhooks?

- **Real-time updates**: Get instant notifications when payment status changes
- **Reliability**: Don't rely on polling or manual checks
- **Automation**: Automatically update orders, send confirmations, etc.
- **Efficiency**: Reduce API calls and server load

### Webhook Flow

```
Customer Pays
    ↓
Provider Processes Payment
    ↓
Provider Sends Webhook to Your Server
    ↓
Your Server Verifies Signature
    ↓
Your Server Updates Transaction Status
    ↓
Your Server Responds with 200 OK
    ↓
Provider Marks Webhook as Delivered
```

## Webhook Security

### Signature Verification

All webhooks must be verified to ensure they're from the legitimate provider and haven't been tampered with.

#### How It Works

1. Provider calculates HMAC signature of webhook payload
2. Provider sends signature in HTTP header
3. Your server recalculates signature using shared secret
4. Your server compares signatures
5. If match: process webhook; if not: reject

#### Implementation

```php
// Xendit signature verification
public function verifyWebhook(array $payload, string $signature): bool
{
    $computedSignature = hash_hmac(
        'sha256',
        json_encode($payload),
        $this->secretKey
    );
    
    return hash_equals($computedSignature, $signature);
}
```

### Best Practices

1. **Always verify signatures**: Never process unverified webhooks
2. **Use HTTPS**: Webhook URLs must use HTTPS in production
3. **Validate payload**: Check required fields exist
4. **Idempotency**: Handle duplicate webhooks gracefully
5. **Log everything**: Keep detailed logs for debugging

## Midtrans Webhook Setup

### Step 1: Configure Webhook URL

1. Log in to [Midtrans Dashboard](https://dashboard.midtrans.com)
2. Navigate to **Settings** → **Configuration**
3. Find **Payment Notification URL**
4. Enter your webhook URL:
   ```
   https://yourdomain.com/api/webhooks/midtrans
   ```
5. Click **Save**

### Step 2: Verify Configuration

Test the webhook URL:

```bash
curl -X POST https://yourdomain.com/api/webhooks/midtrans \
  -H "Content-Type: application/json" \
  -d '{
    "transaction_status": "settlement",
    "order_id": "test_order_123",
    "gross_amount": "50000.00",
    "signature_key": "test_signature"
  }'
```

### Step 3: Handle Webhook Events

Midtrans sends webhooks for these events:

| Event | transaction_status | Description |
|-------|-------------------|-------------|
| Payment Success | `settlement` | Payment completed |
| Payment Pending | `pending` | Awaiting payment |
| Payment Expired | `expire` | Payment expired |
| Payment Cancelled | `cancel` | Payment cancelled |
| Payment Denied | `deny` | Payment denied |

### Webhook Payload Example

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

## Xendit Webhook Setup

### Step 1: Configure Webhook URL

1. Log in to [Xendit Dashboard](https://dashboard.xendit.co)
2. Navigate to **Settings** → **Developers** → **Webhooks**
3. Click **Add Webhook URL**
4. Enter your webhook URL:
   ```
   https://yourdomain.com/api/webhooks/xendit
   ```
5. Select events to receive:
   - ✅ `qr_code.paid`
   - ✅ `qr_code.expired`
6. Click **Save**

### Step 2: Get Verification Token

1. In the same **Webhooks** section
2. Copy your **Webhook Verification Token**
3. This is used as the `secret_key` in your application

### Step 3: Verify Configuration

Test the webhook URL:

```bash
curl -X POST https://yourdomain.com/api/webhooks/xendit \
  -H "Content-Type: application/json" \
  -H "x-callback-token: your_webhook_verification_token" \
  -d '{
    "id": "qr_test_123",
    "external_id": "ORDER-2025-001",
    "status": "PAID",
    "amount": 50000,
    "paid_at": "2025-01-01T12:00:00Z"
  }'
```

### Step 4: Handle Webhook Events

Xendit sends webhooks for these events:

| Event | status | Description |
|-------|--------|-------------|
| QR Code Created | `ACTIVE` | QR code generated, awaiting payment |
| Payment Success | `PAID` | Payment completed |
| QR Code Expired | `EXPIRED` | QR code expired without payment |
| Payment Failed | `FAILED` | Payment failed |

### Webhook Payload Example

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

## Testing Webhooks

### Local Development Testing

#### Option 1: Using ngrok

Ngrok creates a public URL for your local server:

```bash
# Install ngrok
# Download from https://ngrok.com

# Start your Laravel server
php artisan serve

# In another terminal, start ngrok
ngrok http 8000

# Use the ngrok URL in provider dashboard
# Example: https://abc123.ngrok.io/api/webhooks/xendit
```

#### Option 2: Using Laravel Valet (macOS)

```bash
# Install Valet
composer global require laravel/valet
valet install

# Park your project directory
cd ~/Sites
valet park

# Your site is now accessible at
# https://your-project.test/api/webhooks/xendit
```

### Manual Webhook Testing

#### Test Xendit Webhook

```bash
# Test successful payment
curl -X POST http://localhost:8000/api/webhooks/xendit \
  -H "Content-Type: application/json" \
  -H "x-callback-token: your_webhook_verification_token" \
  -d '{
    "id": "qr_test_123",
    "external_id": "ORDER-2025-001",
    "status": "PAID",
    "amount": 50000,
    "paid_at": "2025-01-01T12:00:00Z"
  }'

# Expected response
{"success":true}

# Test expired QR code
curl -X POST http://localhost:8000/api/webhooks/xendit \
  -H "Content-Type: application/json" \
  -H "x-callback-token: your_webhook_verification_token" \
  -d '{
    "id": "qr_test_123",
    "external_id": "ORDER-2025-001",
    "status": "EXPIRED",
    "amount": 50000
  }'
```

#### Test Midtrans Webhook

```bash
# Test successful payment
curl -X POST http://localhost:8000/api/webhooks/midtrans \
  -H "Content-Type: application/json" \
  -d '{
    "transaction_status": "settlement",
    "order_id": "ORDER-2025-001",
    "gross_amount": "50000.00",
    "transaction_id": "mid_123456",
    "signature_key": "calculated_signature"
  }'
```

### Automated Testing

#### PHPUnit Test Example

```php
public function test_xendit_webhook_processes_payment()
{
    // Create test transaction
    $transaction = QrisTransaction::factory()->create([
        'external_id' => 'ORDER-2025-001',
        'status' => 'pending',
        'provider' => 'xendit'
    ]);

    // Prepare webhook payload
    $payload = [
        'id' => 'qr_123',
        'external_id' => 'ORDER-2025-001',
        'status' => 'PAID',
        'amount' => 50000,
        'paid_at' => now()->toIso8601String()
    ];

    // Calculate signature
    $signature = hash_hmac('sha256', json_encode($payload), 'test_secret');

    // Send webhook request
    $response = $this->postJson('/api/webhooks/xendit', $payload, [
        'x-callback-token' => $signature
    ]);

    // Assert response
    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    // Assert transaction updated
    $transaction->refresh();
    $this->assertEquals('success', $transaction->status);
    $this->assertNotNull($transaction->paid_at);
}
```

### Provider Test Tools

#### Xendit Test Payment Simulator

1. Log in to Xendit Dashboard
2. Navigate to **Developers** → **Test Payment Simulator**
3. Enter QR code ID
4. Click **Simulate Payment**
5. Webhook will be sent to your configured URL

#### Midtrans Simulator

1. Log in to Midtrans Dashboard
2. Navigate to **Transactions** → **Simulator**
3. Create test transaction
4. Use test payment methods
5. Webhook will be sent automatically

## Troubleshooting

### Common Issues

#### 1. Webhook Not Received

**Symptoms**: No webhook notifications arriving

**Checklist**:
- ✅ Webhook URL is correct in provider dashboard
- ✅ URL is accessible from internet (not localhost)
- ✅ Server is running and accepting requests
- ✅ No firewall blocking incoming requests
- ✅ HTTPS is configured (for production)

**Solution**:
```bash
# Test if URL is accessible
curl -I https://yourdomain.com/api/webhooks/xendit

# Check Laravel routes
php artisan route:list | grep webhook

# Check server logs
tail -f storage/logs/laravel.log
```

#### 2. Signature Verification Failed

**Symptoms**: Error "Invalid webhook signature"

**Checklist**:
- ✅ Webhook verification token is correct
- ✅ Token matches provider dashboard
- ✅ Credentials are properly decrypted
- ✅ Payload is not modified before verification

**Solution**:
```php
// Debug signature calculation
Log::info('Webhook received', [
    'payload' => $payload,
    'received_signature' => $signature,
    'calculated_signature' => hash_hmac('sha256', json_encode($payload), $secret)
]);
```

#### 3. Transaction Not Updated

**Symptoms**: Webhook received but transaction status unchanged

**Checklist**:
- ✅ Transaction exists in database
- ✅ external_id matches
- ✅ No database errors
- ✅ Status mapping is correct

**Solution**:
```bash
# Check database
php artisan tinker
>>> QrisTransaction::where('external_id', 'ORDER-2025-001')->first()

# Check logs
tail -f storage/logs/laravel.log | grep webhook
```

#### 4. Duplicate Webhooks

**Symptoms**: Same webhook received multiple times

**Explanation**: Providers may retry webhooks if they don't receive 200 OK response

**Solution**: Implement idempotency
```php
public function handleWebhook(Request $request)
{
    $externalId = $request->input('external_id');
    
    // Check if already processed
    $transaction = QrisTransaction::where('external_id', $externalId)->first();
    
    if ($transaction->status === 'success') {
        // Already processed, return success
        return response()->json(['success' => true]);
    }
    
    // Process webhook...
}
```

### Debugging Tips

#### Enable Debug Logging

```php
// In .env
LOG_LEVEL=debug

// In webhook controller
Log::debug('Webhook received', [
    'provider' => 'xendit',
    'payload' => $request->all(),
    'headers' => $request->headers->all()
]);
```

#### Check Webhook Delivery

Most providers show webhook delivery status in their dashboard:

**Xendit**:
- Dashboard → Developers → Webhooks → Delivery Logs

**Midtrans**:
- Dashboard → Transactions → Select transaction → Notification History

#### Test Signature Calculation

```php
php artisan tinker

$payload = ['id' => 'qr_123', 'status' => 'PAID'];
$secret = 'your_webhook_verification_token';
$signature = hash_hmac('sha256', json_encode($payload), $secret);
echo $signature;
```

## Monitoring and Logging

### What to Log

1. **Webhook Receipt**: Log when webhook is received
2. **Signature Verification**: Log verification result
3. **Payload**: Log full payload (sanitize sensitive data)
4. **Processing Result**: Log success or failure
5. **Errors**: Log any errors with stack trace

### Example Logging

```php
public function handleWebhook(Request $request)
{
    Log::info('Webhook received', [
        'provider' => 'xendit',
        'external_id' => $request->input('external_id'),
        'status' => $request->input('status')
    ]);
    
    try {
        // Verify signature
        if (!$this->verifySignature($request)) {
            Log::warning('Webhook signature verification failed', [
                'provider' => 'xendit',
                'external_id' => $request->input('external_id')
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        
        // Process webhook
        $this->processWebhook($request);
        
        Log::info('Webhook processed successfully', [
            'provider' => 'xendit',
            'external_id' => $request->input('external_id')
        ]);
        
        return response()->json(['success' => true]);
        
    } catch (\Exception $e) {
        Log::error('Webhook processing failed', [
            'provider' => 'xendit',
            'external_id' => $request->input('external_id'),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json(['error' => 'Processing failed'], 500);
    }
}
```

### Monitoring Webhook Health

Create a monitoring dashboard or alerts for:

- Webhook success rate
- Average processing time
- Failed signature verifications
- Unprocessed webhooks
- Duplicate webhooks

### Log Analysis

```bash
# Count webhooks by status
grep "Webhook received" storage/logs/laravel.log | grep -o '"status":"[^"]*"' | sort | uniq -c

# Find failed webhooks
grep "Webhook processing failed" storage/logs/laravel.log

# Check signature failures
grep "signature verification failed" storage/logs/laravel.log
```

## Best Practices

### Development

1. **Use test mode**: Always test with sandbox/test credentials first
2. **Test all scenarios**: Success, failure, expiration, duplicates
3. **Handle errors gracefully**: Return appropriate HTTP status codes
4. **Log everything**: Comprehensive logging helps debugging

### Production

1. **Use HTTPS**: Never use HTTP for webhooks in production
2. **Verify signatures**: Always verify webhook signatures
3. **Respond quickly**: Process webhooks asynchronously if needed
4. **Monitor health**: Set up alerts for webhook failures
5. **Handle retries**: Implement idempotency for duplicate webhooks

### Security

1. **Validate input**: Check all required fields exist
2. **Sanitize data**: Clean data before processing
3. **Rate limiting**: Implement rate limiting on webhook endpoints
4. **IP whitelisting**: Consider whitelisting provider IPs (if available)
5. **Audit logging**: Log all webhook activity for security audits

## Webhook Response Codes

Return appropriate HTTP status codes:

| Code | Meaning | When to Use |
|------|---------|-------------|
| 200 | Success | Webhook processed successfully |
| 400 | Bad Request | Invalid payload format |
| 401 | Unauthorized | Signature verification failed |
| 404 | Not Found | Transaction not found |
| 500 | Server Error | Internal processing error |

## Additional Resources

- [Xendit Webhook Documentation](https://developers.xendit.co/api-reference/#webhooks)
- [Midtrans Notification Documentation](https://docs.midtrans.com/en/after-payment/http-notification)
- [Laravel Queue Documentation](https://laravel.com/docs/queues) (for async processing)
- [ngrok Documentation](https://ngrok.com/docs) (for local testing)

## Support

If you encounter issues not covered in this guide:

1. Check application logs: `storage/logs/laravel.log`
2. Check provider dashboard for webhook delivery status
3. Review provider documentation
4. Contact provider support
5. Contact your system administrator
