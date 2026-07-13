#!/bin/sh
set -e

cd /var/www/html

if [ -f composer.json ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ -f .env ] && ! grep -qE '^APP_KEY=base64:' .env; then
    php artisan key:generate --force || true
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

if [ -n "$DB_HOST" ]; then
    echo "Waiting for PostgreSQL at $DB_HOST:${DB_PORT:-5432}..."
    i=0
    while [ $i -lt 60 ]; do
        if php -r "try { new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '5432') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Exception \$e) { exit(1); }" 2>/dev/null; then
            echo "PostgreSQL is ready."
            break
        fi
        i=$((i + 1))
        sleep 2
    done
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ] && [ -f artisan ]; then
    php artisan migrate --force --seed || php artisan migrate --force || true
fi

php artisan storage:link --force 2>/dev/null || true

exec docker-php-entrypoint "$@"