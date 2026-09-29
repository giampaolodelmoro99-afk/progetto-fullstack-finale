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

## Illustrazioni demo

Le immagini dei prodotti e delle categorie dei dati demo sono illustrazioni vettoriali scritte a mano in `design/illustrations/`, convertite in WebP 800×1000 e già presenti nella repository: `docker compose up` non le rigenera.

| Sorgenti | WebP generati |
|---|---|
| `design/illustrations/products/*.svg` (96 file) | `backend/database/seeders/assets/products/` |
| `design/illustrations/categories/*.svg` (6 categorie) | `frontend/src/assets/categories/` |
| `design/illustrations/fallback.svg` (ripiego per categorie e prodotti senza immagini) | `frontend/src/assets/fallback.webp` |

Le immagini dei prodotti seguono lo schema `{type}-{finish}-{view}`: 16 tipi, due finiture (`graphite`, `ivory`) e tre viste (`front` per la copertina, `angle` per i tre quarti, `detail` per il particolare), per esempio `headphones-ivory-angle.webp`. Tipi: `headphones`, `earbuds`, `speaker`, `radio`, `charger`, `cable`, `power-bank`, `wireless-charger`, `stand`, `keyboard`, `hub`, `mouse`, `watch`, `strap`, `lamp`, `sensor`.

Le immagini delle categorie hanno nomi in inglese; la corrispondenza con gli slug è questa:

| Slug della categoria | Immagine |
|---|---|
| `audio` | `audio.webp` |
| `ricarica-ed-energia` | `charging.webp` |
| `scrivania` | `desk.webp` |
| `indossabili` | `wearables.webp` |
| `casa-smart` | `smart-home.webp` |
| `viaggio` | `travel.webp` |

Dopo aver modificato un SVG, rigenera tutte le immagini dalla radice della repository:

```sh
sh scripts/render-illustrations.sh
```

Lo script usa un container Alpine 3.20 usa e getta, fissato per digest così la conversione dà sempre gli stessi file, con `rsvg-convert` e `cwebp` installati al momento: serve solo Docker, e non si aggiunge nessuna dipendenza al progetto. Controlla prima che Docker sia disponibile e che le sorgenti esistano, converte tutto in una cartella temporanea del container e stampa il peso di ogni file. Sostituisce i WebP esistenti solo se tutte le conversioni riescono e nessun file supera i 60 KB; altrimenti esce con errore e lascia intatti i file già presenti. In ogni caso i file generati appartengono al tuo utente.

Per controllare il risultato apri `design/illustrations/preview.html` nel browser: mostra tutte le immagini generate in griglia.

## Credenziali demo

Da completare quando verranno creati i seeder degli utenti.

| Ruolo | Email | Password | Note |
|---|---|---|---|
| Admin | — | — | — |
| Cliente demo | — | — | — |
| Cliente con doppia autenticazione | — | — | — |
| Cliente con email non verificata | — | — | — |

## Carte di test

Il pagamento è simulato: nessun addebito reale. Scadenza qualsiasi non passata nel formato MM/AA, CVC di 3 cifre qualsiasi.

| Numero | Esito |
|---|---|
| 4242 4242 4242 4242 | pagamento approvato |
| 4000 0000 0000 0002 | pagamento rifiutato (errore "Pagamento non riuscito") |

Un ordine con pagamento rifiutato resta nel database come tentativo fallito: lo vede solo l'admin, non compare negli ordini del cliente.
