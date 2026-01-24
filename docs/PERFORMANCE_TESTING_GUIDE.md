# Performance Testing Guide

## Overview
This guide provides instructions for conducting performance tests on the Midtrans subscription feature to ensure it meets non-functional requirements.

## Performance Requirements

### Response Time Targets
- **Checkout Session Creation**: < 3 seconds
- **Webhook Processing**: < 5 seconds
- **Subscription Query**: < 100 milliseconds
- **Feature Access Check**: < 50 milliseconds
- **Dashboard Load**: < 2 seconds

### Throughput Targets
- **Concurrent Users**: Support 50+ concurrent users
- **Webhook Requests**: Handle 100+ webhooks per minute
- **Database Queries**: < 50ms for indexed queries

### Resource Usage Targets
- **Memory**: < 128 MB per request
- **CPU**: < 50% utilization under normal load
- **Database Connections**: < 20 concurrent connections

## Testing Tools

### 1. Built-in Performance Script

```bash
# Run basic performance tests
php performance_test.php
```

This script tests:
- Subscription query performance
- Feature access check performance
- Webhook processing simulation
- Database index performance
- Concurrent request handling
- Memory usage

### 2. Apache Bench (ab)

Install Apache Bench:
```bash
# Ubuntu/Debian
sudo apt-get install apache2-utils

# macOS
brew install httpd
```

Test endpoint performance:
```bash
# Test pricing page (10 concurrent users, 100 requests)
ab -n 100 -c 10 https://staging.yourapp.com/pricing

# Test subscription status endpoint
ab -n 100 -c 10 -H "Authorization: Bearer YOUR_TOKEN" \
   https://staging.yourapp.com/api/subscription/status
```

### 3. wrk (HTTP Benchmarking Tool)

Install wrk:
```bash
# Ubuntu/Debian
sudo apt-get install wrk

# macOS
brew install wrk
```

Run load tests:
```bash
# Test with 10 threads, 50 connections for 30 seconds
wrk -t10 -c50 -d30s https://staging.yourapp.com/pricing

# Test webhook endpoint
wrk -t5 -c20 -d30s -s webhook_test.lua \
    https://staging.yourapp.com/api/webhooks/midtrans
```

Example `webhook_test.lua`:
```lua
wrk.method = "POST"
wrk.headers["Content-Type"] = "application/json"
wrk.body = '{"transaction_status":"settlement","order_id":"test_123"}'
```

### 4. Laravel Telescope

Enable Telescope for detailed performance monitoring:

```bash
# Install Telescope (if not already installed)
composer require laravel/telescope --dev

# Publish configuration
php artisan telescope:install

# Run migrations
php artisan migrate
```

Access Telescope dashboard:
```
https://staging.yourapp.com/telescope
```

Monitor:
- Request duration
- Database queries
- Queue jobs
- Cache hits/misses
- Memory usage

## Performance Test Scenarios

### Scenario 1: Normal Load

**Objective**: Verify system performs well under normal conditions

**Test Steps**:
1. Simulate 10 concurrent users
2. Each user performs:
   - View pricing page
   - Create checkout session
   - View subscription status
3. Run for 5 minutes
4. Measure response times

**Expected Results**:
- All requests complete successfully
- Average response time < 2 seconds
- No errors or timeouts

**Commands**:
```bash
# Test pricing page
ab -n 500 -c 10 https://staging.yourapp.com/pricing

# Test subscription status
ab -n 500 -c 10 -H "Authorization: Bearer TOKEN" \
   https://staging.yourapp.com/api/subscription/status
```

### Scenario 2: Peak Load

**Objective**: Verify system handles peak traffic

**Test Steps**:
1. Simulate 50 concurrent users
2. Run for 10 minutes
3. Monitor system resources
4. Check for errors

**Expected Results**:
- System remains responsive
- Response time < 5 seconds
- Error rate < 1%
- CPU usage < 80%

**Commands**:
```bash
wrk -t20 -c50 -d10m https://staging.yourapp.com/pricing
```

### Scenario 3: Webhook Burst

**Objective**: Verify webhook handling under high load

**Test Steps**:
1. Send 100 webhook requests simultaneously
2. Verify all webhooks processed
3. Check processing times
4. Verify data consistency

**Expected Results**:
- All webhooks processed successfully
- Average processing time < 5 seconds
- No duplicate processing
- Database remains consistent

**Commands**:
```bash
# Send multiple webhook requests
for i in {1..100}; do
  curl -X POST https://staging.yourapp.com/api/webhooks/midtrans \
    -H "Content-Type: application/json" \
    -d "{\"order_id\":\"test_$i\",\"transaction_status\":\"settlement\"}" &
done
wait
```

### Scenario 4: Database Performance

**Objective**: Verify database queries are optimized

**Test Steps**:
1. Run performance test script
2. Check query execution times
3. Verify indexes are used
4. Check for N+1 queries

**Expected Results**:
- Indexed queries < 50ms
- No N+1 query problems
- Connection pool not exhausted

**Commands**:
```bash
php performance_test.php

# Check slow queries in MySQL
mysql -u username -p -e "
  SELECT * FROM mysql.slow_log 
  WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
  ORDER BY query_time DESC 
  LIMIT 10;
"
```

### Scenario 5: Memory Leak Detection

**Objective**: Verify no memory leaks exist

**Test Steps**:
1. Monitor memory usage over time
2. Run continuous load for 1 hour
3. Check memory growth
4. Verify garbage collection

**Expected Results**:
- Memory usage remains stable
- No continuous growth
- Memory released after requests

**Commands**:
```bash
# Monitor memory usage
watch -n 5 'ps aux | grep php-fpm | awk "{sum+=\$6} END {print sum/1024 \" MB\"}"'

# Run continuous load
wrk -t10 -c20 -d1h https://staging.yourapp.com/pricing
```

