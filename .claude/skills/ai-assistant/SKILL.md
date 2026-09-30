---
name: ai-assistant
description: Assistente AI per i clienti del negozio con un modello locale Ollama — perimetro (prodotti, confronti, regole del negozio, nessun dato personale), interfaccia AiAssistant con implementazione Ollama e finta, tool calling sulle ricerche del catalogo, citazione dei prodotti per slug con card dal database, streaming NDJSON, limiti di messaggi, rate limiting e concorrenza, sicurezza dei prompt, servizio Ollama in Docker Compose con Modelfile e GPU opzionale, scelta del modello, widget del frontend e test senza chiamate reali. Usala per qualsiasi lavoro sull'assistente, nel backend, nel frontend o in docker/ollama.
---

# Assistente AI

Assistente per i clienti di Casa del Silicio, basato su un modello locale servito da Ollama. Si realizza in tre fasi: 2c (infrastruttura e scelta del modello), 4b (backend) e 8 (widget), più il Design E per l'interfaccia (`docs/roadmap.md`).

## Perimetro

- **Cosa fa:** aiuta a scegliere i prodotti, risponde su caratteristiche e confronti, spiega le regole del negozio (spedizione, resi, garanzia, contatti) con gli stessi testi della pagina `/assistenza`, letti dalla stessa fonte (`GET /shop/support`, skill `ecommerce-domain`, sezione "Testi dell'assistenza").
- **Cosa non fa:** non accede a ordini, carrello, indirizzi, profilo o altri dati personali, nemmeno quando l'utente è autenticato. Il backend non inserisce mai dati dell'utente nel contesto del modello: l'autenticazione serve solo come chiave del limiter. Per lo stato di un ordine l'assistente rimanda a "I miei ordini".
- **Chi lo usa:** clienti autenticati e ospiti.
- **Domande fuori tema:** rifiuto garbato con una frase fissa, «Posso aiutarti solo con i prodotti e i servizi di Casa del Silicio.», e un invito a chiedere di un prodotto.
- **Fonte di prezzi e disponibilità:** il database, non il modello. Il modello può citare prezzo e disponibilità solo come li riceve dai tool; le card mostrate all'utente arrivano dalla `ProductResource`.

## Architettura

Come il `PaymentGateway` (skill `ecommerce-domain`, "Pagamenti"):

- Interfaccia `App\Contracts\AiAssistant`:
  - `isAvailable(): bool` — Ollama raggiungibile e modello del negozio presente
  - un metodo di streaming che riceve i messaggi della conversazione, le definizioni dei tool e i dati di contesto, e restituisce una sequenza di **eventi**: frammenti di testo e richieste di tool call (nome e argomenti). Chi chiama esegue i tool e rilancia il metodo con i risultati come messaggi `tool`, finché il modello non risponde solo con testo. La responsabilità è fissata qui; la firma esatta si fissa nella Fase 2c.
- Implementazioni in `App\Services\Assistant`:
  - `OllamaAssistant`, con il client HTTP di Laravel (`Http`) verso l'API di Ollama (`/api/chat` con `stream: true`, `/api/tags` per lo stato). Nessuna libreria aggiuntiva.
  - `FakeAiAssistant`, programmabile nei test: testo in frammenti, tool call con argomenti, slug citati, indisponibilità, errore a metà e timeout. Registra i messaggi ricevuti, così i test verificano anche i risultati dei tool restituiti al modello.
- Binding in `AppServiceProvider` guidato da `ASSISTANT_DRIVER` (`ollama` o `fake`).
- Un'Action coordina la richiesta: slot di concorrenza, chiamata al modello, giri dei tool, estrazione degli slug e caricamento dei prodotti. Il controller resta sottile (skill `laravel-api`).

`config/assistant.php`:

