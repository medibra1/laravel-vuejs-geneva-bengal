FROM php:8.3-fpm

# libjpeg-dev + `docker-php-ext-configure gd --with-jpeg` are both required:
# building gd without them yields an extension that reports "enabled" but
# has no imagejpeg()/imagecreatefromjpeg(), so every JPEG conversion
# medialibrary generates for uploaded cat photos fails at runtime. Same
# reasoning for libwebp-dev + --with-webp: without it, imagewebp() is
# undefined and any medialibrary conversion targeting webp (see
# HomeGallerySeeder) fails at runtime instead of at build time.
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libonig-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
    && docker-php-ext-install pdo_mysql gd zip mbstring exif \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
