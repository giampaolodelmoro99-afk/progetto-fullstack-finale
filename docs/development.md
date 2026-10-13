# Sviluppo

Dettagli operativi per chi lavora sul progetto. L'avvio rapido e le credenziali demo sono nel [README](../README.md).

## Comandi del Makefile

| Comando | Effetto |
|---|---|
| `make up` / `make down` | avvia in background il negozio senza l'assistente AI / ferma tutti i container, assistente compreso |
| `make up-ai` | avvia anche l'assistente AI (profilo `ai`) |
| `make up-gpu` | come `make up-ai`, con la GPU NVIDIA ([docs/assistant/running.md](assistant/running.md)) |
| `make build` | ricostruisce le immagini |
| `make logs` | segue i log di tutti i servizi |
| `make shell` | shell nel container `app` |
| `make migrate` | esegue le migration |
| `make fresh` | ricrea il database con i dati demo |
| `make admin` | crea un amministratore (`php artisan admin:create`) |
| `make admin-remove` | rimuove un amministratore anonimizzandolo (`php artisan admin:remove`) |
| `make test` | test di backend (Pest) e frontend (Vitest) |
| `make test-backend` / `make test-frontend` | test di un solo lato |
| `make coverage-backend` / `make coverage-frontend` | test con copertura (soglie 80% e 70%) |
| `make lint` | Pint nel backend, ESLint con Prettier nel frontend |
| `make artisan cmd="..."` | comando Artisan |
| `make composer cmd="..."` | comando Composer |
| `make npm cmd="..."` | comando npm |
| `make ask q="..."` | domanda all'assistente AI dal terminale, con i tempi di risposta (dentro `q` usa virgolette singole, non doppie) |

I test del backend usano tutti il database `ecommerce_testing`: non lanciare mai due esecuzioni insieme (due `make test`, o `make test` e `make coverage-backend`), perché le migration di `RefreshDatabase` si scontrano e producono falsi fallimenti.

## File `.env` alla radice

È facoltativo: serve solo a cambiare UID, GID o porte dei servizi.

```sh
cp .env.example .env
```

## Credenziali del database

