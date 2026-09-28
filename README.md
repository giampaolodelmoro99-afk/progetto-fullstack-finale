# E-commerce

Progetto d'esame ITS Full Stack Developer: e-commerce completo con API REST in Laravel e SPA in React.

| Cartella | Contenuto |
|---|---|
| `backend/` | Laravel 13, API REST headless con autenticazione Sanctum SPA a cookie |
| `frontend/` | React con Vite, React Router, TanStack Query e MUI |
| `docker/` | Dockerfile, script di avvio e configurazione dei servizi |
| `design/` | export di Claude Design e riferimenti visivi |
| `docs/` | schema ER, decisioni architetturali, note per la presentazione |

## Requisiti

- Docker con Docker Compose v2
- `make` (facoltativo, per i comandi abbreviati)

Sull'host non servono PHP, Composer né Node: tutto gira nei container.

## Avvio

```sh
docker compose up
```

Al primo avvio i container, senza altri passaggi:

- creano i file `.env` di backend e frontend dagli `.env.example`
- installano le dipendenze Composer e npm
- generano `APP_KEY`
- eseguono le migration
- popolano il database con i dati demo (solo se è vuoto)
- creano il link dello storage

Il primo avvio richiede qualche minuto per scaricare le dipendenze; i successivi sono immediati.

Il file `.env` alla radice è facoltativo: serve solo a cambiare UID, GID o porte. Per crearlo:

```sh
cp .env.example .env
```

## Servizi e porte

| Servizio | Ruolo | Indirizzo |
|---|---|---|
| app | API Laravel (`php artisan serve`) | http://localhost:8000 |
| node | frontend React (Vite) | http://localhost:5173 |
| mysql | MySQL 8.4, database `ecommerce` ed `ecommerce_testing` | `localhost:3307` dall'host, `mysql:3306` dai container |
| mailpit | cattura delle email (SMTP sulla 1025) | http://localhost:8025 |
| phpmyadmin | ispezione del database | http://localhost:8081 |

MySQL è esposto sull'host alla porta **3307** per non entrare in conflitto con un eventuale MySQL locale sulla 3306.

Endpoint di controllo: `GET http://localhost:8000/api/v1/health` risponde `{"status":"ok"}`.

## Credenziali del database

| Utente | Password | Uso |
|---|---|---|
| `ecommerce` | `secret` | applicazione e test, anche per phpMyAdmin |
| `root` | `root` | amministrazione |

## Comandi

| Comando | Effetto |
|---|---|
| `make up` / `make down` | avvia in background / ferma i container |
| `make build` | ricostruisce le immagini |
| `make logs` | segue i log di tutti i servizi |
| `make shell` | shell nel container `app` |
| `make migrate` | esegue le migration |
| `make fresh` | ricrea il database con i dati demo |
| `make test` | test di backend (Pest) e frontend (Vitest) |
| `make test-backend` / `make test-frontend` | test di un solo lato |
| `make coverage-backend` / `make coverage-frontend` | test con copertura (soglie 80% e 70%) |
| `make lint` | Pint nel backend, ESLint con Prettier nel frontend |
| `make artisan cmd="..."` | comando Artisan |
| `make composer cmd="..."` | comando Composer |
| `make npm cmd="..."` | comando npm |

### Dopo un `git pull`

Gli script di avvio installano le dipendenze solo se mancano `backend/vendor` o `frontend/node_modules`, e non si accorgono di un lock file aggiornato. Se il pull modifica `composer.lock` o `package-lock.json`, con i container accesi esegui:

```sh
make composer cmd="install"
make npm cmd="ci"
docker compose restart node
make migrate
```

`npm ci` cancella e ricrea `node_modules` mentre il dev server Vite è in esecuzione: il riavvio del container `node` evita errori di moduli mancanti.

Se il pull contiene nuove migration, basta `make migrate`.

### Watcher del frontend

Se le modifiche ai file del frontend non vengono rilevate (per esempio con Docker Desktop su Windows), imposta `VITE_USE_POLLING=true` nel `.env` alla radice.

## Credenziali demo

Da completare quando verranno creati i seeder degli utenti.

| Ruolo | Email | Password | Note |
|---|---|---|---|
| Admin | — | — | — |
| Cliente demo | — | — | — |
| Cliente con doppia autenticazione | — | — | — |
| Cliente con email non verificata | — | — | — |
