FROM php:8.2-fpm-alpine

# Add community repository and update package index
RUN echo "https://dl-cdn.alpinelinux.org/alpine/v3.19/main" > /etc/apk/repositories \
    && echo "https://dl-cdn.alpinelinux.org/alpine/v3.19/community" >> /etc/apk/repositories \
    && apk update --no-cache

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    oniguruma-dev \
    icu-dev \
    postgresql-dev

# Install Node.js and npm separately
RUN apk add --no-cache nodejs npm

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl

# Install Redis extension from source (avoiding PECL network issues)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && mkdir -p /tmp/redis \
    && curl -fsSL https://github.com/phpredis/phpredis/archive/refs/tags/6.0.2.tar.gz | tar xz -C /tmp/redis --strip-components=1 \
    && cd /tmp/redis \
    && phpize \
    && ./configure \
    && make -j$(nproc) \
    && make install \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy package files first for better caching
COPY package*.json ./

# Install Node dependencies with retry and timeout settings
RUN npm config set fetch-retry-mintimeout 20000 \
    && npm config set fetch-retry-maxtimeout 120000 \
    && npm config set fetch-retries 5 \
    && npm config set fetch-timeout 300000 \
    && npm install --prefer-offline --no-audit --progress=false

# Copy composer files for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application files
COPY . .

# Copy nginx config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Copy supervisor config
COPY docker/supervisord.conf /etc/supervisord.conf

# Build assets
RUN npm run build && rm -rf node_modules

# Run composer scripts after copying all files
RUN composer dump-autoload --optimize

# Create required directories
RUN mkdir -p /run/nginx \
    && mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/app/public

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear Laravel caches
RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

# Expose port
EXPOSE 80

# Start supervisor directly
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
