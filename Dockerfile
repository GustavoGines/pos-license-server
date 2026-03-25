FROM php:8.3-apache

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
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
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP
RUN docker-php-ext-configure intl \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd intl zip

# Permitir a Composer correr como root
ENV COMPOSER_ALLOW_SUPERUSER=1

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Copiar la configuración limpia de Apache (apunta a /public de Laravel)
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Directorio de trabajo
WORKDIR /var/www/html

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar archivos de composer primero para aprovechar caché de Docker
COPY composer.json composer.lock ./

# Instalar dependencias Laravel
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copiar archivos de Node
COPY package.json package-lock.json* ./

# Instalar dependencias Node
RUN npm install

# Copiar el resto de la aplicación
COPY . /var/www/html

# Re-correr composer dump-autoload con scripts
RUN composer dump-autoload --optimize

# Compilar assets (Vite + Tailwind + Filament CSS)
RUN npm run build

# Establecer permisos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# Copiar y dar permisos al script de entrada
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
