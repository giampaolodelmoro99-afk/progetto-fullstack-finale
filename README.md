# Casa del Silicio — e-commerce

Progetto d'esame ITS Full Stack Developer: un negozio online completo di elettronica e accessori, con un'API REST in Laravel, una SPA in React e un assistente AI che gira in locale.

Il cliente sfoglia il catalogo, compra con un checkout in tre passi e un pagamento simulato, segue e annulla i propri ordini, protegge l'account con doppia autenticazione e passkey e può chiedere consiglio all'assistente. L'amministratore gestisce prodotti con immagini, categorie e ordini da un pannello con dashboard.

| Cartella | Contenuto |
|---|---|
| `backend/` | Laravel 13 (PHP 8.4), API REST headless con autenticazione Sanctum SPA a cookie |
| `frontend/` | React 19 con Vite, React Router, TanStack Query, React Hook Form, Zod e MUI |
| `docker/` | Dockerfile, script di avvio e configurazioni dei servizi |
| `design/` | design system, mockup e illustrazioni demo |
| `docs/` | schema ER, specifica OpenAPI, assistente AI, sviluppo, roadmap e backlog |

## Requisiti

- Docker con Docker Compose v2
- `make` (facoltativo, per i comandi abbreviati)
- Solo per l'assistente AI, facoltativo: circa **13 GB** di disco in più (immagine di Ollama e modello) e circa **4 GB di RAM** liberi

Sull'host non servono PHP, Composer né Node: tutto gira nei container.

## Avvio rapido

```sh
docker compose up
```

Al primo avvio i container, senza altri passaggi, creano i file `.env` dagli `.env.example`, installano le dipendenze, generano `APP_KEY`, eseguono le migration, popolano il database con i dati demo (solo se è vuoto) e creano il link dello storage. Il primo avvio richiede qualche minuto; i successivi sono immediati.

| Servizio | Indirizzo |
|---|---|
| Negozio (frontend React) | http://localhost:5173 |
| API Laravel | http://localhost:8000 (stato: `GET /api/v1/health`) |
| Mailpit, le email inviate dall'applicazione | http://localhost:8025 |
| phpMyAdmin | http://localhost:8081 (utente `ecommerce`, password `secret`) |
| MySQL 8.4 | `localhost:3307` dall'host |

`docker compose up` non avvia l'assistente AI: il negozio funziona completo, con l'assistente «non disponibile». Per attivarlo:

```sh
make up-ai
```

Tutti i comandi (`make test`, `make fresh`, `make lint`…), cosa fare dopo un `git pull`, le prove delle API con curl o Postman e gli altri dettagli operativi sono in [docs/development.md](docs/development.md).

## Credenziali demo

Tutti gli account demo con cui si può accedere hanno la stessa password: **`Silicio2026`**.

| Ruolo | Nome | Email | Note |
|---|---|---|---|
| Admin | Marta Lenzi | `marta.lenzi@esempio.it` | senza doppia autenticazione; autrice delle spedizioni e delle consegne degli ordini demo |
| Cliente demo | Giulia Ferri | `giulia.ferri@esempio.it` | email verificata, due indirizzi (Pisa predefinito, Firenze), ordini in tutti gli stati, carrello con una riga esaurita |
| Cliente con doppia autenticazione | Davide Conti | `davide.conti@esempio.it` | TOTP attivo con segreto fisso (vedi sotto) |
| Cliente con email non verificata | Paolo Greco | `paolo.greco@esempio.it` | naviga e usa il carrello, ma il checkout è bloccato |
| Cliente eliminato | Utente eliminato | `deleted-{id}@anonymized.invalid` | account anonimizzato: non può accedere; il suo ordine consegnato resta per gli obblighi fiscali e nell'area admin mostra «Cliente eliminato» |

