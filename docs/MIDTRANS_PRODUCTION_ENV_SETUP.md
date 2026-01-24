# Midtrans Production Environment Configuration

## Overview
This document provides instructions for configuring Midtrans credentials in the production environment.

## ⚠️ IMPORTANT SECURITY NOTICE

**CRITICAL**: Production credentials must be handled with extreme care:
- Never commit production credentials to version control
- Use secure environment variable management (e.g., Laravel Forge, AWS Secrets Manager)
- Limit access to production credentials to authorized personnel only
- Rotate credentials periodically
- Monitor for unauthorized access

## Production Environment Variables

Add the following variables to your production `.env` file:

```env
# Midtrans Subscription Configuration (PRODUCTION)
SUBSCRIPTION_PROVIDER=midtrans
MIDTRANS_SERVER_KEY=Mid-server-YOUR_PRODUCTION_SERVER_KEY
MIDTRANS_CLIENT_KEY=Mid-client-YOUR_PRODUCTION_CLIENT_KEY
MIDTRANS_MERCHANT_ID=YOUR_PRODUCTION_MERCHANT_ID
MIDTRANS_IS_PRODUCTION=true
MIDTRANS_SUBSCRIPTION_SUCCESS_URL="${APP_URL}/subscription/success"
MIDTRANS_SUBSCRIPTION_CANCEL_URL="${APP_URL}/subscription/cancel"
MIDTRANS_SUBSCRIPTION_ERROR_URL="${APP_URL}/subscription/error"
SUBSCRIPTION_TRIAL_DAYS=14
```

## Getting Production Credentials

1. **Login to Midtrans Production Dashboard**
   - Go to https://dashboard.midtrans.com/
   - Login with your Midtrans account
   - **Note**: This is different from sandbox dashboard

2. **Get Production Server Key**
   - Navigate to Settings → Access Keys
   - Copy the **Production Server Key**
   - Format: `Mid-server-xxxxxxxxxx`
   - **KEEP THIS SECRET**

3. **Get Production Client Key**
   - In the same Access Keys page
   - Copy the **Production Client Key**
   - Format: `Mid-client-xxxxxxxxxx`

4. **Get Merchant ID**
   - Navigate to Settings → General Settings
   - Copy your **Merchant ID**
   - Format: `Gxxxxxxxxx`

## Webhook Configuration (Production)

Configure the following webhook URLs in Midtrans Production Dashboard:

### Payment Notification URL
```
https://your-production-domain.com/api/webhooks/midtrans
```

### Recurring Notification URL
```
https://your-production-domain.com/api/webhooks/midtrans
```

### Pay Account Notification URL
```
https://your-production-domain.com/api/webhooks/midtrans
```

**Important**: 
- All webhook URLs must use HTTPS
- Ensure your SSL certificate is valid
- Webhook endpoint must be publicly accessible

## Redirect URLs (Production)

Update your `APP_URL` in production `.env`:

```env
APP_URL=https://your-production-domain.com
```

The redirect URLs will automatically be generated:
- Success: `https://your-production-domain.com/subscription/success`
- Cancel: `https://your-production-domain.com/subscription/cancel`
- Error: `https://your-production-domain.com/subscription/error`

## Pre-Deployment Checklist

Before deploying to production:

### Configuration
- [ ] Production credentials obtained from Midtrans dashboard
- [ ] `MIDTRANS_IS_PRODUCTION=true` is set
- [ ] `APP_URL` points to production domain
- [ ] All redirect URLs are correct
- [ ] Webhook URLs configured in Midtrans dashboard

### Security
- [ ] Server key is stored securely (not in git)
- [ ] HTTPS is enabled and certificate is valid
- [ ] Webhook signature validation is enabled
- [ ] Rate limiting is configured on webhook endpoint
- [ ] CSRF protection is disabled for webhook endpoint only

### Testing
- [ ] All tests passing in staging
- [ ] Webhook delivery tested in sandbox
- [ ] Payment flow tested end-to-end in sandbox
- [ ] Backward compatibility verified with Polar

### Monitoring
- [ ] Logging configured for subscription events
- [ ] Error alerting configured
- [ ] Performance monitoring enabled
- [ ] Webhook delivery monitoring enabled

### Backup
- [ ] Database backup completed
- [ ] Rollback plan documented
- [ ] Emergency contact list prepared

## Deployment Steps

1. **Backup Database**
   ```bash
   php artisan backup:run
   ```

2. **Deploy Code**
   ```bash
   git pull origin main
   composer install --no-dev --optimize-autoloader
   ```

3. **Update Environment Variables**
   - Add production Midtrans credentials
   - Set `MIDTRANS_IS_PRODUCTION=true`
   - Update `APP_URL` to production domain

4. **Run Migrations**
   ```bash
   php artisan migrate --force
   ```

