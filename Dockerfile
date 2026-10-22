# syntax=docker/dockerfile:1
#
# Dockerfile MANDIRI QR Code AKP: konteks build = folder repositori ini saja.
#
#   docker build -t ghcr.io/eddyyucca/akp-qr:latest .
#
# Satu image bisa menjalankan beberapa peran lewat perintah (CMD): web (bawaan).
FROM php:8.3-apache

# gd = gambar QR (PNG); pdo_mysql dipakai Laravel; mysqli ikut dipasang untuk alat/skrip yang memakainya.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev unzip curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli gd zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache melayani folder public/ dan memakai .htaccess Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && { echo 'ServerName localhost'; echo 'ServerTokens Prod'; echo 'ServerSignature Off'; } > /etc/apache2/conf-available/akp.conf \
    && a2enconf akp

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-akp.ini

WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_MIRROR_PATH_REPOS=1

COPY . /var/www/html/

# Unduhan paket kadang terputus: ulangi sampai 4 kali.
RUN ok=0; for i in 1 2 3 4; do composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts && ok=1 && break; echo "composer install gagal (percobaan $i dari 4), mengulang..." >&2; sleep 5; done; [ "$ok" = 1 ] \
    && php artisan package:discover --ansi \
    && rm -rf /root/.composer /root/.cache \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Dipasang paling akhir supaya perubahan skrip tidak mengulang langkah di atas.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/[[:cntrl:]]$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 CMD curl -fsS http://localhost/up || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