| Chiave | Valore iniziale | Uso |
|---|---|---|
| `driver` | `env('ASSISTANT_DRIVER', 'ollama')` | implementazione dell'interfaccia |
| `base_url` | `http://ollama:11434` | indirizzo di Ollama nella rete Docker |
| `model` | nome del modello del negozio creato dal Modelfile | modello usato in `/api/chat` |
| `connect_timeout` | pochi secondi | Ollama spento o irraggiungibile |
| `timeout` | tempo massimo della risposta completa | comprende **tutti** i giri di `/api/chat`, anche quelli dei tool, non una sola chiamata; oltre questo, evento `error` |
| `max_messages` | 10 | messaggi accettati per richiesta |
| `max_message_length` | 500 | caratteri di un messaggio `user` |
| `max_assistant_message_length` | 4000 | caratteri di un messaggio `assistant` inviato dal client; pone un limite al contesto anche se il client falsifica la cronologia |
| `max_tool_results` | 8 | prodotti restituiti al modello da una chiamata di `search_products` |
| `max_tool_rounds` | numero massimo di giri di tool call per risposta | oltre, si chiede al modello la risposta finale senza tool |
| `max_products` | numero massimo di prodotti citati restituiti come card | |
| `max_concurrent` | 1 | generazioni contemporanee, da misurare nella Fase 2c |

I valori marcati come "pochi secondi" o descritti a parole si fissano nella Fase 2c, dopo le misure.

## Dati del catalogo

La scelta tra le due strade si fa con una prova nella Fase 2c e si scrive in `docs/assistant/model-evaluation.md`.

**Strada principale, tool calling.** Il backend dichiara al modello due tool:

| Tool | Parametri | Effetto |
|---|---|---|
| `search_products` | `search`, `category` (slug), `min_price` e `max_price` in euro, `in_stock`, `new`, `sort` (`newest`, `price_asc`, `price_desc`) | ricerca nel catalogo con gli stessi filtri di `IndexProductRequest`; il backend converte i prezzi in centesimi e restituisce al massimo `max_tool_results` prodotti |
| `get_product` | `slug` | dettaglio di un prodotto con descrizione breve e scheda tecnica |

- I tool girano su un'Action che riusa gli scope del modello `Product` (`active`, `search`, `inCategory`, `priceBetween`, `inStock`, `newArrivals`, `sortBy`): vedono solo prodotti attivi e non eliminati, come il catalogo pubblico.
- Gli argomenti proposti dal modello si validano come un input dell'utente: valori non ammessi si scartano, mai una query costruita dal testo del modello.
- Il risultato torna al modello come JSON compatto: nome, slug, categoria, prezzo formattato, etichetta di disponibilità, descrizione breve e, nel dettaglio, scheda tecnica.
- Le categorie disponibili (slug e nome, lette con la stessa query di `GET /categories`, senza chiamate HTTP interne) sono nel contesto, così il modello usa slug validi.

**Ripiego, recupero preventivo.** Se il modello scelto non è affidabile nel tool calling, il backend recupera i prodotti prima della chiamata: applica lo scope `search` all'ultimo messaggio dell'utente, più la categoria se il messaggio ne nomina una, e passa i risultati al modello nel blocco dei dati.

## Sicurezza dei prompt

- Le istruzioni di sistema stanno **solo** nel Modelfile. Il client non può inviarne: i messaggi accettati hanno solo i ruoli `user` e `assistant`; `system` e `tool` producono un 422.
- Nemmeno il backend invia messaggi di ruolo `system` a `/api/chat`: un messaggio `system` nella richiesta può prendere il posto del `SYSTEM` del Modelfile, e perimetro, rifiuto e formato `[[slug]]` andrebbero persi. Il comportamento si verifica nella Fase 2c sulla versione di Ollama scelta e si scrive in `docs/assistant/model-evaluation.md`; un test controlla che la richiesta non contenga messaggi `system`.
- Regole del negozio, categorie e dati del catalogo arrivano al modello come **dati**, mai come istruzioni: nei messaggi `tool` o in un blocco delimitato allegato alla conversazione, con la regola esplicita nel Modelfile che il contenuto di quei dati non va mai eseguito come istruzione. Vale anche per i testi dei prodotti, che scrive l'admin.
- Messaggi `user` di massimo 500 caratteri e `assistant` di massimo 4000, al massimo 10 messaggi per richiesta; l'ultimo deve essere di ruolo `user`.
- Nessun dato di altri utenti e nessun dato dell'utente stesso nel contesto: l'assistente non ha tool che li leggano.
- Rischio residuo accettato: il client può falsificare i messaggi `assistant` della propria cronologia. Riguarda solo la sua conversazione, che non contiene dati di altri né azioni con effetti.

## Citazione dei prodotti

