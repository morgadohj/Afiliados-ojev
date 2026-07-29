#!/bin/sh
set -eu

if [ -s /run/app-secrets/app_key ]; then
    APP_KEY="$(cat /run/app-secrets/app_key)"
    export APP_KEY
fi

if [ -s /run/app-secrets/db_password ]; then
    DB_PASSWORD="$(cat /run/app-secrets/db_password)"
    export DB_PASSWORD
fi

mkdir -p \
    bootstrap/cache \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data bootstrap/cache storage

wait_for_database() {
    attempts=0

    until php -r '
        try {
            new PDO(
                sprintf(
                    "pgsql:host=%s;port=%s;dbname=%s",
                    getenv("DB_HOST"),
                    getenv("DB_PORT"),
                    getenv("DB_DATABASE")
                ),
                getenv("DB_USERNAME"),
                getenv("DB_PASSWORD")
            );
        } catch (Throwable $exception) {
            exit(1);
        }
    '; do
        attempts=$((attempts + 1))

        if [ "$attempts" -ge 30 ]; then
            echo "No fue posible conectar con PostgreSQL." >&2
            exit 1
        fi

        sleep 2
    done
}

case "${1:-web}" in
    web)
        wait_for_database
        php artisan migrate --force
        php artisan optimize
        chown -R www-data:www-data bootstrap/cache storage
        php-fpm -D
        exec nginx -g 'daemon off;'
        ;;
    queue)
        wait_for_database
        php artisan config:cache
        chown -R www-data:www-data bootstrap/cache storage
        exec su -s /bin/sh www-data -c \
            'php artisan queue:work redis --sleep=3 --tries=3 --timeout=120 --max-time=3600'
        ;;
    scheduler)
        wait_for_database
        php artisan config:cache
        chown -R www-data:www-data bootstrap/cache storage
        exec su -s /bin/sh www-data -c 'php artisan schedule:work'
        ;;
    *)
        exec "$@"
        ;;
esac

