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

# Reinstalls also when package-lock.json changed since the last install
# (after a git pull): the hash of the installed lock file is kept inside
# node_modules/.
lock_hash=$(sha256sum package-lock.json 2>/dev/null | cut -d' ' -f1)
if [ ! -d node_modules ] || [ "$(cat node_modules/.package-lock.sha256 2>/dev/null)" != "$lock_hash" ]; then
    echo "[entrypoint] Installo le dipendenze npm"
    if [ -f package-lock.json ]; then
        npm ci
    else
        npm install
    fi
    echo "$lock_hash" > node_modules/.package-lock.sha256
fi

echo "[entrypoint] Avvio Vite su 0.0.0.0:5173"
exec npm run dev -- --host 0.0.0.0 --port 5173