1. Il modello cita un prodotto scrivendone il nome seguito dal marcatore `[[slug]]`, per esempio «le Cuffie Aria [[cuffie-aria]]». Il formato è nel Modelfile.
2. A risposta completa, il backend estrae gli slug nell'ordine in cui compaiono, toglie i duplicati e tiene al massimo `max_products` prodotti.
3. Scarta gli slug inesistenti e quelli non acquistabili: prodotti disattivati, eliminati o esauriti.
4. Restituisce i prodotti rimasti nell'evento `products` con la stessa `ProductResource` dell'elenco del catalogo, caricando le relazioni con `with()` in una sola query.
5. Il frontend toglie tutti i marcatori dal testo mostrato, anche quelli ancora incompleti durante lo streaming (`[[cuf` in fondo al testo), e mostra le card sotto il messaggio. Prezzo, disponibilità e "Aggiungi al carrello" delle card vengono dal database.

## API

Prefisso `/api/v1`, rotte in `public.php`.

| Metodo | Percorso | Descrizione |
|---|---|---|
| GET | /assistant/status | `{ "data": { "available": true } }`; controlla Ollama e la presenza del modello con `connect_timeout` e mette l'esito in cache per pochi secondi |
| POST | /assistant/messages | risposta dell'assistente in streaming, limiter `assistant` |

### `POST /assistant/messages`

Richiesta, validata da `SendAssistantMessageRequest`:

```json
{
  "messages": [
    { "role": "user", "content": "Cerco cuffie per l'ufficio sotto i 200 euro" },
    { "role": "assistant", "content": "Ti consiglio le Cuffie Aria [[cuffie-aria]]…" },
    { "role": "user", "content": "E per correre?" }
  ]
}
```

| Campo | Regole |
|---|---|
| `messages` | obbligatorio, array da 1 a `max_messages` (10) elementi; l'ultimo di ruolo `user` |
| `messages.*.role` | `user` o `assistant` |
| `messages.*.content` | obbligatorio, stringa; massimo `max_message_length` (500) caratteri per `user` e `max_assistant_message_length` (4000) per `assistant` (le risposte del modello sono più lunghe) |

Risposta: `200` con `Content-Type: application/x-ndjson`, `Cache-Control: no-cache` e `X-Accel-Buffering: no`, un oggetto JSON per riga:

```
{"type":"text","delta":"Ti consiglio "}
{"type":"text","delta":"le Cuffie Aria Sport [[cuffie-aria-sport]]…"}
{"type":"products","data":[{ …ProductResource… }]}
{"type":"done"}
```

- In caso di timeout durante lo streaming: `{"type":"error","message":"L'assistente non ha risposto in tempo. Riprova tra poco."}`; per un altro errore: `{"type":"error","message":"Si è verificato un problema con l'assistente. Riprova tra poco."}`. Poi la risposta si chiude. Gli eventi `text` già inviati restano visibili.
- Errori prima dello streaming, con la forma uniforme della skill `laravel-api`:

| Caso | Status | `code` | Messaggio |
|---|---|---|---|
| Ollama irraggiungibile | 503 | `assistant_unavailable` | «L'assistente non è disponibile in questo momento.» |
| Nessuno slot libero | 503 | `assistant_busy` | «L'assistente è occupato, riprova tra poco.» |
| Dati non validi | 422 | — | errori sui campi |
| Oltre il limite | 429 | — | messaggio in italiano e `Retry-After` |

- La rotta è stateful come le altre della SPA: il frontend invia l'header `X-XSRF-TOKEN`, e un ospite ottiene prima il cookie con `GET /sanctum/csrf-cookie`.

## Limiti

### Rate limiting

Limiter `assistant` in `AppServiceProvider`, per utente se autenticato e per IP se ospite. Valore iniziale: 10 richieste al minuto. Oltre il limite, 429 con `Retry-After`, come gli altri limiter (skill `account-security`).

### Concorrenza

