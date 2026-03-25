#!/usr/bin/env bash
# Exit on error
set -o errexit

# Install dependencies
composer install --no-dev --optimize-autoloader

# Clear configuration caches
php artisan optimize:clear

# Cache configuration for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
# Force is required to run migrations in production
php artisan migrate --force
