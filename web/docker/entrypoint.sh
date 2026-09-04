#!/bin/sh
set -e

cd /var/www

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

if [ ! -L public/storage ]; then
    php artisan storage:link --force >/dev/null 2>&1 || true
fi

exec docker-php-entrypoint "$@"
