#!/bin/sh
# Dijalankan setiap kontainer start. Pengaturan (.env) datang dari environment kontainer, bukan dari image.
set -e
cd /var/www/html

# Perintah selain peran layanan (mis. key:generate, tinker) langsung dijalankan apa adanya.
case "$1 $2 $3" in
    "apache2-foreground  "|"php artisan queue:work"|"php artisan schedule:work") ;;
    *) exec runuser -u www-data -- "$@" ;;
esac

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY belum diisi. Buat dengan: docker run --rm <image> php artisan key:generate --show" >&2
    exit 1
fi

# Volume baru dimiliki root; pastikan www-data bisa menulis.
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Konfigurasi dikunci dari environment kontainer ini. Route tidak di-cache (ada route closure).
runuser -u www-data -- php artisan config:cache
runuser -u www-data -- php artisan view:cache || echo "peringatan: view:cache dilewati" >&2

# Hanya satu kontainer (web) yang boleh memigrasi, supaya tidak berebut.
if [ "$AUTO_MIGRATE" = "true" ] && [ "$1" = "apache2-foreground" ]; then
    runuser -u www-data -- php artisan migrate --force
fi

if [ "$1" = "apache2-foreground" ]; then
    exec "$@"
fi
exec runuser -u www-data -- "$@"