## Monitoring During Tests

### System Metrics

Monitor these metrics during performance tests:

```bash
# CPU usage
top -b -n 1 | grep "Cpu(s)"

# Memory usage
free -h

# Disk I/O
iostat -x 1

# Network traffic
iftop

# PHP-FPM status
curl http://localhost/php-fpm-status

# Database connections
mysql -u username -p -e "SHOW PROCESSLIST;"
```

### Application Metrics

Monitor in Laravel logs:

```bash
# Watch application logs
tail -f storage/logs/laravel.log

# Watch web server logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# Watch queue workers
php artisan queue:work --verbose
```

### Database Metrics

```sql
-- Check slow queries
SELECT * FROM mysql.slow_log 
WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY query_time DESC;

-- Check table sizes
SELECT 
  table_name,
  ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES
WHERE table_schema = 'your_database'
ORDER BY (data_length + index_length) DESC;

-- Check index usage
SHOW INDEX FROM subscriptions;
```

## Performance Optimization Tips

### 1. Database Optimization

```php
// Use indexes for frequently queried columns
Schema::table('subscriptions', function (Blueprint $table) {
    $table->index('midtrans_subscription_id');
    $table->index('provider');
    $table->index(['user_id', 'status']);
});

// Use eager loading to prevent N+1 queries
$users = User::with('subscription')->get();

// Use select to limit columns
$subscriptions = Subscription::select('id', 'user_id', 'status')->get();
```

### 2. Caching

```php
// Cache subscription status
$status = Cache::remember("user.{$userId}.subscription", 300, function () use ($user) {
    return $this->subscriptionService->getUserSubscriptionStatus($user);
});

// Cache plans configuration
$plans = Cache::rememberForever('subscription.plans', function () {
    return config('subscription.plans');
});
```

### 3. Queue Processing

```php
// Process webhooks asynchronously
ProcessMidtransWebhook::dispatch($payload)->onQueue('webhooks');

// Use queue workers
php artisan queue:work --queue=webhooks,default --tries=3
```

### 4. Response Optimization

```php
// Use HTTP caching headers
return response()->json($data)
    ->header('Cache-Control', 'public, max-age=300');

// Compress responses
// Enable gzip in nginx/apache configuration
```

## Performance Benchmarks

### Baseline Metrics (Target)

| Metric | Target | Acceptable | Poor |
|--------|--------|------------|------|
| Pricing Page Load | < 1s | < 2s | > 2s |
| Checkout Creation | < 2s | < 3s | > 3s |
| Webhook Processing | < 3s | < 5s | > 5s |
| Subscription Query | < 50ms | < 100ms | > 100ms |
| Feature Check | < 20ms | < 50ms | > 50ms |
| Memory per Request | < 64MB | < 128MB | > 128MB |
| Concurrent Users | 50+ | 20-50 | < 20 |

### Sample Results Template

```markdown
## Performance Test Results

**Date**: 2026-01-24
**Environment**: Staging
**Load**: 50 concurrent users

### Response Times
- Pricing Page: 850ms (avg), 1.2s (p95), 1.5s (p99)
- Checkout Creation: 1.8s (avg), 2.5s (p95), 3.0s (p99)
- Webhook Processing: 2.1s (avg), 3.8s (p95), 4.5s (p99)

### Throughput
- Requests per second: 45
- Webhooks per minute: 120
- Error rate: 0.2%

### Resource Usage
- CPU: 45% average, 70% peak
- Memory: 85MB average, 120MB peak
- Database connections: 12 average, 18 peak

### Issues Found
- None

### Recommendations
- All metrics within acceptable range
- Ready for production deployment
```

## Troubleshooting Performance Issues

### Issue: Slow Database Queries

**Diagnosis**:
```bash
# Enable slow query log
mysql -u root -p -e "SET GLOBAL slow_query_log = 'ON';"
mysql -u root -p -e "SET GLOBAL long_query_time = 1;"

# Check slow queries
tail -f /var/log/mysql/slow.log
```

**Solutions**:
- Add missing indexes
- Optimize query structure
- Use query caching
- Consider read replicas

### Issue: High Memory Usage

**Diagnosis**:
```bash
# Check PHP memory limit
php -i | grep memory_limit

# Monitor memory usage
watch -n 1 'ps aux | grep php-fpm'
```

**Solutions**:
- Increase PHP memory limit
- Optimize query results
- Use pagination
- Clear unused variables

### Issue: Slow Webhook Processing

**Diagnosis**:
```bash
# Check queue workers
php artisan queue:work --verbose

# Monitor queue depth
php artisan queue:monitor
```

**Solutions**:
- Use queue workers
- Increase worker count
- Optimize webhook logic
- Add caching

## Continuous Performance Monitoring

### Setup Monitoring Tools

1. **Laravel Telescope** (Development/Staging)
2. **New Relic** or **Datadog** (Production)
3. **Prometheus + Grafana** (Custom metrics)
4. **CloudWatch** (AWS environments)

### Key Metrics to Track

- Response time percentiles (p50, p95, p99)
- Error rates
- Throughput (requests per second)
- Database query times
- Queue depth and processing time
- Memory and CPU usage
- Cache hit rates

### Alerting Thresholds

Set up alerts for:
- Response time > 5 seconds
- Error rate > 1%
- CPU usage > 80%
- Memory usage > 90%
- Queue depth > 1000 jobs
- Database connections > 80% of pool

## Related Documentation

- [Staging Deployment Guide](MIDTRANS_STAGING_DEPLOYMENT.md)
- [Monitoring Setup](../config/monitoring.php)
- [Database Optimization](DATABASE_OPTIMIZATION.md)
