FROM php:8.3-apache

# Actualizar sistema y aplicar parches de seguridad del SO
RUN apt-get update && apt-get upgrade -y && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP requeridas
RUN docker-php-ext-configure intl \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd intl zip

# Endurecimiento de seguridad PHP contra CVE-2026-109618 / CVE-2026-109619
RUN { \
    echo 'expose_php = Off'; \
    echo 'memory_limit = 256M'; \
    echo 'upload_max_filesize = 10M'; \
    echo 'post_max_size = 12M'; \
    echo 'max_execution_time = 60'; \
} > /usr/local/etc/php/conf.d/security-hardening.ini

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN a2enmod rewrite

# Copiar configuración de Apache
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Dependencias PHP
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Código fuente (sin dependencias Node/Vite innecesarias)
COPY . /var/www/html

RUN composer dump-autoload --optimize

# Permisos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