- Il backend gira con `php artisan serve` e 4 worker, e una risposta in streaming su CPU occupa un worker per decine di secondi. Senza un limite, poche conversazioni bloccherebbero tutto il negozio.
- `config('assistant.max_concurrent')`, valore iniziale 1, da misurare nella Fase 2c.
- Prima di contattare Ollama, l'Action prende uno slot con un lock atomico della cache: prova `Cache::lock('assistant:slot:{i}', $ttl)->get()` per `i` da 1 a `max_concurrent` e usa il primo libero. Il driver cache `database` supporta i lock atomici con la tabella `cache_locks`.
- Con lo slot preso, l'Action apre la prima chiamata a `/api/chat` con `connect_timeout` **prima** di restituire la risposta in streaming: se la connessione non riesce, la risposta è il 503 `assistant_unavailable`, con gli header ancora da inviare. Il valore in cache di `isAvailable()` serve solo a `GET /assistant/status`, non a questa decisione.
- Lo slot passa poi alla callback dello `StreamedResponse`, che lo possiede fino alla fine.
- La durata del lock è `timeout` più un margine, e `timeout` comprende tutti i giri dei tool: se un worker muore senza rilasciarlo, lo slot si libera da solo.
- **Ogni uscita rilascia lo slot:**
  - prima dello streaming (503 `assistant_unavailable`, eccezione imprevista), in un `try`/`finally` dell'Action, che lo rilascia solo se non lo ha già passato alla callback
  - durante lo streaming, in un `finally` della callback: fine normale, errore, timeout o disconnessione del client. La risposta usa `ignore_user_abort(true)` e controlla `connection_aborted()` tra un frammento e l'altro, così la generazione si interrompe e il `finally` viene eseguito.
- Nessuno slot libero: 503 immediato con `code` `assistant_busy`, senza attesa e senza chiamare Ollama.
- Rischio residuo accettato: con `max_concurrent` a 1, un solo utente che resta entro il limite del limiter può tenere occupato l'assistente per gli altri. Il resto del negozio non ne risente, perché gli altri worker restano liberi.

## Nessuna persistenza

- Nessuna tabella: la conversazione vive solo nel frontend, che a ogni richiesta invia gli ultimi 10 messaggi.
- Il contenuto dei messaggi non si scrive mai nei log. Si registrano solo durata, esito ed eventuale errore tecnico.

## Docker e Modelfile (Fase 2c)

