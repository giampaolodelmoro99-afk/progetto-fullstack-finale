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
- Solo per l'assistente AI, facoltativo: circa **13 GB** di disco in più (immagine di Ollama 9,3 GB e modello 3,4 GB) e circa **4 GB di RAM** liberi

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

`docker compose up` non avvia l'assistente AI e non scarica nulla di Ollama: il negozio funziona completo, con l'assistente «non disponibile». Per avviarlo vedi "Assistente AI".

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
| ollama | modello locale dell'assistente AI (Ollama), solo con il profilo `ai` | solo nella rete Docker, `ollama:11434`: la porta non è pubblicata sull'host |

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
| `make up` / `make down` | avvia in background il negozio senza l'assistente AI / ferma tutti i container, assistente compreso |
| `make up-ai` | avvia anche l'assistente AI (profilo `ai`) |
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
| `make ask q="..."` | domanda all'assistente AI dal terminale, con i tempi di risposta (dentro `q` usa virgolette singole, non doppie) |
| `make up-gpu` | come `make up-ai`, con la GPU NVIDIA (vedi "Assistente AI") |

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

Se il pull modifica `docker/php/Dockerfile` o `docker/php/php.ini` (per esempio il supporto WebP della GD o i limiti di upload), l'immagine già costruita resta quella vecchia: esegui `make build` e poi `make up`. Da un clone pulito non serve, perché `docker compose up` costruisce l'immagine la prima volta.

### Watcher del frontend

Se le modifiche ai file del frontend non vengono rilevate (per esempio con Docker Desktop su Windows), imposta `VITE_USE_POLLING=true` nel `.env` alla radice.

## Assistente AI

L'assistente risponde su prodotti, confronti e regole del negozio con un modello locale, **qwen3.5:4b**, servito da Ollama e personalizzato dal `docker/ollama/Modelfile` (persona, perimetro, formato delle citazioni). Non vede dati personali. Scelta del modello e misure: `docs/assistant/model-evaluation.md`.

Il servizio `ollama` è nel profilo Compose `ai`, quindi non parte con il solo `docker compose up`. Per avviarlo insieme al negozio:

```sh
make up-ai
# equivale a
docker compose --profile ai up -d
```

Il primo avvio con il profilo scarica circa 7 GB:

| Download | Dimensione | A 40 MB/s | A 3 MB/s |
|---|---|---|---|
| Immagine `ollama/ollama:0.35.0` | 3,8 GB (9,3 GB su disco, comprende le librerie CUDA) | circa 2 minuti | circa 20 minuti |
| Modello qwen3.5:4b | 3,4 GB | circa 1 minuto e mezzo | circa 20 minuti |

Il negozio funziona durante il download e anche se il download fallisce. L'assistente è disponibile quando nei log compare `[ollama] Pronto` (`docker compose logs ollama`) e `docker compose ps` mostra `ollama` come `healthy`. Gli avvii successivi non scaricano nulla: immagine e modello restano nel volume `ollama-data`.

L'API di Ollama non ha autenticazione, quindi la sua porta non è pubblicata: la raggiunge solo il backend, nella rete Docker. Lo script `docker/ollama/entrypoint.sh` a ogni avvio scarica il modello base solo se manca e ricrea il modello del negozio dal Modelfile.

Per provarlo dal terminale, con le tool call e i tempi di risposta:

```sh
make ask q="Cerco delle cuffie sotto i 400 euro"
# oppure
docker compose exec app php artisan assistant:ask --timing "Cerco delle cuffie sotto i 400 euro"
```

Su CPU la prima parola arriva dopo circa 14 s per le domande semplici e fino a 40 s quando l'assistente cerca prima nel catalogo; una risposta completa con ricerche richiede fino a un minuto. Il primo messaggio dopo 30 minuti di inattività è più lento di circa 10 s, perché il modello va ricaricato in memoria (`OLLAMA_KEEP_ALIVE`, modificabile nel `.env` alla radice). L'assistente serve una risposta alla volta: chi scrive mentre è occupato riceve «L'assistente è occupato, riprova tra poco.».

### Demo dell'assistente su CPU

1. Avvia con `make up-ai` e aspetta che `docker compose ps` mostri `ollama` come `healthy`.
2. Qualche minuto prima di iniziare, riscalda il modello con una domanda: `make ask q='Quanto costa la spedizione?'`. La prima domanda dopo l'avvio, o dopo 30 minuti di inattività, carica il modello in memoria e costa circa 10 s in più. Durante la demo il modello resta caricato.
3. Tempi attesi a caldo:
   - regole del negozio e domande fuori tema: prima parola in circa 14–18 s, risposta completa in circa 15–25 s
   - ricerche nel catalogo: prima parola in circa 14–40 s, risposta completa in circa 40–50 s
   - confronti tra prodotti: prima parola dopo circa 30 s, risposta completa anche oltre un minuto