Oltre a questi, il seeder crea 20 clienti casuali (`nome.cognome@esempio.it`, stessa password) con un indirizzo e ordini degli ultimi sei mesi. `make fresh` ricrea sempre gli stessi dati ([docs/development.md](docs/development.md#riproducibilità-dei-dati-demo)).

### Doppia autenticazione di Davide Conti

Il segreto è fisso e pubblicato qui **solo per la demo**, così durante la presentazione si può aggiungere l'account a un'app di autenticazione (Google Authenticator, 1Password, Aegis…). Ogni codice di recupero vale una sola volta: `make fresh` ripristina gli otto codici.

| Dato | Valore |
|---|---|
| Segreto TOTP (base32) | `CASADELSILICIODEMOTOTPSEGRETO234` |
| URI per il QR code | `otpauth://totp/Casa%20del%20Silicio:davide.conti%40esempio.it?secret=CASADELSILICIODEMOTOTPSEGRETO234&issuer=Casa%20del%20Silicio` |
| Codici di recupero (monouso) | `K7PM-2XQA`, `R4TD-9WNE`, `B8HV-3LCJ`, `M2QF-7YRS`, `T6ZN-4GKP`, `W9CX-5DHB`, `E3JL-8VMT`, `P5RY-6NAF` |

### Cosa mostrano i dati demo

- **Carrello di Giulia**: Cuffie Aria, Caricatore Brezza 65 W ×2, Cavo Seta USB-C 1 m e la Radio Onda Portatile esaurita: checkout bloccato finché non si rimuove la riga esaurita.
- **Ordini di Giulia**: consegnati, annullato con rimborso, spedito, pagato (da spedire) e un tentativo di pagamento rifiutato, visibile solo all'admin.
- **Catalogo**: 50 prodotti su cinque categorie, "Viaggio" senza prodotti (non compare nel menu e si può eliminare dall'admin), prodotti esauriti, negli ultimi pezzi o disattivati, e sei novità degli ultimi 30 giorni.
- **Dashboard admin**: ordini pagati nel mese corrente, ordini da spedire, scorte basse e un ordine «In attesa» con il pagamento in corso.

Le passkey non sono nei dati demo: si registrano dalla pagina Sicurezza con un dispositivo reale, sempre da http://localhost:5173.

### Carte di test

Il pagamento è simulato, nessun addebito reale. Scadenza qualsiasi non passata nel formato MM/AA, CVC di 3 cifre qualsiasi.

| Numero | Esito |
|---|---|
| 4242 4242 4242 4242 | pagamento approvato |
| 4000 0000 0000 0002 | pagamento rifiutato |

## Creazione e rimozione di un amministratore

Gli amministratori non si registrano dal sito: si creano e si rimuovono dal terminale.

```sh
make admin
# equivale a
docker compose exec app php artisan admin:create
```

Il comando chiede nome, email, password e conferma. La password si scrive sempre in modo nascosto e non si può passare come opzione, così non resta nella cronologia del terminale; non compare mai nell'output né nei log. Valgono le stesse regole della registrazione (email unica e valida, almeno 10 caratteri con maiuscole, minuscole e numeri, password non comparsa in fughe di dati). L'account nasce con l'email già verificata. Il comando crea solo account nuovi: un'email già usata, anche da un cliente, viene rifiutata.

```sh
make admin-remove
# equivale a
docker compose exec app php artisan admin:remove
```

Il comando chiede l'email, mostra nome ed email dell'amministratore trovato e chiede di riscrivere l'email per confermare. Un'email inesistente o di un cliente viene rifiutata senza modifiche, così come la rimozione dell'ultimo amministratore attivo. L'account non si cancella ma si anonimizza come quello di un cliente che si elimina, perché la cronologia degli ordini conserva chi ha fatto ogni passaggio: lì compare come «Amministratore eliminato». All'indirizzo originale arriva un'email che avvisa della rimozione.

## Funzionalità

**Negozio**
- Home con novità e categorie, catalogo con ricerca, filtri (categoria, prezzo, disponibilità, novità) e ordinamento nella query string, dettaglio prodotto con galleria, scheda tecnica, disponibilità e prodotti correlati.
- Carrello per i clienti autenticati, con limiti di quantità e blocco del checkout per le righe esaurite.
- Checkout in tre passi (indirizzo, pagamento, riepilogo) con controllo dello stock sotto lock, pagamento simulato e conferma d'ordine con email.
- Pagine Assistenza e Note legali con i testi letti dall'API.

**Account del cliente**
- Registrazione con verifica dell'email, recupero della password, profilo con cambio email confermato dal nuovo indirizzo.
- Indirizzi (solo in Italia) con un predefinito, storico degli ordini con cronologia degli stati e annullamento con rimborso.
- Pagina Sicurezza: cambio password, doppia autenticazione TOTP con codici di recupero, passkey, attività recente ed eliminazione dell'account.

**Pannello admin**
- Dashboard con fatturato e ordini del mese, ordini per stato, ordini da spedire e scorte basse.
- Prodotti con caricamento, ordinamento e testo alternativo delle immagini; categorie; ordini con filtri, dettaglio, spedizione, consegna e annullamento con rimborso.

**Assistente AI**: chat nel negozio che consiglia e confronta i prodotti leggendo il catalogo reale e risponde sulle regole del negozio.

## Architettura

### Backend

API REST headless in Laravel 13, senza viste: ogni risposta è JSON (NDJSON in streaming per l'assistente), con i testi per l'utente in italiano. 75 rotte versionate sotto `/api/v1`, divise in pubbliche, cliente (`auth:sanctum`) e admin (`auth:sanctum` + `role:admin`).

```
Rotta → middleware → Form Request (Policy + validazione) → Controller → Action → API Resource
```

- **Controller** sottili e `final`: coordinano Form Request, Action e Resource, senza query complesse né accesso diretto al database.
- **Action** (57 classi in `app/Actions`): un caso d'uso per classe, con un solo metodo `execute()`. Le scritture correlate stanno in una transazione con un ordine fisso dei lock (prima l'utente, poi le risorse collegate); pagamenti, rimborsi e assistente si chiamano sempre fuori dalle transazioni.
- **Autorizzazione** con una Policy per modello. Le risorse personali di un altro utente (indirizzi, righe del carrello, ordini, passkey) rispondono 404, così non se ne rivela l'esistenza.
- **API Resource**: mai modelli grezzi; importi in centesimi, date ISO 8601 in ora italiana, enum come `{ value, label }`, varianti distinte per l'area admin.
- **Errori** in una forma unica `{ message, errors, code }`: eccezioni di dominio con lo status giusto e un `code` stabile quando il frontend deve distinguere i casi.
- **Servizi dietro un'interfaccia** (`app/Contracts`), scelti dalla configurazione: gateway di pagamento finto, assistente con Ollama o finto, cerimonia WebAuthn. Nei test si sostituiscono con implementazioni finte.
- **Notifiche e listener** tutti in coda (`ShouldQueue`); in sviluppo `QUEUE_CONNECTION=sync` li esegue subito.
- **Comandi Artisan**: `admin:create`, `admin:remove`, `orders:reconcile-pending` (riconciliazione degli ordini rimasti in attesa), `assistant:ask` e `db:seed-if-empty` usato dall'avvio.

Documentazione: schema del database in [docs/database/er-diagram.md](docs/database/er-diagram.md), specifica OpenAPI 3.1 in italiano in [docs/api/openapi.yaml](docs/api/openapi.yaml), tenuta allineata alle rotte da un test.

### Frontend

SPA in React 19 (JavaScript) organizzata per feature (`src/features/`: catalogo, carrello, checkout, account, sicurezza, admin, assistente…), con layout separati per negozio, area account e pannello admin.

- **Routing** con React Router: rotte per ospiti, clienti e admin protette da componenti dedicati, pagine admin caricate in modo lazy, filtri del catalogo e degli elenchi admin nella query string.
- **Dati del server** con TanStack Query. Alla fine della sessione la cache si svuota, così su un dispositivo condiviso chi accede dopo non vede i dati del precedente.
- **Client HTTP** axios con cookie e token CSRF: ripete una volta la richiesta dopo un 419, apre la conferma dell'identità su un 423 e ripete la richiesta originale, mostra i tempi di attesa dei 429.
- **Form** con React Hook Form e Zod, errori 422 del server sui campi; i dati della carta restano solo in memoria durante il checkout.
- **Interfaccia** con MUI e un tema derivato dal design system di `design/`: stile solo con la prop `sx` e i token del tema, font self-hosted, testi in italiano.

## Sicurezza

- **Autenticazione solo a cookie di sessione** (Sanctum SPA) con protezione CSRF, CORS limitato all'origine del frontend e nessun token personale.
- **Doppia autenticazione facoltativa** con TOTP e codici di recupero; **passkey** WebAuthn per l'accesso senza password e come secondo fattore.
- **Conferma dell'identità** (risposta 423) prima delle operazioni sensibili: cambio email, gestione di doppia autenticazione e passkey, eliminazione dell'account.
- **Notifiche email e registro degli eventi** per ogni operazione sensibile, con data, ora e dispositivo.
- **10 rate limiter** nominati (login, registrazione, recupero password, secondo fattore, assistente…) con risposta 429 e `Retry-After`.
- **Segreti mai in chiaro**: password con hash, segreto TOTP cifrato, codici di recupero con hash, token di reset e di cambio email salvati come hash. Le notifiche con un link d'azione sono cifrate anche nella coda.
- **Dati della carta** mai salvati né registrati nei log: dell'ordine restano solo le ultime 4 cifre.
- **Eliminazione dell'account con anonimizzazione**: i dati personali si cancellano, gli ordini restano per gli obblighi fiscali.
- **Upload delle immagini** ricodificati in WebP con nome casuale: il file originale non si salva mai.

## Assistente AI

L'assistente risponde su prodotti, confronti e regole del negozio con un modello locale, **qwen3.5:4b**, servito da Ollama nel servizio `ollama` (profilo Compose `ai`) e personalizzato da `docker/ollama/Modelfile`.

- Cerca nel catalogo con il **tool calling**: prezzi e disponibilità arrivano dal database, mai dal modello, e i prodotti citati diventano card con il link alla pagina.
- **Non vede dati personali**: alle domande sugli ordini rimanda all'area account; rifiuta le richieste fuori tema e i tentativi di cambiargli le istruzioni.
- Risposta in **streaming**, una alla volta, con rate limiting; la porta di Ollama non è pubblicata sull'host.
- Il negozio funziona anche senza assistente: `app` non dipende da `ollama`.

```sh
make up-ai                                   # avvia il negozio con l'assistente
make ask q="Cerco delle cuffie sotto i 400 euro"   # prova dal terminale, con i tempi
```

Il primo avvio scarica circa 7 GB. Su CPU la prima parola arriva in circa 14–40 secondi. Download, tempi, demo consigliata e GPU NVIDIA facoltativa sono in [docs/assistant/running.md](docs/assistant/running.md); scelta del modello e misure in [docs/assistant/model-evaluation.md](docs/assistant/model-evaluation.md).

## Test e copertura

```sh
make test               # backend e frontend
make coverage-backend   # soglia minima 80%
make coverage-frontend  # soglia minima 70%
```

Misurati il 12 ottobre 2026 (Fase 10a):

| | Test | Copertura |
|---|---|---|
| Backend (Pest, PCOV) | 1824 test con 8237 asserzioni, in 139 file | **99,8%** delle righe di `app/` |
| Frontend (Vitest, React Testing Library, MSW) | 791 test in 72 file | **95,7%** delle righe, 90,2% dei rami, 96,2% delle funzioni |

- Il backend usa il database MySQL `ecommerce_testing` e non chiama servizi esterni: gateway, assistente e cerimonia delle passkey sono sostituiti da implementazioni finte.
- Un test di architettura controlla le convenzioni (controller e Action `final`, nessun accesso a `DB` dai controller, notifiche in coda, eccezioni di dominio fuori dal log); un altro tiene la specifica OpenAPI allineata alle rotte.
- Il frontend intercetta le chiamate HTTP con MSW, quindi i test non dipendono dal backend acceso.
- **Limite noto:** con Vitest 5.0 e jsdom 30.1 un `File` di jsdom non diventa un corpo multipart per MSW. Il caricamento delle immagini nei test delle pagine admin è quindi simulato, e il `FormData` inviato si verifica a parte; riprovato senza successo anche con Vitest 5.0.4 e jsdom 30.1.2.

## Decisioni principali

- **Docker Compose come unico requisito**: `docker compose up` da un clone pulito porta a un'applicazione funzionante con i dati demo; gli script di avvio sono idempotenti e reinstallano le dipendenze quando cambiano i lock file.
- **Stack minimo**: nessun Nginx, Redis o worker; cache e sessioni su database, code sincrone ma tutte le notifiche già pronte per una coda vera.
- **API headless versionata** e frontend separato su un'altra porta, con autenticazione a cookie invece di token nel browser.
- **Importi interi in centesimi** e **snapshot negli ordini** (nome, SKU e prezzo dei prodotti, indirizzo di spedizione), così un ordine non cambia se si modificano o eliminano prodotti e indirizzi.
- **Stock scalato al checkout sotto lock**, con il pagamento fuori dalla transazione e una riconciliazione per gli ordini rimasti a metà.
- **Utenti anonimizzati, mai cancellati**, per conservare gli ordini.
- **Assistente con modello locale** e tool calling sul catalogo, isolato dai dati personali e facoltativo.
- **Test nello stesso task del codice**, con valori attesi scritti a mano.

Le decisioni sullo schema sono motivate in [docs/database/er-diagram.md](docs/database/er-diagram.md); le fasi del progetto sono in [docs/roadmap.md](docs/roadmap.md).

## Note per la produzione

La configurazione della repository è pensata per lo sviluppo in locale. Prima di un rilascio:

1. **`APP_DEBUG=false` e `LOG_LEVEL` a `warning` o `error`** (in `.env.example` sono `true` e `debug`): altrimenti un errore 500 mostra la traccia.
2. **`SESSION_SECURE_COOKIE=true` su HTTPS**, così il cookie di sessione non viaggia mai in chiaro (in sviluppo resta vuota perché `localhost` è senza HTTPS).
3. **Coda asincrona e worker**: basta cambiare `QUEUE_CONNECTION` e avviare `php artisan queue:work`. Le notifiche con un link d'azione (verifica email, reset password, conferma e annullamento del cambio email) sono cifrate nella coda con `APP_KEY`: prima di ruotare la chiave bisogna svuotare la coda.
4. **Rotte con soli token casuali**: `POST /email-change/confirm`, `POST /email-change/cancel`, `POST /reset-password/check` e `POST /reset-password/passkey-options` hanno solo il limiter generale (120 richieste al minuto). È accettato perché i token hanno almeno 256 bit di entropia e scadono in 60 minuti.
5. **Health check**: `GET /api/v1/health` risponde 503 se il database non risponde. Con cache e rate limiter sul database, però, un MySQL fermo fa fallire prima il limiter e la risposta è un 500: l'healthcheck di Compose se ne accorge comunque, ma un monitoraggio che distingue i codici deve accettare entrambi.
6. **Riconciliazione pianificata**: `orders:reconcile-pending` oggi si lancia a mano; in produzione va pianificato.
7. **Server web e HTTPS** davanti all'applicazione al posto di `php artisan serve` e del dev server di Vite (build statica del frontend con `npm run build`).

## Sviluppi futuri

- **Cancellazione automatica dopo 10 anni** degli ordini degli account eliminati, allo scadere dell'obbligo di conservazione della documentazione contabile, con un comando pianificato.
- **Gestione delle sessioni attive** nella pagina Sicurezza: elenco dei dispositivi collegati, con browser, sistema operativo e ultimo accesso, e chiusura di una singola sessione o di tutte le altre, con il suo evento di sicurezza e la notifica email. Oggi le altre sessioni si chiudono solo come effetto del cambio o del reset della password e della disattivazione della doppia autenticazione.
- **Test end-to-end** con Playwright sui flussi principali (acquisto, accesso con doppia autenticazione, gestione degli ordini).
- **Scheduler** per lanciare `orders:reconcile-pending` a intervalli regolari invece che a mano.

## Licenze di terze parti

| Componente | Uso | Licenza | Fonte ufficiale |
|---|---|---|---|
| Bodoni Moda (pacchetto `@fontsource-variable/bodoni-moda` 5.3.0) | titoli e prezzi del frontend, self-hosted | SIL Open Font License 1.1 | https://github.com/indestructible-type/Bodoni |
| Manrope (pacchetto `@fontsource-variable/manrope` 5.3.0) | interfaccia e testi del frontend, self-hosted | SIL Open Font License 1.1 | https://github.com/sharanda/manrope |

La licenza è quella dichiarata nel `package.json` (`OFL-1.1`) e nel file `LICENSE` di ciascun pacchetto installato. I font sono serviti da Vite insieme al frontend: nessuna richiesta a Google Fonts o ad altri CDN.

Le immagini dei prodotti e delle categorie sono illustrazioni originali del progetto ([docs/demo-assets.md](docs/demo-assets.md)).
