# Staging Environment Checklist

## Overview
This checklist ensures that the staging environment is properly configured for Midtrans subscription testing.

## Pre-Deployment Configuration

### 1. Server Requirements
- [ ] PHP 8.1 or higher installed
- [ ] Composer installed
- [ ] Node.js and NPM installed
- [ ] Database server running (MySQL/PostgreSQL/SQLite)
- [ ] Redis server running (for queues and cache)
- [ ] Web server configured (Nginx/Apache)
- [ ] SSL certificate installed (HTTPS required for webhooks)

### 2. Application Configuration
- [ ] `.env` file created from `.env.example`
- [ ] `APP_ENV` set to `staging`
- [ ] `APP_DEBUG` set to `true` (for staging)
- [ ] `APP_URL` set to staging domain
- [ ] Database credentials configured
- [ ] Redis credentials configured

### 3. Midtrans Configuration
- [ ] Midtrans sandbox account created
- [ ] Server key obtained from Midtrans dashboard
- [ ] Client key obtained from Midtrans dashboard
- [ ] `MIDTRANS_SERVER_KEY` set in `.env`
- [ ] `MIDTRANS_CLIENT_KEY` set in `.env`
- [ ] `MIDTRANS_IS_PRODUCTION` set to `false`

### 4. Subscription URLs
- [ ] `MIDTRANS_SUBSCRIPTION_SUCCESS_URL` configured
- [ ] `MIDTRANS_SUBSCRIPTION_CANCEL_URL` configured
- [ ] `MIDTRANS_SUBSCRIPTION_ERROR_URL` configured
- [ ] All URLs use HTTPS protocol
- [ ] All URLs point to staging domain

### 5. Webhook Configuration
- [ ] Webhook endpoint accessible: `/api/webhooks/midtrans`
- [ ] Webhook URL configured in Midtrans dashboard
- [ ] Webhook URL uses HTTPS
- [ ] CSRF protection disabled for webhook endpoint
- [ ] Rate limiting configured for webhook endpoint

## Deployment Verification

### 6. Code Deployment
- [ ] Latest code pulled from repository
- [ ] Correct branch/tag checked out
- [ ] Dependencies installed (`composer install`)
- [ ] Frontend assets built (`npm run build`)
- [ ] File permissions set correctly
- [ ] Storage directories writable

### 7. Database Migration
- [ ] Database backup created
- [ ] Migrations run successfully (`php artisan migrate`)
- [ ] Migration status verified (`php artisan migrate:status`)
- [ ] New columns exist in subscriptions table
- [ ] Indexes created successfully
- [ ] Verification script passed (`php verify_migrations.php`)

### 8. Configuration Verification
- [ ] Config cache cleared (`php artisan config:clear`)
- [ ] Config cached (`php artisan config:cache`)
- [ ] Configuration script passed (`php verify_config.php`)
- [ ] All environment variables loaded correctly
- [ ] Subscription plans configured
- [ ] Routes registered correctly

### 9. Services Verification
- [ ] PHP-FPM running
- [ ] Web server running
- [ ] Queue workers running
- [ ] Redis server accessible
- [ ] Database server accessible
- [ ] Application accessible via browser

## Functional Testing

### 10. Basic Application Tests
- [ ] Homepage loads without errors
- [ ] Login functionality works
- [ ] Dashboard accessible
- [ ] No errors in Laravel logs
- [ ] No errors in web server logs

### 11. Subscription Feature Tests
- [ ] Pricing page loads (`/pricing`)
- [ ] Subscription plans display correctly
- [ ] Subscribe button works
- [ ] Checkout flow initiates
- [ ] Midtrans payment page loads
- [ ] Success callback works
- [ ] Cancel callback works
- [ ] Error callback works

### 12. Webhook Tests
- [ ] Webhook endpoint responds (200 OK)
- [ ] Webhook signature validation works
- [ ] Payment notification processed
- [ ] Recurring notification processed
- [ ] Subscription status updates correctly
- [ ] Webhook logs created

