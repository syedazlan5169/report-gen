#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

composer_lock_hash="$(sha256sum composer.lock | awk '{print $1}')"
composer_marker="vendor/.composer-lock-hash"
if [ ! -f "$composer_marker" ] || [ "$(cat "$composer_marker")" != "$composer_lock_hash" ]; then
    composer install --no-interaction
    printf '%s\n' "$composer_lock_hash" > "$composer_marker"
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -Eq '^APP_KEY=.+$' .env; then
    php artisan key:generate --force --no-interaction
fi

rm -rf public/storage
ln -s ../storage/app/public public/storage

php artisan migrate --no-interaction

exec "$@"
