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

# Correr migraciones primero para que la tabla cache exista antes de limpiarla
echo "Corriendo migraciones de base de datos..."
php artisan config:clear || true
php artisan migrate --force
php artisan db:seed --force

# Publicar assets de Filament (requiere APP_KEY disponible, por eso va aqui)
php artisan filament:upgrade || true
php artisan storage:link || true

# Limpiar y cachear configuraciones
echo "Cacheando configuraciones..."
php artisan optimize:clear || true
php artisan config:cache
php artisan route:cache || true
php artisan view:cache

echo "Setup complete. Booting server..."
# Pass control to CMD (apache2-foreground)
exec "$@"
