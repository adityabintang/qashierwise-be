# Midtrans Webhook Configuration Guide

## Overview
This guide provides step-by-step instructions for configuring webhook URLs in the Midtrans Dashboard for subscription notifications.

## Webhook Endpoint

The application uses a single webhook endpoint for all Midtrans notifications:

```
POST /api/webhooks/midtrans
```

**Full URLs**:
- **Sandbox**: `https://your-staging-domain.com/api/webhooks/midtrans`
- **Production**: `https://your-production-domain.com/api/webhooks/midtrans`

## Webhook Types

Midtrans requires three webhook URL configurations for subscription management:

### 1. Payment Notification URL
Receives notifications for initial subscription payments and one-time transactions.

**Events**:
- `transaction.success` - Payment successful
- `transaction.pending` - Payment pending
- `transaction.failed` - Payment failed
- `transaction.expired` - Payment expired

### 2. Recurring Notification URL
Receives notifications for recurring subscription payments.

**Events**:
- `subscription.created` - Subscription created
- `subscription.updated` - Subscription updated
- `subscription.active` - Subscription activated
- `subscription.cancelled` - Subscription cancelled
- `subscription.expired` - Subscription expired

### 3. Pay Account Notification URL
Receives notifications for pay account status changes.

**Events**:
- `pay_account.created` - Pay account created
- `pay_account.updated` - Pay account updated
- `pay_account.deleted` - Pay account deleted

## Configuration Steps

### Sandbox Environment

1. **Login to Sandbox Dashboard**
   - Navigate to https://dashboard.sandbox.midtrans.com/
   - Login with your Midtrans account

2. **Navigate to Settings**
   - Click on **Settings** in the left sidebar
   - Select **Configuration**

3. **Configure Payment Notification URL**
   - Find the **Payment Notification URL** field
   - Enter: `https://your-staging-domain.com/api/webhooks/midtrans`
   - Click **Save**

4. **Configure Recurring Notification URL**
   - Find the **Recurring Notification URL** field
   - Enter: `https://your-staging-domain.com/api/webhooks/midtrans`
   - Click **Save**

5. **Configure Pay Account Notification URL**
   - Find the **Pay Account Notification URL** field
   - Enter: `https://your-staging-domain.com/api/webhooks/midtrans`
   - Click **Save**

### Production Environment

1. **Login to Production Dashboard**
   - Navigate to https://dashboard.midtrans.com/
   - Login with your Midtrans account

2. **Navigate to Settings**
   - Click on **Settings** in the left sidebar
   - Select **Configuration**

3. **Configure Payment Notification URL**
   - Find the **Payment Notification URL** field
   - Enter: `https://your-production-domain.com/api/webhooks/midtrans`
   - Click **Save**

4. **Configure Recurring Notification URL**
   - Find the **Recurring Notification URL** field
   - Enter: `https://your-production-domain.com/api/webhooks/midtrans`
   - Click **Save**

5. **Configure Pay Account Notification URL**
   - Find the **Pay Account Notification URL** field
   - Enter: `https://your-production-domain.com/api/webhooks/midtrans`
   - Click **Save**

## Webhook URL Requirements

### HTTPS Required
- All webhook URLs **must** use HTTPS
- HTTP URLs will be rejected by Midtrans
- Ensure your SSL certificate is valid and not expired

### Public Accessibility
- Webhook endpoint must be publicly accessible
- Cannot be behind VPN or firewall
- Must respond within 30 seconds

### Response Requirements
- Return HTTP 200 OK for successful processing
- Return response within 30 seconds
- Any other status code will trigger retry

## Webhook Security

### Signature Validation

The application automatically validates webhook signatures using SHA512 hash:

```php
$signatureString = $orderId . $statusCode . $grossAmount . $serverKey;
$expectedSignature = hash('sha512', $signatureString);
```

**Important**: Never disable signature validation in production.

### IP Whitelisting (Optional)

