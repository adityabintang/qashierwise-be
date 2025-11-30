#!/bin/sh
set -e

cd /var/www/html

# Fix permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Clear and cache config
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations (optional - comment out if you don't want auto-migrate)
# php artisan migrate --force

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisord.conf