| Utente | Password | Uso |
|---|---|---|
| `ecommerce` | `secret` | applicazione e test, anche per phpMyAdmin (http://localhost:8081) |
| `root` | `root` | amministrazione |

MySQL è esposto sull'host alla porta **3307** per non entrare in conflitto con un eventuale MySQL locale sulla 3306.

## Dopo un `git pull`

A ogni avvio gli script dei container confrontano l'hash di `composer.lock` e `package-lock.json` con quello dell'ultima installazione (salvato in `backend/vendor/.composer-lock.sha256` e `frontend/node_modules/.package-lock.sha256`) e reinstallano le dipendenze se è cambiato. Dopo un pull che aggiorna i lock file basta quindi riavviare:

```sh
make down
make up
```

Le nuove migration si applicano da sole all'avvio; con i container già accesi basta `make migrate`.

Il primo avvio dopo l'introduzione di questo controllo (Fase 10a) reinstalla una volta le dipendenze anche se non sono cambiate, perché il file con l'hash non esiste ancora. Lo stesso succede dopo un aggiornamento fatto a mano (`make composer cmd="require ..."`, `make npm cmd="install ..."`): il lock file cambia ma il file con l'hash no, quindi il riavvio successivo reinstalla una volta. È solo più lento, non cambia nulla.

Lo script del backend, `docker/php/entrypoint.sh`, è copiato dentro l'immagine `ecommerce-app` insieme a `docker/php/Dockerfile` e `docker/php/php.ini`: se il pull modifica uno di questi tre file, l'immagine già costruita resta quella vecchia. In quel caso:

```sh
make build
make up
```

Da un clone pulito non serve, perché `docker compose up` costruisce l'immagine la prima volta. Lo script del frontend, `docker/node/entrypoint.sh`, è montato nel container e vale subito.

## Watcher del frontend

Se le modifiche ai file del frontend non vengono rilevate (per esempio con Docker Desktop su Windows), imposta `VITE_USE_POLLING=true` nel `.env` alla radice.

## Due copie della repository sulla stessa macchina

`docker-compose.yml` fissa il nome del progetto (`name: ecommerce`): due cartelle con la repository usano gli stessi container, la stessa immagine `ecommerce-app` e gli stessi volumi, compreso il database. Per avviare una seconda copia isolata (per esempio per provare un clone pulito) si sceglie un altro nome di progetto, e le porte devono essere libere:

```sh
COMPOSE_PROJECT_NAME=ecommerce-prova docker compose up
COMPOSE_PROJECT_NAME=ecommerce-prova docker compose down -v
```

Il `down -v` va lanciato sempre con lo stesso nome usato all'avvio: senza, rimuoverebbe i volumi del progetto principale, compreso il modello dell'assistente.

## Documentazione delle API

La specifica **OpenAPI 3.1**, in italiano, è [`docs/api/openapi.yaml`](api/openapi.yaml): tutte le rotte con parametri, corpi, risposte, codici d'errore, limiter, autenticazione a cookie con CSRF (419), conferma dell'identità (423) ed eventi NDJSON dell'assistente. Il test `backend/tests/Feature/Docs/OpenApiSpecTest.php` fallisce se una rotta manca dalla specifica o se la specifica descrive una rotta che non esiste.

Per consultarla: https://editor.swagger.io, *File → Import file*, `docs/api/openapi.yaml`.

### Prove con curl

Le richieste con il cookie di sessione vanno preparate come fa il frontend: un cookie jar, gli header `Origin` e `Referer` del frontend (così Sanctum riconosce la richiesta) e, sulle scritture, l'header `X-XSRF-TOKEN` con il valore decodificato del cookie `XSRF-TOKEN`, riletto prima di ogni richiesta perché login e logout lo cambiano.

```bash
API=http://localhost:8000
H=(-H 'Accept: application/json' -H 'Origin: http://localhost:5173' -H 'Referer: http://localhost:5173/')
xsrf() { local t; t=$(awk '$6=="XSRF-TOKEN"{print $7}' cookies.txt); printf '%b' "${t//%/\\x}"; }

curl -s -c cookies.txt -b cookies.txt "${H[@]}" $API/sanctum/csrf-cookie
curl -s -c cookies.txt -b cookies.txt "${H[@]}" -H 'Content-Type: application/json' \
  -H "X-XSRF-TOKEN: $(xsrf)" -X POST $API/api/v1/login \
  -d '{"email":"giulia.ferri@esempio.it","password":"Silicio2026"}'
curl -s -c cookies.txt -b cookies.txt "${H[@]}" $API/api/v1/me
```

Senza `X-XSRF-TOKEN` una scrittura risponde 419 «La sessione è scaduta. Ricarica la pagina e riprova.».

### Prove con Postman

*Import*, trascina `docs/api/openapi.yaml` e scegli di generare una collection; la variabile `baseUrl` vale `http://localhost:8000/api/v1`.

1. Header su tutte le richieste della collection: `Accept: application/json`, `Origin: http://localhost:5173`, `X-XSRF-TOKEN: {{xsrf_token}}`.
2. Script post-response a livello di collection, che aggiorna il token dopo ogni risposta:

   ```js
   const token = pm.cookies.get('XSRF-TOKEN');
   if (token) {
       pm.collectionVariables.set('xsrf_token', decodeURIComponent(token));
   }
   ```

3. Prima richiesta di ogni sessione: `GET http://localhost:8000/sanctum/csrf-cookie`, poi `POST /login` con le credenziali demo.

## Ordini rimasti in attesa

Se il checkout si interrompe tra l'addebito e la registrazione del pagamento (per esempio il processo PHP si ferma), l'ordine resta «In attesa» con le scorte già scalate. Il comando:

```sh
make artisan cmd="orders:reconcile-pending"
```

cerca gli ordini in attesa creati da più di 15 minuti, chiede di nuovo l'esito dell'addebito al gateway con la stessa chiave di idempotenza (nessun secondo addebito) e completa l'ordine come farebbe il checkout: pagato, con carrello svuotato ed email di conferma, oppure annullato con le scorte ripristinate. Si può rilanciare senza effetti doppi; un errore del gateway lascia l'ordine in attesa per l'esecuzione successiva. Il progetto non ha uno scheduler: il comando si lancia a mano (in produzione andrebbe pianificato).

Sui dati demo, passati 15 minuti dal seeding, annulla l'ordine «In attesa» creato dal seeder, perché il gateway finto non ha mai ricevuto il suo addebito, e restituisce i 2 pezzi di Cavo Seta USB-C 1 m che quell'ordine aveva scalato.

## Riproducibilità dei dati demo

`make fresh` ricrea sempre gli stessi dati: prodotti, account e indirizzi fissi sono scritti a mano (`backend/database/seeders/data/products.php` e i seeder), mentre clienti casuali e ordini storici usano semi fissi: Faker per nomi e indirizzi, un `Random\Randomizer` con seme proprio per date, clienti, stati e righe degli ordini (il motivo è spiegato in `DatabaseSeeder`). Le date sono relative al giorno del seeding: novità e ordini del mese corrente restano sempre attuali. Prima di copiare le immagini dei prodotti il seeder svuota `storage/app/public/products`, quindi non restano file orfani.

I seeder usano Faker, che è una dipendenza di sviluppo: il database demo si crea nell'ambiente Docker di sviluppo, non in produzione.

## Passkey in sviluppo

- Funzionano solo dal frontend, su `http://localhost:5173`: è l'unica origine ammessa e il relying party è `localhost` (i browser considerano `localhost` un contesto sicuro anche senza HTTPS).
- Libreria: `laravel/passkeys`, con il vincolo `^0.2.1`, che non accetta la 0.3: in una versione 0.x un aggiornamento minore può cambiare l'API, quindi si aggiorna a mano, verificando i test.
- Nel frontend le cerimonie passano da `@simplewebauthn/browser`, con le opzioni lette dalle rotte del progetto sotto `/api/v1`; le rotte del pacchetto sono disattivate.
- `PASSKEYS_USER_HANDLE_SECRET` è facoltativa: se manca, il segreto si ricava dalla `APP_KEY` dell'installazione. Va impostata prima di un'eventuale rotazione della `APP_KEY`.
- I dati demo non contengono passkey: si registrano dalla pagina Sicurezza con un dispositivo reale.
