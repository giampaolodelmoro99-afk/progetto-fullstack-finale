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

Tutti gli account demo hanno la stessa password: **`Silicio2026`**.

| Ruolo | Nome | Email | Note |
|---|---|---|---|
| Admin | Marta Lenzi | `marta.lenzi@esempio.it` | senza doppia autenticazione; autrice delle spedizioni e delle consegne degli ordini demo |
| Cliente demo | Giulia Ferri | `giulia.ferri@esempio.it` | email verificata, due indirizzi (Pisa predefinito, Firenze), ordini in tutti gli stati, carrello con una riga esaurita |
| Cliente con doppia autenticazione | Davide Conti | `davide.conti@esempio.it` | TOTP attivo con segreto fisso (vedi sotto) |
| Cliente con email non verificata | Paolo Greco | `paolo.greco@esempio.it` | naviga e usa il carrello, ma il checkout è bloccato |

Oltre a questi, il seeder crea 20 clienti casuali (`nome.cognome@esempio.it`, stessa password) con un indirizzo e ordini degli ultimi sei mesi.

### Cosa mostrano i dati demo

- **Carrello di Giulia**: Cuffie Aria, Caricatore Brezza 65 W ×2, Cavo Seta USB-C 1 m e la Radio Onda Portatile esaurita: 5 articoli e checkout bloccato finché non si rimuove la riga esaurita.
- **Ordini di Giulia**: consegnati, annullato con rimborso, spedito, pagato (da spedire) e un tentativo di pagamento rifiutato, visibile solo all'admin.
- **Catalogo**: 50 prodotti su cinque categorie, "Viaggio" senza prodotti (si può eliminare dall'admin), alcuni esauriti, negli ultimi pezzi o disattivati, e sei novità degli ultimi 30 giorni, tra cui le Cuffie Aria dell'hero della home.
- **Dashboard admin**: ordini pagati anche nel mese corrente, ordini da spedire, scorte basse e un ordine "In attesa" con il pagamento in corso.

### Doppia autenticazione di Davide Conti

Il segreto è fisso e pubblicato qui **solo per la demo**, così durante la presentazione si può aggiungere l'account all'app di autenticazione (Google Authenticator, 1Password, Aegis…). Il login con il secondo fattore è disponibile dalla Fase 3.

| Dato | Valore |
|---|---|
| Segreto TOTP (base32) | `CASADELSILICIODEMOTOTPSEGRETO234` |
| URI per il QR code | `otpauth://totp/Casa%20del%20Silicio:davide.conti%40esempio.it?secret=CASADELSILICIODEMOTOTPSEGRETO234&issuer=Casa%20del%20Silicio` |
| Codici di recupero (monouso) | `K7PM-2XQA`, `R4TD-9WNE`, `B8HV-3LCJ`, `M2QF-7YRS`, `T6ZN-4GKP`, `W9CX-5DHB`, `E3JL-8VMT`, `P5RY-6NAF` |

### Riproducibilità

`make fresh` ricrea sempre gli stessi dati: prodotti, account e indirizzi fissi sono scritti a mano (`backend/database/seeders/data/products.php` e i seeder), mentre clienti casuali e ordini storici usano Faker con un seed fisso. Le date sono relative al giorno del seeding: novità e ordini del mese corrente restano sempre attuali. Prima di copiare le immagini dei prodotti il seeder svuota `storage/app/public/products`, quindi non restano file orfani.

I seeder usano Faker, che è una dipendenza di sviluppo: il database demo si crea nell'ambiente Docker di sviluppo, non in produzione.

## Carte di test

Il pagamento è simulato: nessun addebito reale. Scadenza qualsiasi non passata nel formato MM/AA, CVC di 3 cifre qualsiasi.

| Numero | Esito |
|---|---|
| 4242 4242 4242 4242 | pagamento approvato |
| 4000 0000 0000 0002 | pagamento rifiutato (errore "Pagamento non riuscito") |

Un ordine con pagamento rifiutato resta nel database come tentativo fallito: lo vede solo l'admin, non compare negli ordini del cliente.
