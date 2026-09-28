#!/bin/sh
set -e

cd /app

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

if [ ! -f .env ]; then
    echo "[entrypoint] Creo .env da .env.example"
    cp .env.example .env
fi

if [ ! -d node_modules ]; then
    echo "[entrypoint] Installo le dipendenze npm"
    if [ -f package-lock.json ]; then
        npm ci
    else
        npm install
    fi
fi

echo "[entrypoint] Avvio Vite su 0.0.0.0:5173"
exec npm run dev -- --host 0.0.0.0 --port 5173
