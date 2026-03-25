#!/bin/bash
set -e

echo "Starting deployment setup..."

# Adjust permissions for Laravel
if [ -d "/var/www/html/storage" ]; then
    chown -R www-data:www-data /var/www/html/storage
    chmod -R 775 /var/www/html/storage
fi

if [ -d "/var/www/html/bootstrap/cache" ]; then
    chown -R www-data:www-data /var/www/html/bootstrap/cache
    chmod -R 775 /var/www/html/bootstrap/cache
fi

# Clear caches and optimize
echo "Clearing and caching configurations..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache || true
php artisan view:cache

# Run database migrations automatically on deploy
echo "Running database migrations..."
php artisan migrate --force

echo "Setup complete. Booting server..."
# Pass control to CMD (apache2-foreground)
exec "$@"
