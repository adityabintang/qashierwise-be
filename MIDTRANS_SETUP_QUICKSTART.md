# Midtrans Setup Quick Start

This is a quick start guide to get your Midtrans accounts set up for the subscription migration. For detailed instructions, see `docs/MIDTRANS_ACCOUNT_SETUP_GUIDE.md`.

## 🚀 Quick Setup (5 Minutes)

### Step 1: Create Sandbox Account (2 min)

1. Go to: https://dashboard.sandbox.midtrans.com/register
2. Fill in:
   - Business Name: Your project name
   - Email: Your email
   - Phone: Your phone number
   - Password: Create a strong password
3. Verify your email
4. Login to dashboard

### Step 2: Get API Keys (1 min)

1. In dashboard, go to: **Settings** → **Access Keys**
2. Copy both keys:
   - **Server Key** (starts with `SB-Mid-server-`)
   - **Client Key** (starts with `SB-Mid-client-`)

### Step 3: Update .env File (1 min)

Add to your `.env` file:

```env
# Midtrans Sandbox Configuration
MIDTRANS_SERVER_KEY=SB-Mid-server-YOUR_KEY_HERE
MIDTRANS_CLIENT_KEY=SB-Mid-client-YOUR_KEY_HERE
MIDTRANS_IS_PRODUCTION=false

# Subscription URLs
MIDTRANS_SUBSCRIPTION_SUCCESS_URL=${APP_URL}/subscription/success
MIDTRANS_SUBSCRIPTION_CANCEL_URL=${APP_URL}/subscription/cancel
MIDTRANS_SUBSCRIPTION_ERROR_URL=${APP_URL}/subscription/error
```

### Step 4: Enable Subscription (1 min)

1. In Midtrans dashboard: **Settings** → **Subscription**
2. Toggle **Enable Subscription** to ON
3. Go to **Settings** → **Payment Configuration**
4. Enable **Credit Card** (required for subscriptions)
5. Save settings

### Step 5: Setup Webhooks (Local Testing)

For local development, you need to expose your local server:

```bash
# Install ngrok if you haven't: https://ngrok.com/download
ngrok http 8000
```

Copy the HTTPS URL (e.g., `https://abc123.ngrok.io`)

In Midtrans dashboard:
1. Go to **Settings** → **Configuration**
2. Set all three webhook URLs to: `https://abc123.ngrok.io/api/webhooks/midtrans`
   - Payment Notification URL
   - Recurring Notification URL
   - Pay Account Notification URL
3. Save

### Step 6: Verify Configuration

Run the configuration checker:

```bash
php check_midtrans_config.php
```

All checks should pass ✓

---

## ✅ You're Ready!

You can now:
- Create test subscriptions
- Test payment flows
- Receive webhooks

### Test Cards

Use these test cards in sandbox:

| Card Number | Result |
|-------------|--------|
| 4811 1111 1111 1114 | Success |
| 4911 1111 1111 1113 | Failure |

CVV: `123` | Expiry: Any future date

---

## 📋 Production Setup (Later)

When you're ready for production:

1. **Create Production Account**: https://dashboard.midtrans.com/register
2. **Complete KYC**: Submit business documents (takes 1-3 days)
3. **Get Production Keys**: Copy from Settings → Access Keys
4. **Update .env**: Use production keys and set `MIDTRANS_IS_PRODUCTION=true`
5. **Setup Production Webhooks**: Use your production domain (must be HTTPS)

---

## 📚 Documentation

- **Detailed Setup Guide**: `docs/MIDTRANS_ACCOUNT_SETUP_GUIDE.md`
- **Setup Checklist**: `docs/MIDTRANS_SETUP_CHECKLIST.md`
- **Configuration Guide**: `docs/MIDTRANS_CONFIGURATION.md`
- **Webhook Handling**: `docs/MIDTRANS_WEBHOOK_HANDLING.md`
- **Testing Guide**: `docs/MIDTRANS_TESTING.md`

---

## 🆘 Troubleshooting

### Webhook Not Received?
- Check ngrok is running
- Verify webhook URL in Midtrans dashboard
- Check Laravel logs: `tail -f storage/logs/laravel.log`

### Signature Validation Failed?
- Verify Server Key is correct in .env
- Run: `php artisan config:clear`

### Can't Create Subscription?
- Ensure subscription feature is enabled
- Check API credentials
- Review Laravel logs for errors

---

## 🎯 Next Steps

After setup:

1. **Test Subscription Creation**
   - Visit your pricing page
   - Click "Subscribe"
   - Complete payment with test card

2. **Verify Webhook Processing**
   - Check logs for webhook receipt
   - Verify subscription status updates

3. **Run Tests**
   - Unit tests: `php artisan test --filter=Midtrans`
   - Integration tests: `php artisan test --filter=Subscription`

---

**Need Help?**
- Midtrans Docs: https://docs.midtrans.com
- Midtrans Support: support@midtrans.com
- Project Docs: `docs/MIDTRANS_ACCOUNT_SETUP_GUIDE.md`
