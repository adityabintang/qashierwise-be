# Migration Rollback Guide

## Overview
This guide provides instructions for rolling back Midtrans subscription migrations if issues are encountered during deployment.

## Pre-Rollback Checklist

- [ ] Backup current database state
- [ ] Document the issue requiring rollback
- [ ] Notify team members
- [ ] Verify rollback procedure

## Database Backup

### Create Backup

```bash
# MySQL/MariaDB
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql

# PostgreSQL
pg_dump -U username database_name > backup_$(date +%Y%m%d_%H%M%S).sql

# SQLite
cp database/database.sqlite database/database.sqlite.backup_$(date +%Y%m%d_%H%M%S)
```

## Rollback Procedure

### Step 1: Check Current Migration Status

```bash
php artisan migrate:status
```

Look for the Midtrans subscription migration:
- `2026_01_23_165210_add_midtrans_fields_to_subscriptions_table`

### Step 2: Rollback Migration

```bash
# Rollback the last migration batch
php artisan migrate:rollback

# OR rollback specific number of steps
php artisan migrate:rollback --step=1

# Verify rollback
php artisan migrate:status
```

### Step 3: Verify Database State

```bash
# Run verification script
php verify_migrations.php

# OR manually check
php artisan tinker
>>> Schema::hasColumn('subscriptions', 'midtrans_subscription_id')
>>> Schema::hasColumn('subscriptions', 'provider')
```

### Step 4: Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Step 5: Restart Services

```bash
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Restart queue workers
php artisan queue:restart

# Restart web server
sudo systemctl restart nginx
```

## Data Preservation

### Important Notes

1. **Existing Data**: The rollback will remove the new columns but preserve existing subscription data
2. **Polar Subscriptions**: All Polar subscriptions remain intact
3. **Midtrans Data**: Any Midtrans subscriptions created will lose their Midtrans-specific data

### Data Recovery

If you need to preserve Midtrans subscription data before rollback:

```bash
# Export Midtrans subscriptions
php artisan tinker
>>> $midtransSubscriptions = DB::table('subscriptions')
    ->whereNotNull('midtrans_subscription_id')
    ->get();
>>> file_put_contents('midtrans_subscriptions_backup.json', json_encode($midtransSubscriptions, JSON_PRETTY_PRINT));
```

## Re-Migration

If you need to re-apply the migration after fixing issues:

```bash
# Run migrations again
php artisan migrate

# Verify
php verify_migrations.php
```

## Troubleshooting

### Issue: Migration rollback fails

```bash
# Check for foreign key constraints
php artisan tinker
>>> DB::select('SHOW CREATE TABLE subscriptions');

# Disable foreign key checks temporarily (MySQL)
>>> DB::statement('SET FOREIGN_KEY_CHECKS=0');
>>> Artisan::call('migrate:rollback');
>>> DB::statement('SET FOREIGN_KEY_CHECKS=1');
```

### Issue: Data corruption after rollback

```bash
# Restore from backup
mysql -u username -p database_name < backup_file.sql

# OR for PostgreSQL
psql -U username database_name < backup_file.sql
```

### Issue: Application errors after rollback

```bash
# Clear all caches
php artisan optimize:clear

# Rebuild autoloader
composer dump-autoload

# Restart all services
sudo systemctl restart php8.2-fpm nginx
php artisan queue:restart
```

## Post-Rollback Verification

### Checklist

- [ ] Application is accessible
- [ ] No errors in logs
- [ ] Existing subscriptions still work
- [ ] Polar integration functional
- [ ] Queue workers running
- [ ] No database errors

### Test Commands

```bash
# Check application health
php artisan about

# Check for errors
tail -f storage/logs/laravel.log

# Test subscription access
php artisan tinker
>>> $user = User::first();
>>> $user->subscription;
>>> $user->hasActiveSubscription();
```

## Emergency Contacts

- Database Admin: dba@yourcompany.com
- DevOps Team: devops@yourcompany.com
- Backend Lead: backend-lead@yourcompany.com

## Incident Report Template

After rollback, document the incident:

```markdown
## Rollback Incident Report

**Date**: YYYY-MM-DD HH:MM
**Environment**: Staging/Production
**Performed By**: [Name]

### Reason for Rollback
[Describe the issue that required rollback]

### Actions Taken
1. [List all actions performed]
2. ...

### Data Impact
- Subscriptions affected: [number]
- Data lost: [description]
- Data preserved: [description]

### Root Cause
[Analysis of what went wrong]

### Prevention Measures
[Steps to prevent similar issues]

### Next Steps
[Plan for re-deployment]
```

## Best Practices

1. **Always backup** before rollback
2. **Test rollback** in development first
3. **Document everything** during the process
4. **Communicate** with team members
5. **Monitor closely** after rollback
6. **Plan re-deployment** carefully

## Related Documentation

- [Staging Deployment Guide](MIDTRANS_STAGING_DEPLOYMENT.md)
- [Production Deployment Guide](MIDTRANS_PRODUCTION_ENV_SETUP.md)
- [Migration Guide](MIGRATION_GUIDE.md)
