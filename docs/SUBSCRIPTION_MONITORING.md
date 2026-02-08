# Subscription System Monitoring Guide

## Overview

This document describes the monitoring setup for the Midtrans subscription system, including logging, alerting, performance monitoring, and troubleshooting procedures.

## Table of Contents

1. [Monitoring Components](#monitoring-components)
2. [Logging](#logging)
3. [Error Alerting](#error-alerting)
4. [Performance Monitoring](#performance-monitoring)
5. [Monitoring Dashboard](#monitoring-dashboard)
6. [Health Checks](#health-checks)
7. [Metrics and KPIs](#metrics-and-kpis)
8. [Troubleshooting](#troubleshooting)
9. [Alert Response Procedures](#alert-response-procedures)

---

## Monitoring Components

The subscription monitoring system consists of:

1. **SubscriptionMonitoringService** - Core monitoring service for tracking events and alerting
2. **Performance Middleware** - Tracks request performance metrics
3. **Health Check Command** - CLI command for system health verification
4. **Monitoring Dashboard** - Web-based dashboard for viewing metrics
5. **Structured Logging** - Comprehensive event logging with context

---

## Logging

### Log Levels

The system uses the following log levels:

- **DEBUG**: Detailed diagnostic information (development only)
- **INFO**: General informational messages (subscription events, webhook processing)
- **WARNING**: Warning messages (payment failures, slow requests)
- **ERROR**: Error messages (API failures, processing errors)
- **CRITICAL**: Critical issues requiring immediate attention (high error rates, security issues)

### Subscription Event Logging

All subscription events are logged with structured data:

```php
Log::info('Subscription created', [
    'event' => 'subscription.created',
    'subscriptionId' => $subscription->id,
    'userId' => $user->id,
    'planName' => $planName,
    'status' => $status,
    'provider' => 'midtrans',
]);
```

**Key Events Logged:**
- `subscription.created` - New subscription created
- `subscription.updated` - Subscription updated
- `subscription.cancelled` - Subscription cancelled
- `subscription.user_cancelled` - User-initiated cancellation
- `subscription.midtrans.created` - Midtrans subscription created
- `subscription.midtrans.updated` - Midtrans subscription updated

### Webhook Event Logging

All webhook events are logged with performance metrics:

```php
Log::info('Midtrans webhook processed successfully', [
    'event' => 'webhook.processed',
    'webhook_type' => 'subscription',
    'order_id' => $orderId,
    'subscription_id' => $subscriptionId,
    'transaction_status' => $transactionStatus,
    'duration_ms' => $duration,
]);
```

**Key Webhook Events:**
- `webhook.received` - Webhook received
- `webhook.processed` - Webhook processed successfully
- `webhook.processing_error` - Webhook processing failed
- `webhook.validation_failed` - Signature validation failed
- `webhook.payment_notification` - Payment notification received
- `webhook.recurring_notification` - Recurring payment notification
- `webhook.pay_account_notification` - Account status notification

### API Call Logging

All Midtrans API calls are logged with timing:

```php
Log::info('Midtrans subscription created successfully', [
    'event' => 'subscription.create.success',
    'subscriptionId' => $subscriptionId,
    'userId' => $userId,
    'planId' => $planId,
    'status' => $status,
    'duration_ms' => $duration,
]);
```

### Log Configuration

Configure logging in `.env`:

```env
# Monitoring & Logging
MONITORING_ENABLED=true
MONITORING_SUBSCRIPTION_LOG_LEVEL=info
MONITORING_WEBHOOK_LOG_LEVEL=info
MONITORING_WEBHOOK_LOG_PAYLOAD=false
MONITORING_API_LOG_LEVEL=info
MONITORING_ERROR_LOG_TRACE=true
```

### Viewing Logs

**Laravel Log Files:**
```bash
# View latest logs
tail -f storage/logs/laravel.log

# Search for subscription events
grep "subscription\." storage/logs/laravel.log

# Search for webhook events
grep "webhook\." storage/logs/laravel.log

# Search for errors
grep "ERROR" storage/logs/laravel.log
```

---

## Error Alerting

### Alert Types

The monitoring service provides several alert types:

1. **Subscription Creation Failed** - Critical alert when subscription creation fails
2. **Webhook Processing Failed** - Alert when webhook processing fails
3. **API Timeout** - Alert when Midtrans API calls exceed timeout threshold
4. **Payment Failed** - Alert when subscription payment fails
5. **Webhook Signature Invalid** - Security alert for invalid webhook signatures
6. **High Error Rate** - Alert when error rate exceeds threshold

### Alert Configuration

Configure alerting in `.env`:

```env
# Alert Channels
MONITORING_EMAIL_ALERTS=true
MONITORING_ALERT_EMAILS=admin@example.com,ops@example.com
MONITORING_SLACK_ALERTS=true
MONITORING_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Alert Thresholds
MONITORING_ERROR_RATE_THRESHOLD=0.1          # 10% error rate
MONITORING_WEBHOOK_FAILURE_THRESHOLD=5       # 5 consecutive failures
MONITORING_API_TIMEOUT_THRESHOLD=5000        # 5 seconds
MONITORING_INVALID_SIGNATURE_THRESHOLD=5     # 5 invalid signatures
```

### Alert Severity Levels

- **LOW**: Informational, no immediate action required
- **MEDIUM**: Requires attention within 24 hours
- **HIGH**: Requires attention within 1 hour
- **CRITICAL**: Requires immediate attention

### Alert Examples

**High Error Rate Alert:**
```
Alert: Subscription Creation Error Rate Exceeded
Severity: HIGH
Error Rate: 15.5%
Threshold: 10%
Recent Errors: [list of recent errors]
Action Required: Investigate subscription creation failures
```

**Webhook Failure Alert:**
```
Alert: Webhook Processing Consecutive Failures
Severity: HIGH
Webhook Type: recurring
Consecutive Failures: 5
Threshold: 5
Action Required: Check webhook processing logic and Midtrans connectivity
```

**Security Alert:**
```
Alert: Multiple Invalid Webhook Signatures
Severity: CRITICAL
IP Address: 192.168.1.100
Attempts: 10
Action Required: Investigate potential security breach
```

---

## Performance Monitoring

### Performance Metrics

The system tracks the following performance metrics:

1. **Request Duration** - Time taken to process requests
2. **Memory Usage** - Memory consumed during request processing
3. **API Response Time** - Time taken for Midtrans API calls
4. **Webhook Processing Time** - Time taken to process webhooks
5. **Database Query Time** - Time taken for database operations

### Performance Thresholds

Configure performance thresholds in `.env`:

```env
MONITORING_PERFORMANCE_ENABLED=true
MONITORING_SLOW_QUERY_THRESHOLD=1000    # 1 second
MONITORING_SLOW_API_THRESHOLD=3000      # 3 seconds
```

### Slow Request Alerts

Requests exceeding the threshold are logged:

```php
Log::warning('Slow request detected', [
    'alert' => 'slow_request',
    'method' => 'POST',
    'path' => '/subscription/checkout',
    'duration_ms' => 4500,
    'threshold_ms' => 3000,
    'severity' => 'medium',
]);
```

### Performance Headers

In debug mode, performance headers are added to responses:

```
X-Response-Time: 245.67ms
X-Memory-Usage: 12.5 MB
```

---

## Monitoring Dashboard

### Accessing the Dashboard

The monitoring dashboard is available at:

```
https://your-domain.com/monitoring/dashboard
```

**Note:** Access is restricted to admin users with the `view-monitoring` permission.

### Dashboard Features

1. **System Health Status** - Overall health indicator (Healthy/Degraded)
2. **Active Issues** - List of current issues requiring attention
3. **Subscription Creation Metrics** - Success/error counts and error rate
4. **Webhook Processing Metrics** - Errors by webhook type
5. **Payment Failure Metrics** - Payment failure count and rate
6. **API Performance Metrics** - Timeout counts
7. **Security Metrics** - Invalid signature counts

### Dashboard Auto-Refresh

The dashboard auto-refreshes every 30 seconds to show real-time data.

### API Endpoints

**Get Metrics (JSON):**
```
GET /monitoring/metrics
```

**Get Health Status (JSON):**
```
GET /monitoring/health
```

**Get Simple Status:**
```
GET /api/health/subscription
```

---

## Health Checks

### CLI Health Check

Run the health check command:

```bash
# Basic health check
php artisan subscription:health-check

# JSON output
php artisan subscription:health-check --json

# With alerting
php artisan subscription:health-check --alert
```

### Health Check Output

```
Checking subscription system health...

Status: HEALTHY
Timestamp: 2026-01-24T12:00:00+00:00

Metrics:
  Subscription Creation:
    Errors: 2
    Successes: 98
    Error Rate: 2.00%

  Webhook Processing:
    Payment Errors: 0
    Recurring Errors: 1
    Pay Account Errors: 0

  Payment Failures:
    Count: 3
    Error Rate: 3.00%

  API Timeouts:
    Count: 0

  Security:
    Invalid Signatures: 0

✓ No issues detected. System is healthy.
```

### Automated Health Checks

Schedule health checks in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Run health check every 5 minutes
    $schedule->command('subscription:health-check --alert')
             ->everyFiveMinutes()
             ->emailOutputOnFailure('admin@example.com');
}
```

### Health Check API

External monitoring services can use the health check API:

```bash
curl https://your-domain.com/api/health/subscription
```

**Response (Healthy):**
```json
{
  "status": "healthy",
  "timestamp": "2026-01-24T12:00:00+00:00"
}
```

**Response (Degraded):**
```json
{
  "status": "degraded",
  "timestamp": "2026-01-24T12:00:00+00:00"
}
```

---

## Metrics and KPIs

### Key Performance Indicators

1. **Subscription Creation Success Rate** - Target: > 95%
2. **Webhook Processing Success Rate** - Target: > 99%
3. **Payment Success Rate** - Target: > 90%
4. **API Response Time** - Target: < 3 seconds
5. **Webhook Processing Time** - Target: < 5 seconds

### Metrics Retention

Metrics are retained for 24 hours by default. Configure retention:

```env
MONITORING_METRICS_RETENTION=24  # hours
```

### Metrics Storage

Metrics are stored in cache with hourly aggregation:

```
monitoring:errors:subscription.creation.failed:2026-01-24-12
monitoring:success:subscription.creation:2026-01-24-12
monitoring:consecutive_failures:webhook.payment
```

---

## Troubleshooting

### Common Issues

#### High Subscription Creation Error Rate

**Symptoms:**
- Error rate > 10%
- Multiple subscription creation failures

**Possible Causes:**
1. Midtrans API issues
2. Invalid credentials
3. Network connectivity problems
4. Invalid plan configuration

**Investigation Steps:**
1. Check Midtrans API status
2. Verify credentials in `.env`
3. Check recent error logs: `grep "subscription.create.failed" storage/logs/laravel.log`
4. Test API connectivity: `php artisan subscription:health-check`

**Resolution:**
1. If API issue: Wait for Midtrans to resolve
2. If credentials: Update `.env` and restart services
3. If network: Check firewall/proxy settings
4. If config: Verify `config/subscription.php`

#### Webhook Processing Failures

**Symptoms:**
- Consecutive webhook failures
- Webhooks not updating subscription status

**Possible Causes:**
1. Invalid webhook signature
2. Database connection issues
3. Processing logic errors
4. Queue worker not running

**Investigation Steps:**
1. Check webhook logs: `grep "webhook.processing_error" storage/logs/laravel.log`
2. Verify webhook signature validation
3. Check queue worker status: `php artisan queue:work --once`
4. Test webhook manually

**Resolution:**
1. If signature: Verify server key in `.env`
2. If database: Check database connectivity
3. If logic: Review error trace and fix code
4. If queue: Start queue worker: `php artisan queue:work`

#### High Payment Failure Rate

**Symptoms:**
- Payment failure rate > 10%
- Multiple payment declined events

**Possible Causes:**
1. Customer payment method issues
2. Insufficient funds
3. Card expired
4. Fraud detection

**Investigation Steps:**
1. Check payment failure logs: `grep "payment_failed" storage/logs/laravel.log`
2. Review failure reasons in logs
3. Check Midtrans dashboard for details

**Resolution:**
1. Contact affected customers
2. Provide alternative payment methods
3. Update payment retry logic if needed

#### API Timeouts

**Symptoms:**
- Slow API response times
- Timeout errors in logs

**Possible Causes:**
1. Midtrans API performance issues
2. Network latency
3. Large payload size

**Investigation Steps:**
1. Check API timeout logs: `grep "api_timeout" storage/logs/laravel.log`
2. Test API response time manually
3. Check network latency

**Resolution:**
1. Increase timeout threshold if appropriate
2. Implement retry logic with exponential backoff
3. Contact Midtrans support if persistent

---

## Alert Response Procedures

### Critical Alerts (Immediate Response Required)

#### Multiple Invalid Webhook Signatures

**Response Time:** Immediate (< 5 minutes)

**Steps:**
1. Check alert details for IP address and attempt count
2. Review webhook logs for suspicious activity
3. Verify webhook signature validation logic
4. Check if Midtrans server key has changed
5. If security breach suspected:
   - Block suspicious IP addresses
   - Rotate webhook credentials
   - Contact Midtrans support
   - Review recent webhook activity

#### High Error Rate (> 20%)

**Response Time:** Immediate (< 15 minutes)

**Steps:**
1. Check monitoring dashboard for affected component
2. Review recent error logs
3. Check Midtrans API status
4. Verify system resources (CPU, memory, disk)
5. If API issue: Monitor Midtrans status page
6. If system issue: Scale resources or restart services
7. Notify stakeholders of issue and ETA

### High Priority Alerts (Response within 1 hour)

#### Webhook Processing Failures

**Response Time:** < 1 hour

**Steps:**
1. Review webhook failure logs
2. Check queue worker status
3. Verify database connectivity
4. Test webhook processing manually
5. Fix identified issues
6. Monitor for continued failures

#### Subscription Creation Failures

**Response Time:** < 1 hour

**Steps:**
1. Review subscription creation logs
2. Test subscription creation manually
3. Verify Midtrans credentials
4. Check plan configuration
5. Fix identified issues
6. Notify affected users if needed

### Medium Priority Alerts (Response within 24 hours)

#### Slow Requests

**Response Time:** < 24 hours

**Steps:**
1. Review slow request logs
2. Identify bottlenecks (database, API, processing)
3. Optimize slow queries or logic
4. Consider caching strategies
5. Monitor performance improvements

#### Payment Failures

**Response Time:** < 24 hours

**Steps:**
1. Review payment failure patterns
2. Identify common failure reasons
3. Contact affected customers if needed
4. Implement improvements to reduce failures

---

## Best Practices

### Monitoring

1. **Regular Health Checks** - Run automated health checks every 5-15 minutes
2. **Log Review** - Review logs daily for patterns and issues
3. **Dashboard Monitoring** - Check dashboard multiple times per day
4. **Alert Response** - Respond to alerts within defined SLAs
5. **Metrics Analysis** - Analyze trends weekly to identify improvements

### Alerting

1. **Appropriate Thresholds** - Set thresholds based on baseline metrics
2. **Alert Fatigue** - Avoid too many low-priority alerts
3. **Clear Actions** - Ensure alerts have clear response procedures
4. **Alert Testing** - Test alert channels regularly
5. **Alert Documentation** - Keep alert procedures up to date

### Performance

1. **Baseline Metrics** - Establish baseline performance metrics
2. **Regular Optimization** - Optimize slow queries and requests
3. **Caching Strategy** - Implement caching where appropriate
4. **Resource Monitoring** - Monitor system resources (CPU, memory, disk)
5. **Load Testing** - Perform load testing before major releases

---

## Support and Escalation

### Internal Escalation

1. **Level 1** - Development team (initial response)
2. **Level 2** - Senior developers/architects (complex issues)
3. **Level 3** - CTO/Technical leadership (critical issues)

### External Escalation

**Midtrans Support:**
- Email: support@midtrans.com
- Dashboard: https://dashboard.midtrans.com
- Documentation: https://docs.midtrans.com

**Escalation Criteria:**
- API downtime > 15 minutes
- Webhook delivery failures > 1 hour
- Payment processing issues affecting multiple users
- Security concerns

---

## Appendix

### Configuration Reference

See `config/monitoring.php` for full configuration options.

### Log Event Reference

See source code for complete list of log events:
- `app/Services/SubscriptionService.php`
- `app/Services/MidtransSubscriptionService.php`
- `app/Http/Controllers/Api/MidtransWebhookController.php`
- `app/Http/Controllers/SubscriptionController.php`

### Metrics Reference

See `app/Services/SubscriptionMonitoringService.php` for metrics implementation.

---

## Changelog

- **2026-01-24** - Initial monitoring setup documentation
