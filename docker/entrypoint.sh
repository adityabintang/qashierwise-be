#!/bin/sh
set -e

cd /var/www/html

echo "Starting entrypoint..."

# Fix permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Clear caches (don't cache for now to debug)
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Check if app key exists
echo "Checking APP_KEY..."
php artisan key:show || echo "No APP_KEY set!"

# Start supervisor
echo "Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
