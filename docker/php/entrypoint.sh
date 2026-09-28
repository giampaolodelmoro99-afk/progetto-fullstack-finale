#!/bin/sh
set -e

cd /var/www/html

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

if [ ! -f .env ]; then
    echo "[entrypoint] Creo .env da .env.example"
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    echo "[entrypoint] Installo le dipendenze Composer"
    composer install --no-interaction --prefer-dist --no-progress
fi

if grep -Eq '^APP_KEY=\s*$' .env; then
    echo "[entrypoint] Genero APP_KEY"
    php artisan key:generate --force
fi

echo "[entrypoint] Eseguo le migration"
php artisan migrate --force

php artisan db:seed-if-empty

if [ ! -L public/storage ]; then
    echo "[entrypoint] Creo il link dello storage"
    php artisan storage:link
fi

echo "[entrypoint] Avvio il server su 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
