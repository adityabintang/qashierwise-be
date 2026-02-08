# Midtrans Staging Environment Configuration

## Overview
This document provides instructions for configuring Midtrans credentials in the staging environment.

## Staging Environment Variables

Add the following variables to your staging `.env` file:

```env
# Midtrans Subscription Configuration (STAGING)
SUBSCRIPTION_PROVIDER=midtrans
MIDTRANS_SERVER_KEY=Mid-server-YOUR_SANDBOX_SERVER_KEY
MIDTRANS_CLIENT_KEY=Mid-client-YOUR_SANDBOX_CLIENT_KEY
MIDTRANS_MERCHANT_ID=YOUR_SANDBOX_MERCHANT_ID
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SUBSCRIPTION_SUCCESS_URL="${APP_URL}/subscription/success"
MIDTRANS_SUBSCRIPTION_CANCEL_URL="${APP_URL}/subscription/cancel"
MIDTRANS_SUBSCRIPTION_ERROR_URL="${APP_URL}/subscription/error"
SUBSCRIPTION_TRIAL_DAYS=14
```

## Getting Sandbox Credentials

1. **Login to Midtrans Dashboard**
   - Go to https://dashboard.sandbox.midtrans.com/
   - Login with your Midtrans account

2. **Get Server Key**
   - Navigate to Settings → Access Keys
   - Copy the **Sandbox Server Key**
   - Format: `Mid-server-xxxxxxxxxx`

3. **Get Client Key**
   - In the same Access Keys page
   - Copy the **Sandbox Client Key**
   - Format: `Mid-client-xxxxxxxxxx`

4. **Get Merchant ID**
   - Navigate to Settings → General Settings
   - Copy your **Merchant ID**
   - Format: `Gxxxxxxxxx`

## Webhook Configuration (Staging)

Configure the following webhook URLs in Midtrans Dashboard:

### Payment Notification URL
```
https://your-staging-domain.com/api/webhooks/midtrans
```

### Recurring Notification URL
```
https://your-staging-domain.com/api/webhooks/midtrans
```

### Pay Account Notification URL
```
https://your-staging-domain.com/api/webhooks/midtrans
```

**Note**: All three webhook types use the same endpoint. The system will route events based on the payload.

## Redirect URLs (Staging)

Update your `APP_URL` in staging `.env`:

```env
APP_URL=https://your-staging-domain.com
```

The redirect URLs will automatically be generated:
- Success: `https://your-staging-domain.com/subscription/success`
- Cancel: `https://your-staging-domain.com/subscription/cancel`
- Error: `https://your-staging-domain.com/subscription/error`

## Verification Steps

After configuration, verify the setup:

1. **Check Configuration Loading**
   ```bash
   php artisan tinker
   >>> config('midtrans.server_key')
   >>> config('midtrans.client_key')
   >>> config('midtrans.is_production')
   ```

2. **Test Subscription Creation**
   - Login to your staging application
   - Navigate to pricing page
   - Click "Subscribe" button
   - Verify redirect to Midtrans payment page

3. **Test Webhook Endpoint**
   ```bash
   curl -X POST https://your-staging-domain.com/api/webhooks/midtrans \
     -H "Content-Type: application/json" \
     -d '{"test": "webhook"}'
   ```
   Should return 200 OK

## Security Checklist

- [ ] Server key is kept secret (never commit to git)
- [ ] Client key is used only in frontend
- [ ] Webhook endpoint is accessible from Midtrans servers
- [ ] HTTPS is enabled for all URLs
- [ ] Webhook signature validation is enabled

## Troubleshooting

### Issue: Webhook not receiving events
**Solution**: 
- Verify webhook URL is accessible publicly
- Check firewall settings
- Verify HTTPS certificate is valid
- Check Midtrans dashboard for webhook delivery logs

### Issue: Invalid credentials error
**Solution**:
- Verify you're using sandbox credentials (not production)
- Check for extra spaces in environment variables
- Verify credentials are copied correctly from dashboard

### Issue: Redirect URLs not working
**Solution**:
- Verify `APP_URL` is set correctly
- Check routes are registered: `php artisan route:list | grep subscription`
- Verify middleware is not blocking the routes

## Next Steps

After staging configuration is complete:
1. Test subscription creation flow
2. Test webhook delivery
3. Test all payment scenarios
4. Verify backward compatibility with Polar
5. Proceed to production configuration

## References

- [Midtrans Dashboard (Sandbox)](https://dashboard.sandbox.midtrans.com/)
- [Midtrans Subscription API Documentation](https://docs.midtrans.com/reference/create-subscription)
- [Webhook Setup Guide](../WEBHOOK_SETUP.md)
