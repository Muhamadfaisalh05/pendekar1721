# syntax=docker/dockerfile:1.7
# ---------------------------------------------------------------------------
# Image runtime aplikasi Pendekar (Laravel 13.40 / Filament 3 / Livewire 3).
#
# WAJIB PHP >= 8.4.1 — composer.lock memakai symfony/* 8.1.x (requires php >=8.4.1).
# Build & push: lihat docker/REBUILD-PHP84.md
# ---------------------------------------------------------------------------
FROM php:8.4-cli

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1

# Ekstensi yang dibutuhkan lock: ext-gd, ext-intl, ext-zip, ext-xml, ext-dom,
# ext-mbstring, ext-simplexml, ext-fileinfo, ext-filter, ext-zlib, pdo_sqlite.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        unzip; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" bcmath gd intl opcache pcntl zip; \
    php -m | grep -qi pdo_sqlite; \
    apt-get purge -y --auto-remove; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Gagal cepat bila base image ternyata < 8.4.1 (mis. tag PHP 8.3 terselip).
RUN php -r 'if (version_compare(PHP_VERSION, "8.4.1", "<")) { fwrite(STDERR, "FATAL: PHP ".PHP_VERSION." < 8.4.1\n"); exit(1); } echo "PHP ".PHP_VERSION." OK\n";'

WORKDIR /var/www/html

# Dependency di-copy lebih dulu supaya layer cache Composer efisien.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

# --no-scripts: hindari artisan dijalankan sebelum .env tersedia.
# package:discover cukup memakai APP_KEY dummy (tidak dipakai untuk dekripsi).
RUN composer dump-autoload --no-dev --optimize --no-scripts --no-interaction \
    && APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
       php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r '$s = @fsockopen("127.0.0.1", 8000); exit($s ? 0 : 1);'

# Produksi bisa memakai Octane (butuh ekstensi swoole / binary rr):
#   command: php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
