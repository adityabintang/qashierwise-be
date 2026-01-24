# Midtrans Setup Checklist

Quick reference checklist for Midtrans account setup. Check off items as you complete them.

## Sandbox Account Setup

### Account Creation
- [ ] Visit https://dashboard.sandbox.midtrans.com/register
- [ ] Fill registration form (business name, email, phone, password)
- [ ] Verify email address
- [ ] Login to sandbox dashboard
- [ ] Complete business profile in Settings

### API Credentials
- [ ] Navigate to Settings → Access Keys
- [ ] Copy Server Key (SB-Mid-server-...)
- [ ] Copy Client Key (SB-Mid-client-...)
- [ ] Add to .env file:
  ```env
  MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxxxxx
  MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxxxxx
  MIDTRANS_IS_PRODUCTION=false
  ```

### Feature Configuration
- [ ] Enable Subscription feature in Settings
- [ ] Configure payment methods (Credit Card, GoPay, etc.)
- [ ] Save payment configuration

### Webhook Setup
- [ ] Set up ngrok for local testing: `ngrok http 8000`
- [ ] Copy ngrok HTTPS URL
- [ ] Navigate to Settings → Configuration
- [ ] Set Payment Notification URL: `https://your-ngrok-url.ngrok.io/api/webhooks/midtrans`
- [ ] Set Recurring Notification URL: `https://your-ngrok-url.ngrok.io/api/webhooks/midtrans`
- [ ] Set Pay Account Notification URL: `https://your-ngrok-url.ngrok.io/api/webhooks/midtrans`
- [ ] Save webhook configuration

### Testing
- [ ] Send test webhook from dashboard
- [ ] Check Laravel logs for webhook receipt
- [ ] Create test subscription
- [ ] Test payment with test card: 4811 1111 1111 1114
- [ ] Verify subscription activation
- [ ] Verify webhook processing

---

## Production Account Setup

### Account Creation
- [ ] Visit https://dashboard.midtrans.com/register
- [ ] Fill registration form
- [ ] Verify email address
- [ ] Prepare KYC documents:
  - [ ] Business registration (SIUP/TDP)
  - [ ] Tax ID (NPWP)
  - [ ] Bank account information
  - [ ] Director/Owner ID card
  - [ ] Business address proof
- [ ] Submit KYC documents
- [ ] Wait for approval (1-3 business days)
- [ ] Receive approval notification
- [ ] Login to production dashboard

### API Credentials
- [ ] Navigate to Settings → Access Keys
- [ ] Copy Production Server Key (Mid-server-...)
- [ ] Copy Production Client Key (Mid-client-...)
- [ ] Add to production .env file:
  ```env
  MIDTRANS_SERVER_KEY=Mid-server-xxxxxxxxxxxxxxxx
  MIDTRANS_CLIENT_KEY=Mid-client-xxxxxxxxxxxxxxxx
  MIDTRANS_IS_PRODUCTION=true
  ```

### Feature Configuration
- [ ] Enable Subscription feature
- [ ] Configure production payment methods
- [ ] Set business information
- [ ] Add business logo (optional)
- [ ] Configure email templates (optional)

### Webhook Setup
- [ ] Verify production domain has valid SSL certificate
- [ ] Test SSL: https://www.ssllabs.com/ssltest/
- [ ] Navigate to Settings → Configuration
- [ ] Set Payment Notification URL: `https://yourdomain.com/api/webhooks/midtrans`
- [ ] Set Recurring Notification URL: `https://yourdomain.com/api/webhooks/midtrans`
- [ ] Set Pay Account Notification URL: `https://yourdomain.com/api/webhooks/midtrans`
- [ ] Save webhook configuration
- [ ] Verify webhook URLs are accessible

### Testing
- [ ] Send test webhook (if available in dashboard)
- [ ] Create test subscription with small amount
- [ ] Complete real payment
- [ ] Verify webhook delivery
- [ ] Check subscription activation
- [ ] Monitor logs for errors
- [ ] Test recurring payment (if possible)

---

## Environment Configuration

### Local Development (.env)
```env
# Midtrans Sandbox
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=false

# Local URLs (with ngrok)
MIDTRANS_SUBSCRIPTION_SUCCESS_URL=https://your-ngrok-url.ngrok.io/subscription/success
MIDTRANS_SUBSCRIPTION_CANCEL_URL=https://your-ngrok-url.ngrok.io/subscription/cancel
MIDTRANS_SUBSCRIPTION_ERROR_URL=https://your-ngrok-url.ngrok.io/subscription/error
```

### Staging (.env)
```env
# Midtrans Sandbox
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=false

# Staging URLs
MIDTRANS_SUBSCRIPTION_SUCCESS_URL=https://staging.yourdomain.com/subscription/success
MIDTRANS_SUBSCRIPTION_CANCEL_URL=https://staging.yourdomain.com/subscription/cancel
MIDTRANS_SUBSCRIPTION_ERROR_URL=https://staging.yourdomain.com/subscription/error
```

### Production (.env)
```env
# Midtrans Production
MIDTRANS_SERVER_KEY=Mid-server-xxxxxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=Mid-client-xxxxxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=true

# Production URLs
MIDTRANS_SUBSCRIPTION_SUCCESS_URL=https://yourdomain.com/subscription/success
MIDTRANS_SUBSCRIPTION_CANCEL_URL=https://yourdomain.com/subscription/cancel
MIDTRANS_SUBSCRIPTION_ERROR_URL=https://yourdomain.com/subscription/error
```

---

## Verification Steps

### After Sandbox Setup
- [ ] Run: `php artisan config:clear`
- [ ] Verify credentials load: `php artisan tinker` → `config('midtrans.server_key')`
- [ ] Test API connection with simple request
- [ ] Create test subscription through application
- [ ] Verify webhook received in logs

### After Production Setup
- [ ] Update production .env file
- [ ] Run: `php artisan config:clear`
- [ ] Verify production credentials load
- [ ] Test with small real transaction
- [ ] Monitor webhook delivery
- [ ] Check subscription status updates
- [ ] Monitor for 24-48 hours

---

## Troubleshooting Quick Reference

| Issue | Solution |
|-------|----------|
| Webhook not received | Check URL accessibility, firewall, ngrok for local |
| Signature validation failed | Verify Server Key, check signature logic |
| Subscription creation failed | Check API credentials, enable subscription feature |
| Payment method unavailable | Enable in dashboard payment configuration |
| Local testing issues | Use ngrok, update webhook URL in dashboard |

---

## Important Links

- **Sandbox Dashboard**: https://dashboard.sandbox.midtrans.com
- **Production Dashboard**: https://dashboard.midtrans.com
- **API Documentation**: https://docs.midtrans.com
- **Subscription API**: https://docs.midtrans.com/reference/create-subscription
- **Support Email**: support@midtrans.com

---

## Notes

- Keep sandbox and production credentials separate
- Never commit credentials to version control
- Test thoroughly in sandbox before production
- Monitor production closely after deployment
- Have rollback plan ready

---

**Last Updated**: January 24, 2026