5. **Clear Caches**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   php artisan view:clear
   php artisan config:cache
   php artisan route:cache
   ```

6. **Verify Configuration**
   ```bash
   php artisan tinker
   >>> config('midtrans.is_production')
   // Should return: true
   >>> config('midtrans.server_key')
   // Should return: Mid-server-xxxxx (production key)
   ```

## Post-Deployment Verification

### 1. Configuration Check
```bash
php check_midtrans_config.php
```

Expected output:
```
✓ Midtrans Server Key: Configured
✓ Midtrans Client Key: Configured
✓ Production Mode: Enabled
✓ Webhook URL: Accessible
```

### 2. Test Subscription Creation
- Login to production application
- Navigate to pricing page
- Click "Subscribe" button
- Verify redirect to Midtrans payment page (production)
- **DO NOT complete payment yet** (use test account first)

### 3. Test Webhook Endpoint
```bash
curl -X POST https://your-production-domain.com/api/webhooks/midtrans \
  -H "Content-Type: application/json" \
  -d '{"test": "webhook"}'
```
Should return 200 OK

### 4. Monitor Logs
```bash
tail -f storage/logs/laravel.log
```
Watch for any errors or warnings

## Monitoring and Alerting

### Key Metrics to Monitor

1. **Subscription Creation Rate**
   - Track successful vs failed subscriptions
   - Alert if failure rate > 5%

2. **Webhook Processing**
   - Track webhook delivery success rate
   - Alert if processing time > 5 seconds
   - Alert if webhook failures > 1%

3. **Payment Success Rate**
   - Track payment success vs failure
   - Alert if failure rate increases suddenly

4. **API Response Times**
   - Monitor Midtrans API response times
   - Alert if response time > 3 seconds

### Log Monitoring

Monitor these log patterns:
```bash
# Subscription creation errors
grep "Midtrans API error" storage/logs/laravel.log

# Webhook processing errors
grep "Webhook processing error" storage/logs/laravel.log

# Signature validation failures
grep "Invalid webhook signature" storage/logs/laravel.log
```

## Rollback Plan

If critical issues occur:

1. **Immediate Rollback**
   ```bash
   git checkout previous-stable-version
   composer install --no-dev
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Switch Back to Polar** (if needed)
   ```env
   SUBSCRIPTION_PROVIDER=polar
   ```

3. **Restore Database** (if needed)
   ```bash
   php artisan backup:restore
   ```

4. **Notify Users**
   - Send notification about temporary service interruption
   - Provide ETA for resolution

## Security Best Practices

### Credential Management
- Store production credentials in secure vault (e.g., AWS Secrets Manager, HashiCorp Vault)
- Use environment-specific credentials (never share between staging/production)
- Rotate credentials every 90 days
- Audit credential access regularly

### Webhook Security
- Always validate webhook signatures
- Use HTTPS for all webhook endpoints
- Implement rate limiting (max 100 requests/minute)
- Log all webhook requests for audit trail
- Monitor for suspicious patterns

### Data Protection
- Never log sensitive data (credit card numbers, tokens)
- Encrypt sensitive data at rest
- Use secure connections (HTTPS/TLS) for all API calls
- Implement proper access controls

## Troubleshooting

### Issue: Production credentials not working
**Solution**:
1. Verify you're using production credentials (not sandbox)
2. Check `MIDTRANS_IS_PRODUCTION=true`
3. Verify credentials are active in Midtrans dashboard
4. Check for typos or extra spaces

### Issue: Webhook not receiving events
**Solution**:
1. Verify webhook URL is publicly accessible
2. Check SSL certificate is valid
3. Verify webhook URL in Midtrans dashboard
4. Check server firewall settings
5. Review Midtrans webhook delivery logs

### Issue: Payment page shows sandbox environment
**Solution**:
1. Clear application cache: `php artisan config:clear`
2. Verify `MIDTRANS_IS_PRODUCTION=true`
3. Check client key is production key
4. Clear browser cache

### Issue: Subscription status not updating
**Solution**:
1. Check webhook delivery in Midtrans dashboard
2. Verify webhook signature validation
3. Check application logs for errors
4. Verify queue workers are running

## Emergency Contacts

Maintain a list of emergency contacts:
- Midtrans Support: support@midtrans.com
- Technical Lead: [Your contact]
- DevOps Team: [Your contact]
- On-Call Engineer: [Your contact]

## Compliance and Legal

### Data Privacy
- Ensure compliance with PCI DSS for payment data
- Follow GDPR/local data protection regulations
- Maintain audit logs for financial transactions
- Implement data retention policies

### Financial Regulations
- Comply with local payment regulations
- Maintain transaction records as required by law
- Implement proper accounting practices
- Ensure tax compliance

## Next Steps

After production deployment:
1. Monitor for 48 hours continuously
2. Collect user feedback
3. Review error logs daily for first week
4. Optimize based on performance metrics
5. Document lessons learned
6. Plan for future enhancements

## References

- [Midtrans Production Dashboard](https://dashboard.midtrans.com/)
- [Midtrans Subscription API Documentation](https://docs.midtrans.com/reference/create-subscription)
- [Midtrans Security Best Practices](https://docs.midtrans.com/en/security/overview)
- [PCI DSS Compliance Guide](https://www.pcisecuritystandards.org/)