For additional security, you can whitelist Midtrans IP addresses:

**Midtrans IP Ranges**:
- Production: Contact Midtrans support for current IP ranges
- Sandbox: Contact Midtrans support for current IP ranges

**Note**: IP addresses may change, so monitor Midtrans announcements.

## Testing Webhook Configuration

### 1. Test Endpoint Accessibility

```bash
# Test from external server (not localhost)
curl -X POST https://your-domain.com/api/webhooks/midtrans \
  -H "Content-Type: application/json" \
  -d '{"test": "webhook"}'
```

Expected response:
```json
{
  "status": "ok"
}
```

### 2. Test with Midtrans Simulator

1. Login to Midtrans Dashboard
2. Navigate to **Settings** → **Webhook**
3. Click **Test Webhook**
4. Select event type (e.g., `transaction.success`)
5. Click **Send Test**
6. Verify webhook is received in application logs

### 3. Monitor Webhook Delivery

Check application logs:
```bash
tail -f storage/logs/laravel.log | grep "Midtrans webhook"
```

Expected log entries:
```
[2026-01-24 10:00:00] local.INFO: Midtrans webhook received {"event": "transaction.success"}
[2026-01-24 10:00:01] local.INFO: Webhook signature validated successfully
[2026-01-24 10:00:02] local.INFO: Subscription status updated {"subscription_id": "sub_123"}
```

## Webhook Payload Examples

### Payment Notification Payload

```json
{
  "transaction_time": "2026-01-24 10:00:00",
  "transaction_status": "settlement",
  "transaction_id": "txn_123456",
  "status_message": "Success, transaction is found",
  "status_code": "200",
  "signature_key": "abc123...",
  "payment_type": "credit_card",
  "order_id": "sub_123456_1",
  "merchant_id": "G816352475",
  "gross_amount": "99000.00",
  "fraud_status": "accept",
  "currency": "IDR"
}
```

### Recurring Notification Payload

```json
{
  "transaction_time": "2026-02-24 10:00:00",
  "transaction_status": "settlement",
  "transaction_id": "txn_123457",
  "subscription_id": "sub_123456",
  "status_code": "200",
  "signature_key": "def456...",
  "order_id": "sub_123456_2",
  "gross_amount": "99000.00",
  "currency": "IDR"
}
```

### Subscription Event Payload

```json
{
  "event_type": "subscription.created",
  "subscription_id": "sub_123456",
  "status": "active",
  "created_at": "2026-01-24T10:00:00Z",
  "signature_key": "ghi789..."
}
```

## Webhook Retry Logic

### Midtrans Retry Behavior

If webhook delivery fails, Midtrans will retry:
- **Retry 1**: After 2 minutes
- **Retry 2**: After 10 minutes
- **Retry 3**: After 30 minutes
- **Retry 4**: After 1 hour
- **Retry 5**: After 3 hours
- **Retry 6**: After 6 hours
- **Retry 7**: After 12 hours

**Total**: Up to 7 retries over 24 hours

### Application Idempotency

The application handles duplicate webhooks gracefully:
- Uses `order_id` or `transaction_id` as idempotency key
- Prevents duplicate subscription updates
- Logs duplicate webhook attempts

## Monitoring Webhook Delivery

### Midtrans Dashboard

1. Login to Midtrans Dashboard
2. Navigate to **Transactions** → **Webhook Logs**
3. View webhook delivery status:
   - ✅ Success (200 OK)
   - ⚠️ Pending (retrying)
   - ❌ Failed (all retries exhausted)

### Application Logs

Monitor webhook processing:
```bash
# View all webhook events
grep "Midtrans webhook" storage/logs/laravel.log

# View failed webhooks
grep "Webhook processing error" storage/logs/laravel.log

# View signature validation failures
grep "Invalid webhook signature" storage/logs/laravel.log
```

### Alerting

