FROM php:8.3-fpm

# libjpeg-dev + `docker-php-ext-configure gd --with-jpeg` are both required:
# building gd without them yields an extension that reports "enabled" but
# has no imagejpeg()/imagecreatefromjpeg(), so every JPEG conversion
# medialibrary generates for uploaded cat photos fails at runtime.
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql gd zip mbstring exif \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