### 13. Backward Compatibility Tests
- [ ] Existing Polar subscriptions still work
- [ ] Polar webhook still processes
- [ ] Users with Polar subscriptions can access features
- [ ] No errors for existing users

## Performance Testing

### 14. Response Time Tests
- [ ] Homepage loads in < 2 seconds
- [ ] Pricing page loads in < 2 seconds
- [ ] Checkout creation in < 3 seconds
- [ ] Webhook processing in < 5 seconds
- [ ] Dashboard loads in < 2 seconds

### 15. Load Tests
- [ ] Application handles 10 concurrent users
- [ ] Application handles 50 concurrent users
- [ ] Webhook handles multiple simultaneous requests
- [ ] Queue processes jobs without delays
- [ ] Database queries optimized

## Security Testing

### 16. Authentication & Authorization
- [ ] Unauthenticated users cannot access subscription pages
- [ ] Users can only manage their own subscriptions
- [ ] Admin routes properly protected
- [ ] Session management works correctly

### 17. Webhook Security
- [ ] Invalid signatures rejected
- [ ] Malformed payloads handled gracefully
- [ ] Rate limiting prevents abuse
- [ ] Sensitive data not logged
- [ ] HTTPS enforced

### 18. Data Security
- [ ] Credentials stored in environment variables
- [ ] No credentials in logs
- [ ] No credentials in error messages
- [ ] Database connections encrypted
- [ ] API keys masked in responses

## Monitoring Setup

### 19. Logging
- [ ] Application logs configured
- [ ] Webhook events logged
- [ ] Error logs monitored
- [ ] Log rotation configured
- [ ] Log retention policy set

### 20. Alerting
- [ ] Error rate monitoring configured
- [ ] Performance monitoring configured
- [ ] Webhook failure alerts set up
- [ ] Database monitoring configured
- [ ] Disk space monitoring configured

### 21. Metrics
- [ ] Subscription creation rate tracked
- [ ] Webhook processing time tracked
- [ ] Error rates tracked
- [ ] Response times tracked
- [ ] Queue depth monitored

## Documentation

### 22. Technical Documentation
- [ ] Deployment guide reviewed
- [ ] Configuration guide reviewed
- [ ] API documentation updated
- [ ] Webhook documentation updated
- [ ] Troubleshooting guide available

### 23. Operational Documentation
- [ ] Runbook created
- [ ] Rollback procedure documented
- [ ] Emergency contacts listed
- [ ] Escalation path defined
- [ ] On-call schedule set

## Sign-off

### 24. Team Approval
- [ ] Backend team approved
- [ ] DevOps team approved
- [ ] QA team approved
- [ ] Product owner approved
- [ ] Security team approved (if applicable)

### 25. Final Checks
- [ ] All checklist items completed
- [ ] Known issues documented
- [ ] Rollback plan ready
- [ ] Production deployment plan ready
- [ ] Monitoring dashboard accessible

## Verification Commands

Run these commands to verify the staging environment:

```bash
# Check application status
php artisan about

# Verify migrations
php verify_migrations.php

# Verify configuration
php verify_config.php

# Check routes
php artisan route:list | grep subscription

# Check queue workers
php artisan queue:work --once

# Check logs
tail -f storage/logs/laravel.log

# Test webhook endpoint
curl -X POST https://staging.yourapp.com/api/webhooks/midtrans \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

## Issue Tracking

| Issue | Severity | Status | Assigned To | Notes |
|-------|----------|--------|-------------|-------|
| - | - | - | - | - |

## Deployment Sign-off

**Deployed By**: ___________________  
**Date**: ___________________  
**Time**: ___________________  
**Version**: ___________________  

**Approved By**:
- Backend Lead: ___________________
- DevOps Lead: ___________________
- QA Lead: ___________________

**Notes**:
_______________________________________________
_______________________________________________
_______________________________________________