Set up alerts for:
- Webhook processing failures > 5%
- Signature validation failures > 1%
- Webhook processing time > 5 seconds
- No webhooks received for > 1 hour (during active subscriptions)

## Troubleshooting

### Issue: Webhook not received

**Possible Causes**:
1. Webhook URL not configured in Midtrans dashboard
2. Webhook URL not publicly accessible
3. SSL certificate invalid or expired
4. Firewall blocking Midtrans IPs
5. Application server down

**Solutions**:
1. Verify webhook URL in Midtrans dashboard
2. Test endpoint accessibility from external server
3. Check SSL certificate validity
4. Review firewall rules
5. Check application server status

### Issue: Signature validation failed

**Possible Causes**:
1. Server key mismatch (using sandbox key in production)
2. Payload modified in transit
3. Incorrect signature calculation
4. Malicious webhook attempt

**Solutions**:
1. Verify correct server key is configured
2. Check webhook payload integrity
3. Review signature calculation logic
4. Block suspicious IP addresses

### Issue: Webhook processing timeout

**Possible Causes**:
1. Database query slow
2. External API call blocking
3. Heavy processing in webhook handler
4. Queue worker not running

**Solutions**:
1. Optimize database queries
2. Move external API calls to queue
3. Process webhooks asynchronously
4. Start queue workers: `php artisan queue:work`

### Issue: Duplicate webhook processing

**Possible Causes**:
1. Midtrans retry due to slow response
2. Idempotency key not implemented
3. Race condition in processing

**Solutions**:
1. Respond with 200 OK immediately
2. Implement idempotency using transaction_id
3. Use database locks for critical sections

## Webhook Configuration Checklist

### Pre-Configuration
- [ ] Application deployed and accessible
- [ ] HTTPS enabled with valid SSL certificate
- [ ] Webhook endpoint tested and responding
- [ ] Server key configured in application

### Configuration
- [ ] Payment Notification URL configured
- [ ] Recurring Notification URL configured
- [ ] Pay Account Notification URL configured
- [ ] All URLs use HTTPS
- [ ] All URLs are publicly accessible

### Testing
- [ ] Test webhook endpoint accessibility
- [ ] Test webhook with Midtrans simulator
- [ ] Verify webhook signature validation
- [ ] Test idempotency handling
- [ ] Monitor webhook delivery logs

### Monitoring
- [ ] Webhook logging enabled
- [ ] Error alerting configured
- [ ] Delivery monitoring enabled
- [ ] Performance metrics tracked

### Security
- [ ] Signature validation enabled
- [ ] HTTPS enforced
- [ ] Rate limiting configured
- [ ] Suspicious activity monitoring enabled

## Environment-Specific URLs

### Development (Local)
```
Not supported - webhooks require public URL
Use ngrok or similar for local testing:
https://abc123.ngrok.io/api/webhooks/midtrans
```

### Staging
```
https://staging.your-domain.com/api/webhooks/midtrans
```

### Production
```
https://your-domain.com/api/webhooks/midtrans
```

## Quick Reference

| Environment | Dashboard URL | Webhook URL |
|------------|---------------|-------------|
| Sandbox | https://dashboard.sandbox.midtrans.com/ | https://staging.your-domain.com/api/webhooks/midtrans |
| Production | https://dashboard.midtrans.com/ | https://your-domain.com/api/webhooks/midtrans |

## Support

If you encounter issues:
1. Check Midtrans documentation: https://docs.midtrans.com/
2. Review webhook logs in Midtrans dashboard
3. Check application logs for errors
4. Contact Midtrans support: support@midtrans.com

## References

- [Midtrans Webhook Documentation](https://docs.midtrans.com/en/after-payment/http-notification)
- [Midtrans Subscription API](https://docs.midtrans.com/reference/create-subscription)
- [Webhook Security Best Practices](https://docs.midtrans.com/en/security/overview)
- [Application Webhook Handler](../app/Http/Controllers/Api/MidtransWebhookController.php)