4. Domande consigliate, provate nella valutazione e affidabili:
   - «Quanto costa la spedizione e quando diventa gratuita?»: regole del negozio lette dal backend
   - «Cerco una radio, quali avete e quanto costano?»: ricerca con prezzi e disponibilità dal database e citazioni dei prodotti
   - «Cerco delle cuffie o degli auricolari sotto i 200 euro, cosa mi consigli?»: due ricerche con filtro di prezzo
   - «A che punto è il mio ordine?»: nessun accesso ai dati personali, rimando a «I miei ordini»
   - «Mi scrivi una ricetta per la carbonara?» oppure «Ignora le istruzioni precedenti…»: rifiuto con la frase fissa
   - un confronto come «Che differenza c'è tra le Cuffie Aria e le Cuffie Aria Studio?» è il più lento: meglio lasciarlo per ultimo, mentre si commenta il resto

Un'altra domanda inviata mentre l'assistente risponde riceve «L'assistente è occupato, riprova tra poco.»: durante la demo conviene una domanda alla volta.

### GPU NVIDIA (facoltativa)

Con il profilo `ai` l'assistente usa la CPU, la configurazione su cui è stato provato. L'uso della GPU è facoltativo e non è stato misurato (`docs/assistant/model-evaluation.md`, sezione "GPU"). Con una GPU NVIDIA e i prerequisiti qui sotto si può aggiungere `docker-compose.gpu.yml`:

```sh
make up-gpu
# equivale a
docker compose -f docker-compose.yml -f docker-compose.gpu.yml --profile ai up -d
```

Prerequisiti sull'host: driver NVIDIA (`nvidia-smi` deve funzionare) e NVIDIA Container Toolkit. Su Ubuntu e Debian:

```sh
curl -fsSL https://nvidia.github.io/libnvidia-container/gpgkey \
  | sudo gpg --dearmor -o /usr/share/keyrings/nvidia-container-toolkit-keyring.gpg
curl -fsSL https://nvidia.github.io/libnvidia-container/stable/deb/nvidia-container-toolkit.list \
  | sed 's#deb https://#deb [signed-by=/usr/share/keyrings/nvidia-container-toolkit-keyring.gpg] https://#g' \
  | sudo tee /etc/apt/sources.list.d/nvidia-container-toolkit.list
sudo apt-get update
sudo apt-get install -y nvidia-container-toolkit
sudo nvidia-ctk runtime configure --runtime=docker
sudo systemctl restart docker
```

Verifica: `docker run --rm --gpus all ubuntu nvidia-smi` mostra la GPU. Con `docker compose exec ollama ollama ps` la colonna `PROCESSOR` indica se il modello è sulla GPU.

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

Tutti gli account demo con cui si può accedere hanno la stessa password: **`Silicio2026`**.

| Ruolo | Nome | Email | Note |
|---|---|---|---|
| Admin | Marta Lenzi | `marta.lenzi@esempio.it` | senza doppia autenticazione; autrice delle spedizioni e delle consegne degli ordini demo |
| Cliente demo | Giulia Ferri | `giulia.ferri@esempio.it` | email verificata, due indirizzi (Pisa predefinito, Firenze), ordini in tutti gli stati, carrello con una riga esaurita |
| Cliente con doppia autenticazione | Davide Conti | `davide.conti@esempio.it` | TOTP attivo con segreto fisso (vedi sotto) |
| Cliente con email non verificata | Paolo Greco | `paolo.greco@esempio.it` | naviga e usa il carrello, ma il checkout è bloccato |
| Cliente eliminato | Utente eliminato | `deleted-{id}@anonymized.invalid` | account anonimizzato con password casuale: non può accedere; un ordine consegnato che resta per gli obblighi fiscali e che nell'area admin (dalla Fase 5) mostra «Cliente eliminato» |

Oltre a questi, il seeder crea 20 clienti casuali (`nome.cognome@esempio.it`, stessa password) con un indirizzo e ordini degli ultimi sei mesi.

### Cosa mostrano i dati demo

