# Xendit QRIS Integration Guide

## Overview

This guide explains how to integrate Xendit as a QRIS payment provider in your application. The system supports multiple payment providers (Midtrans and Xendit), allowing merchants to choose their preferred provider for generating dynamic QRIS codes.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Getting Xendit API Credentials](#getting-xendit-api-credentials)
3. [Configuration](#configuration)
4. [Provider Selection](#provider-selection)
5. [Webhook Setup](#webhook-setup)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)
8. [API Reference](#api-reference)

## Prerequisites

Before integrating Xendit, ensure you have:

- A Xendit business account
- Access to Xendit Dashboard
- Laravel application with the multi-provider QRIS system installed
- HTTPS-enabled domain for webhook callbacks

## Getting Xendit API Credentials

### Step 1: Create a Xendit Account

1. Visit [Xendit Dashboard](https://dashboard.xendit.co)
2. Sign up for a business account
3. Complete the verification process

### Step 2: Obtain API Keys

1. Log in to your Xendit Dashboard
2. Navigate to **Settings** → **Developers** → **API Keys**
3. You'll find two types of keys:
   - **Public API Key**: Used for client-side operations (not needed for QRIS)
   - **Secret API Key**: Used for server-side operations (required)

4. Copy your **Secret API Key** - this will be used as both `api_key` and for authentication

### Step 3: Generate Webhook Verification Token

1. In Xendit Dashboard, go to **Settings** → **Developers** → **Webhooks**
2. Copy your **Webhook Verification Token** - this will be used as `secret_key` for signature verification

## Configuration

### Environment Variables

Add the following to your `.env` file (optional, for default configuration):

```env
# Xendit Configuration
XENDIT_API_KEY=your_xendit_secret_api_key
XENDIT_SECRET_KEY=your_xendit_webhook_verification_token
XENDIT_BASE_URL=https://api.xendit.co
```

### Database Configuration

The system automatically creates the necessary database tables during migration:

- `payment_provider_credentials`: Stores encrypted provider credentials
- `qris_transactions`: Enhanced with `provider`, `reference_id`, and `paid_at` columns

Run migrations if not already done:

```bash
php artisan migrate
```

## Provider Selection

### Via API

#### 1. Store Xendit Credentials

**Endpoint**: `POST /api/provider-credentials`

**Request Body**:
```json
{
  "provider": "xendit",
  "api_key": "xnd_development_your_api_key",
  "secret_key": "your_webhook_verification_token"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Provider credentials saved successfully",
  "data": {
    "id": 1,
    "provider": "xendit",
    "is_active": false
  }
}
```

#### 2. Activate Xendit Provider

**Endpoint**: `POST /api/provider-credentials/{id}/activate`

**Response**:
```json
{
  "success": true,
  "message": "Provider activated successfully"
}
```

#### 3. List Configured Providers

**Endpoint**: `GET /api/provider-credentials`

**Response**:
```json
{
  "data": [
    {
      "id": 1,
      "provider": "midtrans",
      "is_active": false,
      "created_at": "2025-01-01T00:00:00Z"
    },
    {
      "id": 2,
      "provider": "xendit",
      "is_active": true,
      "created_at": "2025-01-02T00:00:00Z"
    }
  ]
}
```

### Via Dashboard UI

1. Log in to your application dashboard
2. Navigate to **Settings** → **Payment Providers**
3. Click **Add Provider** → Select **Xendit**
4. Enter your Xendit API credentials:
   - API Key: Your Xendit Secret API Key
   - Secret Key: Your Webhook Verification Token
5. Click **Save**
6. Toggle the provider to **Active** to start using it

## Webhook Setup

Webhooks allow Xendit to notify your application when payment status changes.

### Step 1: Configure Webhook URL in Xendit Dashboard

1. Log in to [Xendit Dashboard](https://dashboard.xendit.co)
2. Navigate to **Settings** → **Developers** → **Webhooks**
3. Click **Add Webhook URL**
4. Enter your webhook URL:
   ```
   https://yourdomain.com/api/webhooks/xendit
   ```
5. Select the following events:
   - `qr_code.paid`
   - `qr_code.expired`
6. Click **Save**

### Step 2: Verify Webhook Configuration

The webhook endpoint is automatically configured in your Laravel application at:

```
POST /api/webhooks/xendit
```

### Step 3: Test Webhook

You can test the webhook using Xendit's webhook testing tool or manually:

```bash
curl -X POST https://yourdomain.com/api/webhooks/xendit \
  -H "Content-Type: application/json" \
  -H "x-callback-token: your_webhook_verification_token" \
  -d '{
    "id": "qr_123456",
    "external_id": "order_123",
    "status": "PAID",
    "amount": 50000,
    "paid_at": "2025-01-01T12:00:00Z"
  }'
```

### Webhook Payload Structure

Xendit sends the following payload when a QR code is paid:

```json
{
  "id": "qr_123456789",
  "external_id": "order_123",
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

### Webhook Status Mapping

| Xendit Status | System Status | Description |
|--------------|---------------|-------------|
| `ACTIVE` | `pending` | QR code created, awaiting payment |
| `PAID` | `success` | Payment received successfully |
| `EXPIRED` | `expired` | QR code expired without payment |
| `FAILED` | `failed` | Payment failed |

## Testing

### Test Mode

Xendit provides a test mode for development:

1. Use test API keys (prefix: `xnd_development_`)
2. Test QR codes won't process real payments
3. Use Xendit's test payment simulator

### Generate Test QRIS

**Endpoint**: `POST /api/qris/generate`

**Request**:
```json
{
  "amount": 50000,
  "order_id": "test_order_123"
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "qr_string": "00020101021126...",
    "reference_id": "qr_123456789",
    "external_id": "test_order_123",
    "amount": 50000,
    "provider": "xendit",
    "status": "pending"
  }
}
```

### Simulate Payment

Use Xendit's test payment simulator:

1. Go to Xendit Dashboard → **Developers** → **Test Payment Simulator**
2. Enter the QR code ID
3. Click **Simulate Payment**
4. Check your webhook endpoint receives the notification

### Manual Webhook Testing

```bash
# Test successful payment
curl -X POST http://localhost:8000/api/webhooks/xendit \
  -H "Content-Type: application/json" \
  -H "x-callback-token: your_test_webhook_token" \
  -d '{
    "id": "qr_test_123",
    "external_id": "test_order_123",
    "status": "PAID",
    "amount": 50000,
    "paid_at": "2025-01-01T12:00:00Z"
  }'

# Test expired QR code
curl -X POST http://localhost:8000/api/webhooks/xendit \
  -H "Content-Type: application/json" \
  -H "x-callback-token: your_test_webhook_token" \
  -d '{
    "id": "qr_test_123",
    "external_id": "test_order_123",
    "status": "EXPIRED",
    "amount": 50000
  }'
```

## Troubleshooting

### Common Issues

#### 1. Invalid Credentials Error

**Error**: `Invalid Xendit credentials`

**Solution**:
- Verify your API key is correct
- Ensure you're using the Secret API Key, not the Public Key
- Check if the API key is for the correct environment (test/production)

#### 2. Webhook Signature Verification Failed

**Error**: `Invalid webhook signature`

**Solution**:
- Verify the webhook verification token matches your Xendit dashboard
- Ensure the `x-callback-token` header is being sent
- Check that credentials are properly decrypted

#### 3. No Active Provider

**Error**: `No payment provider configured for this user`

**Solution**:
- Ensure you've saved Xendit credentials
- Activate the Xendit provider via API or dashboard
- Check that `is_active` is set to `true` in the database

#### 4. Provider Unavailable

**Error**: `Payment provider is temporarily unavailable`

**Solution**:
- Check your internet connection
- Verify Xendit API status at [status.xendit.co](https://status.xendit.co)
- Check Laravel logs for detailed error messages
- Ensure your API key has not been revoked

### Debugging

Enable detailed logging in your `.env`:

```env
LOG_LEVEL=debug
```

Check logs:

```bash
tail -f storage/logs/laravel.log
```

### Testing Webhook Signature Verification

```php
// In tinker or test script
php artisan tinker

$payload = ['id' => 'qr_123', 'status' => 'PAID'];
$secret = 'your_webhook_verification_token';
$signature = hash_hmac('sha256', json_encode($payload), $secret);
echo $signature;
```

## API Reference

### Generate QRIS Code

**Endpoint**: `POST /api/qris/generate`

**Headers**:
```
Authorization: Bearer {your_token}
Content-Type: application/json
```

**Request Body**:
```json
{
  "amount": 50000,
  "order_id": "order_123",
  "callback_url": "https://yourdomain.com/api/webhooks/xendit"
}
```

**Response** (Success - 200):
```json
{
  "success": true,
  "data": {
    "qr_string": "00020101021126...",
    "reference_id": "qr_123456789",
    "external_id": "order_123",
    "amount": 50000,
    "provider": "xendit",
    "status": "pending"
  }
}
```

**Response** (Error - 400):
```json
{
  "error": "Invalid Xendit credentials",
  "code": "INVALID_CREDENTIALS"
}
```

### Check Transaction Status

**Endpoint**: `GET /api/qris/transactions/{external_id}`

**Response**:
```json
{
  "success": true,
  "data": {
    "external_id": "order_123",
    "status": "success",
    "amount": 50000,
    "provider": "xendit",
    "reference_id": "qr_123456789",
    "paid_at": "2025-01-01T12:00:00Z"
  }
}
```

### Webhook Endpoint

**Endpoint**: `POST /api/webhooks/xendit`

**Headers**:
```
x-callback-token: {your_webhook_verification_token}
Content-Type: application/json
```

**Request Body**: See [Webhook Payload Structure](#webhook-payload-structure)

**Response** (Success - 200):
```json
{
  "success": true
}
```

**Response** (Error - 401):
```json
{
  "error": "Invalid webhook signature"
}
```

## Security Best Practices

1. **Never commit credentials**: Keep API keys in `.env` file, never in version control
2. **Use HTTPS**: Always use HTTPS for webhook URLs
3. **Verify webhooks**: Always verify webhook signatures before processing
4. **Rotate keys**: Regularly rotate API keys and webhook tokens
5. **Monitor logs**: Regularly check credential access logs for suspicious activity
6. **Environment separation**: Use different API keys for test and production

## Migration from Midtrans

If you're currently using Midtrans and want to switch to Xendit:

1. Configure Xendit credentials (see [Configuration](#configuration))
2. Test Xendit integration in development environment
3. Activate Xendit provider
4. Existing Midtrans transactions will continue to work
5. New transactions will use Xendit
6. You can switch back to Midtrans anytime by activating it

## Support

For Xendit-specific issues:
- [Xendit Documentation](https://developers.xendit.co)
- [Xendit Support](https://help.xendit.co)

For application issues:
- Check application logs: `storage/logs/laravel.log`
- Review error handling documentation: `docs/ERROR_HANDLING.md`

## Additional Resources

- [Xendit QRIS API Documentation](https://developers.xendit.co/api-reference/#qr-codes)
- [Xendit Webhook Documentation](https://developers.xendit.co/api-reference/#webhooks)
- [Multi-Provider Architecture](docs/MULTI_PROVIDER_ARCHITECTURE.md)