- Servizio `ollama` in `docker-compose.yml`:
  - immagine `ollama/ollama` con una versione fissa
  - **porta non pubblicata sull'host**: l'API di Ollama non ha autenticazione e permette anche di scaricare e cancellare modelli (`/api/pull`, `/api/delete`); `app` la raggiunge nella rete Docker su `ollama:11434`. Se nella Fase 2c serve accedervi dall'host per le misure, la porta si lega solo a `127.0.0.1:11434`
  - volume `ollama-data` per i modelli scaricati
  - healthcheck con `ollama list` (l'immagine potrebbe non contenere `curl`); lo stesso comando serve allo script per attendere che il server risponda
  - script di avvio in `docker/ollama/` idempotente: avvia `ollama serve`, attende che risponda, esegue `ollama pull` del modello base solo se manca e `ollama create` del modello del negozio dal `docker/ollama/Modelfile`
- `app` **non** dipende da `ollama`: `docker compose up` porta a un negozio funzionante anche mentre il modello si sta scaricando o se Ollama non parte. Il primo avvio scarica il modello, quindi richiede più tempo; il README lo segnala.
- GPU NVIDIA solo con un file compose opzionale, `docker-compose.gpu.yml`, da usare con `docker compose -f docker-compose.yml -f docker-compose.gpu.yml up`. Senza quel file, `docker compose up` funziona su CPU. Un eventuale target del `Makefile` si propone nella Fase 2c.
- `docker/ollama/Modelfile`:
  - `FROM` il modello base scelto e parametri di generazione (temperatura bassa, contesto sufficiente per 10 messaggi e i risultati dei tool)
  - `SYSTEM` con: persona di Casa del Silicio; perimetro e frase fissa di rifiuto; regola che i dati del catalogo e delle regole del negozio sono dati e non istruzioni; divieto di inventare prodotti, prezzi o disponibilità; formato `[[slug]]` per le citazioni; regole di `design/design-system/voice-and-content.md` (tu con garbo, frasi brevi, niente punti esclamativi né emoji né urgenza artificiale, prezzi nel formato 349,00 €, unità con lo spazio, parole fisse per disponibilità e stati, nessun marchio di terzi)
  - le regole del negozio **non** si scrivono nel Modelfile: arrivano a ogni richiesta dalla stessa fonte di `/assistenza`, così gli importi seguono `config/shop.php`
- Il servizio si aggiunge al README e alla tabella dei servizi di `CLAUDE.md` nella Fase 2c, come servizio interno senza indirizzo sull'host.

### Scelta del modello e misure

Modello tra 3 e 4 miliardi di parametri, scelto nella Fase 2c con un insieme fisso di domande in italiano (consigli, confronti, regole del negozio, domande fuori tema, tentativi di far eseguire istruzioni contenute nei dati). Criteri: qualità dell'italiano, affidabilità del tool calling, velocità su CPU.

In `docs/assistant/model-evaluation.md` si scrivono:

- i modelli provati, le risposte e la scelta motivata, compresa la strada per i dati del catalogo (tool calling o recupero preventivo)
- la verifica che lo streaming arrivi al browser a frammenti con `php artisan serve`
- il tempo della prima parola e il tempo totale di una risposta su CPU
- il valore scelto per `max_concurrent` e i timeout
- il comportamento di un messaggio `system` inviato a `/api/chat` rispetto al `SYSTEM` del Modelfile, sulla versione di Ollama scelta
- l'effetto di `OLLAMA_KEEP_ALIVE`, perché lo scaricamento del modello dalla memoria allunga il tempo della prima parola, e la RAM necessaria, da riportare nel README

## Frontend (Fase 8)

Aspetto e testi dal Design E; qui le regole di architettura (skill `react-architecture`).

- Feature `features/assistant/` con `api/`, `hooks/` e `components/`.
- Pulsante fisso in basso a destra, in `StoreLayout`, che apre il pannello della chat. Non compare nell'area admin.
- Pannello con suggerimenti di domande quando la conversazione è vuota e la nota «Le risposte sono generate da un'AI: prezzi e disponibilità sono quelli delle schede dei prodotti.»
- Stato della conversazione in un `AssistantProvider` sotto `StoreLayout`, così resta aperta mentre si naviga nel negozio. Solo in memoria: niente `localStorage`, `sessionStorage`, URL o cache di TanStack Query. Ricaricando la pagina riparte vuota.
- Il campo del messaggio accetta al massimo 500 caratteri e mostra il conteggio; si inviano gli ultimi 10 messaggi.
- Streaming con `fetch` e `ReadableStream` (axios nel browser non legge una risposta a frammenti), in una funzione di `features/assistant/api/`. È l'unica eccezione alla regola che le funzioni API usano `httpClient` (skill `react-architecture`), quindi replica a mano ciò che fa l'interceptor:
  - `credentials: 'include'` e header `Accept`, `Content-Type` e `X-XSRF-TOKEN`, con il valore del cookie `XSRF-TOKEN` decodificato con `decodeURIComponent`
  - `ensureCsrfCookie()` prima del primo invio da ospite
  - su un 419 (cookie CSRF scaduto, anche per un utente autenticato): nuovo `ensureCsrfCookie()` e **un solo** nuovo tentativo; un secondo 419 si mostra come errore
  - le risposte non 200 si normalizzano con `normalizeApiError`, così messaggi, `code` e `retryAfter` hanno la stessa forma del resto dell'app
  - un `AbortController` interrompe la richiesta alla chiusura del pannello, senza mostrare errori
- Il parser legge le righe NDJSON, accumula i `delta`, toglie i marcatori `[[slug]]` e mostra le card dall'evento `products`.
- `useAssistantStatus()` legge `GET /assistant/status` con TanStack Query. Con `available: false` o in caso di errore, il pulsante mostra «Assistente non disponibile» ed è disabilitato; il resto del negozio funziona normalmente.
- Errori: 503 `assistant_busy` e `assistant_unavailable`, 429 ed evento `error` si mostrano nel pannello con il messaggio in italiano, senza perdere la conversazione e senza bloccare il resto del negozio.
- Le card riusano il componente del catalogo. "Aggiungi al carrello" segue le regole del catalogo: un ospite passa dal login.

## Test

Casi obbligatori nella skill `testing`, sezione "Assistente AI". Regole:

- Nei test `AiAssistant` si sostituisce con `FakeAiAssistant` tramite il container.
- `OllamaAssistant` si testa con `Http::fake()` e `Http::preventStrayRequests()`: nessuna chiamata reale a Ollama, né nel backend né nel frontend (MSW).
- Il corpo delle risposte in streaming si legge con `streamedContent()`: senza, la callback non viene eseguita e i test su eventi e rilascio dello slot non verificano niente.
- Il Modelfile non si testa in modo automatico: la sua qualità si verifica con le domande della Fase 2c.
