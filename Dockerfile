# ============================================================================
# Thesis Logbook Management — PHP-FPM image (source DI-BAKE ke image, bukan bind mount)
# ============================================================================
# Stage 1: build aset frontend (React viewer) dengan Node
FROM node:20-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund || npm install --no-audit --no-fund
# Fix #1: COPY file & direktori dipisah agar tidak ambigu.
COPY vite.config.js ./
COPY resources ./resources
COPY public/pdfjs ./public/pdfjs
RUN npm run build

# Stage 2: runtime PHP-FPM
# Fix #2: naikkan ke 8.4 agar cocok dengan symfony/* di composer.lock (butuh >=8.4.1).
FROM php:8.4-fpm-alpine

# --- system deps ---
RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    libpng-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    zlib-dev \
    oniguruma-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        opcache \
        gd \
        intl \
        zip \
        pcntl \
        exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# --- composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Install PHP deps (cache layer by copying manifests first)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --no-dev --prefer-dist --no-progress

# Copy seluruh source (BAKE ke image)
COPY . .

# Ambil aset frontend yang sudah di-build dari stage assets
COPY --from=assets /build/public/build ./public/build

# Fix #6: simpan salinan public/ bawaan di /public-dist.
# Named volume /var/www/public (dibagi ke nginx) akan diisi dari sini
# oleh docker-entrypoint.sh bila volume masih kosong.
RUN mkdir -p /public-dist \
    && cp -a public/. /public-dist/ \
    && rm -rf public/storage

# Finalize autoload + assets
RUN composer install --no-interaction --no-scripts --no-dev --optimize-autoloader --no-progress \
    && mkdir -p storage/framework/{sessions,views,cache} storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rw storage bootstrap/cache

# PHP config: upload 10MB, post 12MB
RUN { \
    echo 'upload_max_filesize=10M'; \
    echo 'post_max_size=12M'; \
    echo 'memory_limit=256M'; \
    echo 'date.timezone=Asia/Jakarta'; \
  } > "$PHP_INI_DIR/conf.d/lbta.ini"

# Fix #6: entrypoint menyalin public/ ke named volume bersama (nginx).
# Jika volume kosong, isi dari file yang di-bake agar aset & symlink tersedia.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

CMD ["php-fpm"]
ENTRYPOINT ["docker-entrypoint.sh"]
