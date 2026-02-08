# Midtrans Subscription - Staging Deployment Guide

## Overview
This guide provides step-by-step instructions for deploying the Midtrans subscription feature to staging environment.

## Pre-Deployment Checklist

### Code Readiness
- [ ] All code changes committed to version control
- [ ] Code reviewed and approved
- [ ] All merge conflicts resolved
- [ ] Branch is up to date with main/master

### Environment Preparation
- [ ] Staging server access verified
- [ ] Database backup completed
- [ ] Environment variables prepared
- [ ] Midtrans sandbox account configured

## Deployment Steps

### Step 1: Prepare Deployment Package

```bash
# On local machine
git checkout main
git pull origin main
git tag -a v1.0.0-midtrans-staging -m "Midtrans subscription staging deployment"
git push origin v1.0.0-midtrans-staging
```

### Step 2: Deploy to Staging Server

#### Option A: Manual Deployment

```bash
# SSH to staging server
ssh user@staging-server

# Navigate to application directory
cd /var/www/your-app

# Pull latest changes
git fetch --all --tags
git checkout v1.0.0-midtrans-staging

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

#### Option B: Automated Deployment (if using CI/CD)

```yaml
# Example GitHub Actions workflow
name: Deploy to Staging
on:
  push:
    tags:
      - 'v*-staging'

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Deploy to staging
        run: |
          # Your deployment script here
```

### Step 3: Run Database Migrations

```bash
# On staging server
php artisan migrate --force

# Verify migrations
php artisan migrate:status
```

### Step 4: Configure Environment Variables

```bash
# Edit .env file on staging server
nano .env

# Add/Update Midtrans configuration
MIDTRANS_SERVER_KEY=SB-Mid-server-your_sandbox_key
MIDTRANS_CLIENT_KEY=SB-Mid-client-your_sandbox_key
MIDTRANS_IS_PRODUCTION=false

# Subscription URLs
MIDTRANS_SUBSCRIPTION_SUCCESS_URL=https://staging.yourapp.com/subscription/success
MIDTRANS_SUBSCRIPTION_CANCEL_URL=https://staging.yourapp.com/subscription/cancel
MIDTRANS_SUBSCRIPTION_ERROR_URL=https://staging.yourapp.com/subscription/error

# Reload configuration
php artisan config:cache
```

### Step 5: Restart Services

```bash
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Restart queue workers
php artisan queue:restart

# Restart web server (if needed)
sudo systemctl restart nginx
# OR
sudo systemctl restart apache2
```

### Step 6: Verify Deployment

```bash
# Check application status
php artisan about

# Check queue workers
php artisan queue:work --once

# Check logs
tail -f storage/logs/laravel.log
```

## Post-Deployment Verification

### Health Checks
- [ ] Application is accessible
- [ ] No errors in logs
- [ ] Database connection working
- [ ] Queue workers running
- [ ] Cache working properly

### Feature Verification
- [ ] Pricing page loads correctly
- [ ] Subscription checkout flow works
- [ ] Webhook endpoint is accessible
- [ ] Configuration values are correct

## Rollback Procedure

If issues are encountered:

```bash
# Revert to previous version
git checkout previous-stable-tag

# Rollback migrations (if needed)
php artisan migrate:rollback --step=1

# Clear caches
php artisan config:clear
php artisan cache:clear

# Restart services
sudo systemctl restart php8.2-fpm
php artisan queue:restart
```

## Monitoring

### Log Files to Monitor
- `storage/logs/laravel.log` - Application logs
- `/var/log/nginx/error.log` - Web server errors
- `/var/log/php8.2-fpm.log` - PHP-FPM errors

### Metrics to Track
- Response times
- Error rates
- Subscription creation success rate
- Webhook processing time

## Troubleshooting

### Common Issues

#### Issue: 500 Internal Server Error
**Solution**: Check PHP error logs and Laravel logs

```bash
tail -f storage/logs/laravel.log
tail -f /var/log/php8.2-fpm.log
```

#### Issue: Configuration not loading
**Solution**: Clear and rebuild cache

```bash
php artisan config:clear
php artisan config:cache
```

#### Issue: Queue workers not processing
**Solution**: Restart queue workers

```bash
php artisan queue:restart
php artisan queue:work --daemon
```

#### Issue: Permissions errors
**Solution**: Fix file permissions

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## Support Contacts

- DevOps Team: devops@yourcompany.com
- Backend Team: backend@yourcompany.com
- Emergency Hotline: +62-xxx-xxxx-xxxx

## Deployment Log

| Date | Version | Deployed By | Status | Notes |
|------|---------|-------------|--------|-------|
| 2026-01-24 | v1.0.0-staging | - | Pending | Initial Midtrans deployment |

## Next Steps

After successful staging deployment:
1. Run comprehensive testing (see tasks 18.4-18.7)
2. Monitor for 24-48 hours
3. Address any issues found
4. Prepare for production deployment