- **Carrello di Giulia**: Cuffie Aria, Caricatore Brezza 65 W ×2, Cavo Seta USB-C 1 m e la Radio Onda Portatile esaurita: 5 articoli e checkout bloccato finché non si rimuove la riga esaurita.
- **Ordini di Giulia**: consegnati, annullato con rimborso, spedito, pagato (da spedire) e un tentativo di pagamento rifiutato, visibile solo all'admin.
- **Catalogo**: 50 prodotti su cinque categorie, "Viaggio" senza prodotti (si può eliminare dall'admin), alcuni esauriti, negli ultimi pezzi o disattivati, e sei novità degli ultimi 30 giorni, tra cui le Cuffie Aria dell'hero della home.
- **Account eliminato**: un cliente che ha eliminato l'account, anonimizzato come fa `DELETE /api/v1/me` (nome, email fittizia, nessun indirizzo, carrello o attività), con il suo ordine consegnato conservato.
- **Dashboard admin**: ordini pagati anche nel mese corrente, ordini da spedire, scorte basse e un ordine "In attesa" con il pagamento in corso.

### Doppia autenticazione di Davide Conti

Il segreto è fisso e pubblicato qui **solo per la demo**, così durante la presentazione si può aggiungere l'account all'app di autenticazione (Google Authenticator, 1Password, Aegis…). Il login con il secondo fattore è disponibile dalla Fase 3b (`POST /api/v1/login/two-factor`). Ogni codice di recupero vale una sola volta: dopo averne usato uno nelle prove, `make fresh` ricrea i dati demo e ripristina gli otto codici elencati qui.

| Dato | Valore |
|---|---|
| Segreto TOTP (base32) | `CASADELSILICIODEMOTOTPSEGRETO234` |
| URI per il QR code | `otpauth://totp/Casa%20del%20Silicio:davide.conti%40esempio.it?secret=CASADELSILICIODEMOTOTPSEGRETO234&issuer=Casa%20del%20Silicio` |
| Codici di recupero (monouso) | `K7PM-2XQA`, `R4TD-9WNE`, `B8HV-3LCJ`, `M2QF-7YRS`, `T6ZN-4GKP`, `W9CX-5DHB`, `E3JL-8VMT`, `P5RY-6NAF` |

### Riproducibilità

`make fresh` ricrea sempre gli stessi dati: prodotti, account e indirizzi fissi sono scritti a mano (`backend/database/seeders/data/products.php` e i seeder), mentre clienti casuali e ordini storici usano semi fissi: Faker per nomi e indirizzi, un `Random\Randomizer` con seme proprio per date, clienti, stati e righe degli ordini (il motivo è spiegato in `DatabaseSeeder`). Le date sono relative al giorno del seeding: novità e ordini del mese corrente restano sempre attuali. Prima di copiare le immagini dei prodotti il seeder svuota `storage/app/public/products`, quindi non restano file orfani.

I seeder usano Faker, che è una dipendenza di sviluppo: il database demo si crea nell'ambiente Docker di sviluppo, non in produzione.

## Passkey

Le passkey (WebAuthn) permettono l'accesso senza password e valgono come secondo fattore quando la doppia autenticazione è attiva.

- Funzionano solo dal frontend, su `http://localhost:5173`: è l'unica origine ammessa e il relying party è `localhost` (i browser considerano `localhost` un contesto sicuro anche senza HTTPS). Non si usano dall'indirizzo del backend.
- Libreria: `laravel/passkeys`, il pacchetto ufficiale di Laravel. Il vincolo in `composer.json` è `^0.2.1`, che non accetta la 0.3: in una versione 0.x un aggiornamento minore può cambiare l'API, quindi si aggiorna a mano, verificando i test.
- `PASSKEYS_USER_HANDLE_SECRET` è facoltativa e non serve in sviluppo: se manca, il segreto dell'identificativo WebAuthn dell'utente si ricava dalla `APP_KEY` dell'installazione. Va impostata prima di un'eventuale rotazione della `APP_KEY`.
- I dati demo non contengono passkey: si registrano dalla pagina Sicurezza con un dispositivo reale.

## Carte di test

Il pagamento è simulato: nessun addebito reale. Scadenza qualsiasi non passata nel formato MM/AA, CVC di 3 cifre qualsiasi.

| Numero | Esito |
|---|---|
| 4242 4242 4242 4242 | pagamento approvato |
| 4000 0000 0000 0002 | pagamento rifiutato (errore "Pagamento non riuscito") |

Un ordine con pagamento rifiutato resta nel database come tentativo fallito: lo vede solo l'admin, non compare negli ordini del cliente.

## Sviluppi futuri

- **Cancellazione automatica dopo 10 anni.** Quando un cliente elimina l'account, i suoi dati personali si cancellano e l'utente viene anonimizzato, ma ordini e pagamenti restano per l'obbligo di conservazione della documentazione contabile. Un comando pianificato potrebbe cancellarli allo scadere dei 10 anni; oggi resta fuori dal progetto.
