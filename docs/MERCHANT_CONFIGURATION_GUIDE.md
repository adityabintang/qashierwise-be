# Merchant Configuration Guide

## Quick Start Guide for Merchants

This guide helps merchants configure and use the QRIS payment system with multiple providers (Midtrans and Xendit).

## Table of Contents

1. [Overview](#overview)
2. [Choosing a Payment Provider](#choosing-a-payment-provider)
3. [Setting Up Midtrans](#setting-up-midtrans)
4. [Setting Up Xendit](#setting-up-xendit)
5. [Managing Multiple Providers](#managing-multiple-providers)
6. [Generating QRIS Codes](#generating-qris-codes)
7. [Monitoring Payments](#monitoring-payments)
8. [FAQ](#faq)

## Overview

The QRIS payment system supports multiple payment providers, giving you flexibility in choosing the service that best fits your business needs. You can:

- Configure multiple providers
- Switch between providers anytime
- Keep existing transactions when switching
- Use different providers for different purposes

### Supported Providers

| Provider | Features | Best For |
|----------|----------|----------|
| **Midtrans** | Established, reliable, comprehensive dashboard | Businesses needing full payment gateway features |
| **Xendit** | Modern API, competitive rates, fast settlement | Startups and tech-savvy businesses |

## Choosing a Payment Provider

### Midtrans

**Pros**:
- Well-established in Indonesia
- Comprehensive merchant dashboard
- Multiple payment methods beyond QRIS
- Strong customer support

**Cons**:
- Higher transaction fees
- More complex setup process

**Best for**: Established businesses, those needing multiple payment methods

### Xendit

**Pros**:
- Modern, developer-friendly API
- Competitive transaction fees
- Fast settlement times
- Easy integration

**Cons**:
- Newer in the market
- Fewer payment methods

**Best for**: Startups, tech companies, businesses prioritizing API integration

## Setting Up Midtrans

### Step 1: Create Midtrans Account

1. Visit [Midtrans](https://midtrans.com)
2. Click **Sign Up** and complete registration
3. Verify your business information
4. Complete KYC (Know Your Customer) process

### Step 2: Get API Credentials

1. Log in to [Midtrans Dashboard](https://dashboard.midtrans.com)
2. Go to **Settings** → **Access Keys**
3. Copy the following:
   - **Server Key**: Used for server-side operations
   - **Client Key**: Used for client-side operations

### Step 3: Configure in Application

#### Via Dashboard:

1. Log in to your application
2. Navigate to **Settings** → **Payment Providers**
3. Click **Add Provider** → Select **Midtrans**
4. Enter your credentials:
   - Server Key: `your_midtrans_server_key`
   - Client Key: `your_midtrans_client_key`
5. Click **Save**
6. Toggle **Active** to start using Midtrans

#### Via API:

```bash
curl -X POST https://yourdomain.com/api/provider-credentials \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "midtrans",
    "api_key": "your_midtrans_server_key",
    "secret_key": "your_midtrans_client_key"
  }'
```

### Step 4: Configure Webhook

1. In Midtrans Dashboard, go to **Settings** → **Configuration**
2. Set **Payment Notification URL** to:
   ```
   https://yourdomain.com/api/webhooks/midtrans
   ```
3. Click **Save**

## Setting Up Xendit

### Step 1: Create Xendit Account

1. Visit [Xendit](https://xendit.co)
2. Click **Sign Up** and complete registration
3. Verify your email and phone number
4. Complete business verification

### Step 2: Get API Credentials

1. Log in to [Xendit Dashboard](https://dashboard.xendit.co)
2. Go to **Settings** → **Developers** → **API Keys**
3. Copy your **Secret API Key**
4. Go to **Settings** → **Developers** → **Webhooks**
5. Copy your **Webhook Verification Token**

### Step 3: Configure in Application

#### Via Dashboard:

1. Log in to your application
2. Navigate to **Settings** → **Payment Providers**
3. Click **Add Provider** → Select **Xendit**
4. Enter your credentials:
   - API Key: `your_xendit_secret_api_key`
   - Secret Key: `your_xendit_webhook_verification_token`
5. Click **Save**
6. Toggle **Active** to start using Xendit

#### Via API:

```bash
curl -X POST https://yourdomain.com/api/provider-credentials \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "xendit",
    "api_key": "xnd_development_your_api_key",
    "secret_key": "your_webhook_verification_token"
  }'
```

### Step 4: Configure Webhook

1. In Xendit Dashboard, go to **Settings** → **Developers** → **Webhooks**
2. Click **Add Webhook URL**
3. Enter:
   ```
   https://yourdomain.com/api/webhooks/xendit
   ```
4. Select events: `qr_code.paid`, `qr_code.expired`
5. Click **Save**

## Managing Multiple Providers

### Viewing Configured Providers

#### Via Dashboard:

1. Navigate to **Settings** → **Payment Providers**
2. View all configured providers with their status

#### Via API:

```bash
curl -X GET https://yourdomain.com/api/provider-credentials \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Switching Providers

You can switch between providers anytime without losing existing transactions.

#### Via Dashboard:

1. Go to **Settings** → **Payment Providers**
2. Find the provider you want to use
3. Toggle it to **Active**
4. The previously active provider will be automatically deactivated

#### Via API:

```bash
curl -X POST https://yourdomain.com/api/provider-credentials/{id}/activate \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Important Notes:

- Only one provider can be active at a time
- Existing transactions remain with their original provider
- New transactions use the currently active provider
- You can switch back anytime

## Generating QRIS Codes

### Via Dashboard:

1. Navigate to **Payments** → **Generate QRIS**
2. Enter transaction details:
   - Amount (in IDR)
   - Order ID (optional, auto-generated if not provided)
3. Click **Generate**
4. Display the QR code to your customer
5. Customer scans and pays using any QRIS-compatible app

### Via API:

```bash
curl -X POST https://yourdomain.com/api/qris/generate \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 50000,
    "order_id": "ORDER-2025-001"
  }'
```

**Response**:
```json
{
  "success": true,
  "data": {
    "qr_string": "00020101021126...",
    "reference_id": "qr_123456789",
    "external_id": "ORDER-2025-001",
    "amount": 50000,
    "provider": "xendit",
    "status": "pending"
  }
}
```

### Displaying QR Code

The `qr_string` can be displayed as:

1. **QR Code Image**: Use a QR code library to generate an image
2. **Payment Link**: Some providers support payment links
3. **Embedded**: Display directly in your app/website

## Monitoring Payments

### Via Dashboard:

1. Navigate to **Payments** → **Transactions**
2. View all transactions with their status:
   - **Pending**: Awaiting payment
   - **Success**: Payment received
   - **Expired**: QR code expired
   - **Failed**: Payment failed

### Via API:

```bash
# Get specific transaction
curl -X GET https://yourdomain.com/api/qris/transactions/ORDER-2025-001 \
  -H "Authorization: Bearer YOUR_TOKEN"

# List all transactions
curl -X GET https://yourdomain.com/api/qris/transactions \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Payment Notifications

You'll receive automatic notifications when:

- Payment is received (status changes to `success`)
- QR code expires (status changes to `expired`)
- Payment fails (status changes to `failed`)

Notifications are sent via:
- Webhook callbacks (for system integration)
- Email (if configured)
- Dashboard notifications

## FAQ

### General Questions

**Q: Can I use multiple providers simultaneously?**

A: Only one provider can be active at a time for new transactions. However, you can configure multiple providers and switch between them anytime.

**Q: What happens to existing transactions when I switch providers?**

A: Existing transactions remain with their original provider and continue to work normally. Only new transactions use the newly activated provider.

**Q: How long does a QRIS code remain valid?**

A: Typically 15-30 minutes, depending on the provider. Check with your provider for exact expiration times.

**Q: Can customers pay with any e-wallet?**

A: Yes! QRIS is a national standard, so customers can pay using any QRIS-compatible app (GoPay, OVO, Dana, ShopeePay, LinkAja, etc.).

### Technical Questions

**Q: Are my API credentials secure?**

A: Yes. All credentials are encrypted before storage using industry-standard encryption (AES-256). They're only decrypted when needed for API calls.

**Q: What if my webhook isn't working?**

A: Check the following:
1. Webhook URL is correct and accessible via HTTPS
2. Webhook verification token matches your provider dashboard
3. Your server can receive POST requests
4. Check application logs for errors

**Q: How do I test without real payments?**

A: Both Midtrans and Xendit provide test/sandbox environments:
- Use test API keys (usually prefixed with `sandbox_` or `xnd_development_`)
- Use provider's test payment simulator
- Test transactions won't process real money

**Q: Can I get transaction reports?**

A: Yes, through:
1. Provider dashboard (Midtrans/Xendit)
2. Application dashboard (if implemented)
3. API endpoints for transaction history

### Troubleshooting

**Q: Error: "Invalid credentials"**

A: 
- Verify you copied the correct API keys
- Ensure you're using the right environment (test/production)
- Check if keys have been revoked in provider dashboard

**Q: Error: "No active provider configured"**

A:
- Ensure you've saved provider credentials
- Activate the provider (toggle to Active)
- Check that at least one provider is marked as active

**Q: QR code not generating**

A:
- Check your internet connection
- Verify provider API status
- Check application logs for detailed errors
- Ensure your account is verified with the provider

**Q: Webhook not receiving notifications**

A:
- Verify webhook URL is correct in provider dashboard
- Ensure your server accepts POST requests
- Check webhook verification token is correct
- Test webhook manually using curl

## Best Practices

### Security

1. **Never share API keys**: Keep credentials confidential
2. **Use HTTPS**: Always use secure connections
3. **Rotate keys regularly**: Change API keys periodically
4. **Monitor access logs**: Check for suspicious activity
5. **Separate environments**: Use different keys for test and production

### Operations

1. **Test before going live**: Always test in sandbox/test mode first
2. **Monitor transactions**: Regularly check transaction status
3. **Set up notifications**: Configure email/webhook notifications
4. **Keep records**: Maintain transaction records for accounting
5. **Have backup provider**: Configure multiple providers for redundancy

### Customer Experience

1. **Clear instructions**: Provide clear payment instructions
2. **Show QR code clearly**: Ensure QR code is large enough to scan
3. **Display amount**: Always show the payment amount
4. **Set expectations**: Inform customers about expiration time
5. **Confirm payment**: Show confirmation when payment is received

## Getting Help

### Provider Support

**Midtrans**:
- Documentation: [docs.midtrans.com](https://docs.midtrans.com)
- Support: [support.midtrans.com](https://support.midtrans.com)
- Email: support@midtrans.com

**Xendit**:
- Documentation: [developers.xendit.co](https://developers.xendit.co)
- Support: [help.xendit.co](https://help.xendit.co)
- Email: support@xendit.co

### Application Support

- Check logs: `storage/logs/laravel.log`
- Review documentation: `docs/` folder
- Contact your system administrator

## Next Steps

1. ✅ Choose your payment provider
2. ✅ Create provider account
3. ✅ Get API credentials
4. ✅ Configure in application
5. ✅ Set up webhooks
6. ✅ Test in sandbox mode
7. ✅ Go live with real transactions

Congratulations! You're ready to accept QRIS payments. 🎉
