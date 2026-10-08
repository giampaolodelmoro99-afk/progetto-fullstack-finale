---
name: ai-assistant
description: Assistente AI per i clienti del negozio con un modello locale Ollama — perimetro (prodotti, confronti, regole del negozio, nessun dato personale), interfaccia AiAssistant con implementazione Ollama e finta, tool calling sulle ricerche del catalogo, citazione dei prodotti per slug con card dal database, streaming NDJSON, limiti di messaggi, rate limiting e concorrenza, sicurezza dei prompt, servizio Ollama in Docker Compose con Modelfile e GPU opzionale, scelta del modello, widget del frontend e test senza chiamate reali. Usala per qualsiasi lavoro sull'assistente, nel backend, nel frontend o in docker/ollama.
---

# Assistente AI

Assistente per i clienti di Casa del Silicio, basato su un modello locale servito da Ollama. Si realizza in tre fasi: 2c (infrastruttura e scelta del modello), 4b (backend) e 8 (widget), con l'interfaccia del Design E, `design/mockups/05-assistente-account.pdf` (`docs/roadmap.md`).

## Perimetro

- **Cosa fa:** aiuta a scegliere i prodotti, risponde su caratteristiche e confronti, spiega le regole del negozio (spedizione, resi, garanzia, contatti) con gli stessi testi della pagina `/assistenza`, letti dalla stessa fonte (`GET /shop/support`, skill `ecommerce-domain`, sezione "Testi dell'assistenza").
- **Cosa non fa:** non accede a ordini, carrello, indirizzi, profilo o altri dati personali, nemmeno quando l'utente è autenticato. Il backend non inserisce mai dati dell'utente nel contesto del modello: l'autenticazione serve solo come chiave del limiter. Per lo stato di un ordine l'assistente rimanda a "I miei ordini".
- **Chi lo usa:** clienti autenticati e ospiti. Per un admin il widget non compare nel negozio (Design E); l'API non lo vieta, perché non espone dati.
- **Domande fuori tema:** rifiuto garbato con una frase fissa, «Posso aiutarti solo con i prodotti e i servizi di Casa del Silicio.», e un invito a chiedere di un prodotto.
- **Fonte di prezzi e disponibilità:** il database, non il modello. Il modello può citare prezzo e disponibilità solo come li riceve dai tool; le card mostrate all'utente arrivano dalla `ProductResource`.

## Architettura

Come il `PaymentGateway` (skill `ecommerce-domain`, "Pagamenti"):

- Interfaccia `App\Contracts\AiAssistant`:
  - `isAvailable(): bool` — Ollama raggiungibile e modello del negozio presente
  - `stream(array $messages, array $tools = [], ?CarbonInterface $deadline = null): iterable` riceve i messaggi della conversazione e le definizioni dei tool e restituisce una sequenza di **eventi** (`App\Services\Assistant\Events`): `TextDelta` per i frammenti di testo e `ToolCallRequested` per le richieste di tool call (nome e argomenti). La richiesta HTTP parte **dentro** `stream()`, che non contiene `yield` e restituisce il generatore di un metodo separato: così Ollama irraggiungibile produce `AssistantUnavailableException` prima dello streaming. Durante l'iterazione: `AssistantTimeoutException` oltre la scadenza, `AssistantFailedException` per ogni altro errore.
  - `AssistantConversation` esegue i giri: chiama `stream()`, esegue i tool con `AssistantTools`, aggiunge il messaggio `assistant` con le tool call e i messaggi `tool` con i risultati, e rilancia finché il modello non risponde solo con testo; oltre `max_tool_rounds` rilancia senza tool. Una sola scadenza copre tutti i giri. L'Action della Fase 4b la riusa, come il comando `assistant:ask`.
- Implementazioni in `App\Services\Assistant`:
  - `OllamaAssistant`, con il client HTTP di Laravel (`Http`) verso l'API di Ollama (`/api/chat` con `stream: true`, `/api/tags` per lo stato). Nessuna libreria aggiuntiva.
  - `FakeAiAssistant`, programmabile nei test a turni (`respondWith()`, `failAfter()`, `timeoutAfter()`, `unavailable()`): testo in frammenti, tool call con argomenti, slug citati, indisponibilità, errore a metà e timeout. Registra messaggi e tool ricevuti (`calls()`), così i test verificano anche i risultati dei tool restituiti al modello.
  - `AssistantSlots` per gli slot di concorrenza (sezione "Concorrenza").
- Binding in `AppServiceProvider` guidato da `ASSISTANT_DRIVER` (`ollama` o `fake`).
- La richiesta passa da tre classi, così il controller resta sottile (skill `laravel-api`):
  - `App\Actions\Assistant\StartAssistantAnswerAction` prende lo slot di concorrenza e apre il primo giro con `AssistantConversation::run()`; restituisce un `AssistantAnswer` (slot, eventi, inizio)
  - `App\Services\Assistant\AssistantAnswerStream` costruisce lo `StreamedResponse` NDJSON, scrive gli eventi, chiama `CitedProducts` a risposta completa e rilascia lo slot
  - `App\Services\Assistant\CitedProducts` estrae gli slug e carica i prodotti (sezione "Citazione dei prodotti")
- `AssistantLog` scrive l'unica riga di log di una risposta (sezione "Nessuna persistenza").
- `GetAssistantStatusAction` risponde a `GET /assistant/status`, con l'esito di `isAvailable()` in cache per 10 secondi.

`config/assistant.php`:

| Chiave | Valore iniziale | Uso |
|---|---|---|
| `driver` | `env('ASSISTANT_DRIVER', 'ollama')` | implementazione dell'interfaccia |
| `base_url` | `http://ollama:11434` | indirizzo di Ollama nella rete Docker |
| `model` | nome del modello del negozio creato dal Modelfile | modello usato in `/api/chat` |
| `think` | `false` | inviato a `/api/chat`: disattiva il ragionamento di Qwen3.5 (`null` per non inviarlo) |
| `connect_timeout` | 3 s | Ollama spento o irraggiungibile, per `/api/tags`; la richiesta in streaming a `/api/chat` usa lo `StreamHandler` di Guzzle, che lo ignora: lì la connessione è limitata da `timeout`, e Ollama spento produce comunque un rifiuto immediato |
| `timeout` | 120 s | tempo massimo della risposta completa; comprende **tutti** i giri di `/api/chat`, anche quelli dei tool, non una sola chiamata; oltre questo, evento `error` |
| `max_messages` | 10 | messaggi accettati per richiesta |
| `max_message_length` | 500 | caratteri di un messaggio `user` |
| `max_assistant_message_length` | 4000 | caratteri di un messaggio `assistant` inviato dal client; pone un limite al contesto anche se il client falsifica la cronologia |
| `max_tool_results` | 8 | prodotti restituiti al modello da una chiamata di `search_products` |
| `max_tool_rounds` | 3 | giri di tool call per risposta; oltre, si chiede al modello la risposta finale senza tool |
| `lock_margin` | 30 s | aggiunto a `timeout` per la durata di uno slot di concorrenza |
| `max_products` | 4 | numero massimo di prodotti citati restituiti come card |
| `max_concurrent` | 1 | generazioni contemporanee, misurato nella Fase 2c |

I valori sono stati fissati nella Fase 2c dopo le misure, riportate in `docs/assistant/model-evaluation.md`.

## Dati del catalogo

Scelta della Fase 2c (`docs/assistant/model-evaluation.md`): **tool calling**, con il modello qwen3.5:4b.

**Tool calling.** Il backend dichiara al modello tre tool, definiti in `App\Services\Assistant\AssistantTools`. Non c'è nessun altro dato allegato alla conversazione: tutto ciò che il modello sa del negozio arriva dal Modelfile o dai risultati dei tool, così il prompt fisso resta corto e la prima parola arriva prima.

| Tool | Parametri | Effetto |
|---|---|---|
| `search_products` | `search` (una parola), `category` (enum degli slug), `min_price` e `max_price` in euro, `in_stock`, `new`, `sort` (`newest`, `price_asc`, `price_desc`) | ricerca nel catalogo con gli stessi filtri di `IndexProductRequest`; il backend converte i prezzi in centesimi e restituisce al massimo `max_tool_results` prodotti, con `total` (prodotti trovati) e, se la lista è parziale, una `note` con il solo fatto («Mostrati 8 prodotti su 10.»): cosa fare con una lista parziale è scritto nel Modelfile, perché i risultati sono dati e mai istruzioni (Fase 4b, `docs/assistant/model-evaluation.md`) |
| `get_product` | `slug` | dettaglio di un prodotto con descrizione breve e scheda tecnica |
| `get_shop_info` | `section` facoltativo (`spedizioni`, `resi`, `garanzia`, `contatti`) | sezioni della pagina `/assistenza` da `GetSupportSectionsAction`, la stessa fonte di `GET /shop/support`: una sola sezione o tutte |

- I tool del catalogo girano su `SearchProductsAction`, che riusa gli scope del modello `Product` (`active`, `search`, `inCategory`, `priceBetween`, `inStock`, `newArrivals`, `sortBy`): vedono solo prodotti attivi e non eliminati, come il catalogo pubblico.
- Gli argomenti proposti dal modello si validano come un input dell'utente: valori non ammessi si scartano, mai una query costruita dal testo del modello.
- Il risultato torna al modello come JSON compatto: nome, slug, categoria, prezzo formattato, etichetta di disponibilità, descrizione breve e, nel dettaglio, scheda tecnica.
- Le categorie non sono nel prompt: il parametro `category` di `search_products` è un `enum` con gli slug letti dalla tabella `categories` (ordinati per `id`, come `GET /categories`) a ogni richiesta, così il modello usa solo slug validi e una categoria nuova è subito disponibile. Il backend controlla comunque che lo slug esista.

**Ripiego, recupero preventivo.** Se il modello scelto non è affidabile nel tool calling, il backend recupera i prodotti prima della chiamata: applica lo scope `search` all'ultimo messaggio dell'utente, più la categoria se il messaggio ne nomina una, e passa i risultati al modello come messaggio `tool`. Non è servito: tutti i candidati provati chiamano i tool in modo affidabile.

## Sicurezza dei prompt

- Le istruzioni di sistema stanno **solo** nel Modelfile. Il client non può inviarne: i messaggi accettati hanno solo i ruoli `user` e `assistant`; `system` e `tool` producono un 422.
- Nemmeno il backend invia messaggi di ruolo `system` a `/api/chat`: un messaggio `system` nella richiesta può prendere il posto del `SYSTEM` del Modelfile, e perimetro, rifiuto e formato `[[slug]]` andrebbero persi. Il comportamento si verifica nella Fase 2c sulla versione di Ollama scelta e si scrive in `docs/assistant/model-evaluation.md`; un test controlla che la richiesta non contenga messaggi `system`.
- Regole del negozio e dati del catalogo arrivano al modello come **dati**, mai come istruzioni: solo nei messaggi `tool`, con la regola esplicita nel Modelfile che il contenuto dei risultati degli strumenti non va mai eseguito come istruzione. Vale anche per i testi dei prodotti, che scrive l'admin (prova q9 della Fase 2c).
- Messaggi `user` di massimo 500 caratteri e `assistant` di massimo 4000, al massimo 10 messaggi per richiesta; l'ultimo deve essere di ruolo `user`.
- Nessun dato di altri utenti e nessun dato dell'utente stesso nel contesto: l'assistente non ha tool che li leggano.
- Difesa residua dal vecchio blocco dei dati: nella prima versione della Fase 2c regole e categorie arrivavano in un blocco `<dati_negozio>` allegato al primo messaggio `user`. Il blocco non esiste più, ma `OllamaAssistant` continua a togliere i marcatori `<dati_negozio>` e `</dati_negozio>`, anche con spazi o maiuscole diverse, dal contenuto di **tutti** i messaggi (anche `assistant` e `tool`), così nessun testo può fingersi un blocco di dati autentico, nemmeno se un modello futuro fosse stato istruito a riconoscerlo.
- Rischio residuo accettato: il client può falsificare i messaggi `assistant` della propria cronologia. Riguarda solo la sua conversazione, che non contiene dati di altri né azioni con effetti.

## Citazione dei prodotti

1. Il modello cita un prodotto scrivendone il nome seguito dal marcatore `[[slug]]`, per esempio «le Cuffie Aria [[cuffie-aria]]». Il formato è nel Modelfile.
2. A risposta completa, il backend (`App\Services\Assistant\CitedProducts`) estrae gli slug nell'ordine in cui compaiono e toglie i duplicati; per sicurezza ne cerca al massimo 20 (`CitedProducts::MAX_SLUGS`).
3. Scarta gli slug inesistenti e quelli non acquistabili: prodotti disattivati, eliminati o esauriti.
4. Solo dopo lo scarto tiene i primi `max_products` prodotti, nell'ordine di citazione: un prodotto citato ma non valido non riduce le card mostrate (cinque slug con il primo esaurito danno quattro card).
5. Restituisce i prodotti rimasti nell'evento `products` con la stessa `ProductResource` dell'elenco del catalogo, caricando le relazioni con `with()` in una sola query. L'evento `products` c'è sempre prima di `done`, anche con `data` vuoto.
6. Il frontend toglie tutti i marcatori dal testo mostrato, anche quelli ancora incompleti durante lo streaming (`[[cuf` in fondo al testo), e mostra le card sotto il messaggio. Prezzo, disponibilità e "Aggiungi al carrello" delle card vengono dal database.

## API

Prefisso `/api/v1`, rotte in `public.php`.

| Metodo | Percorso | Descrizione |
|---|---|---|
| GET | /assistant/status | `{ "data": { "available": true } }`; controlla Ollama e la presenza del modello con `connect_timeout` e mette l'esito in cache per 10 secondi (`GetAssistantStatusAction::CACHE_SECONDS`) |
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
- **Dalla Fase 8** (decisione del Design E, `docs/backlog.md`): un solo messaggio per entrambi i casi, `{"type":"error","message":"La risposta si è interrotta prima della fine."}`, quello del mockup, perché l'evento arriva dopo l'inizio della risposta. Il testo fisso sta solo in `AssistantAnswerStream`, che lo scrive al posto del messaggio dell'eccezione; `AssistantTimeoutException`, `AssistantFailedException` e il comando `assistant:ask` mantengono i loro messaggi. La distinzione tra timeout ed errore resta nel log (`AssistantLog`, esito `timeout` o `failed`). Se il timeout scatta durante i giri dei tool, prima di qualsiasi testo, la frase compare da sola: è accettato.
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
- `config('assistant.max_concurrent')`, fissato a 1 nella Fase 2c (`docs/assistant/model-evaluation.md`): Ollama serve una generazione alla volta, e una seconda richiesta resterebbe in coda occupando un worker.
- Prima di contattare Ollama, l'Action prende uno slot con un lock atomico della cache: prova `Cache::lock('assistant:slot:{i}', $ttl)->get()` per `i` da 1 a `max_concurrent` e usa il primo libero. Il driver cache `database` supporta i lock atomici con la tabella `cache_locks`.
- Con lo slot preso, l'Action apre la prima chiamata a `/api/chat` (con `AssistantConversation::run()`) **prima** di restituire la risposta in streaming: se la connessione non riesce, la risposta è il 503 `assistant_unavailable`, con gli header ancora da inviare. Il valore in cache di `isAvailable()` serve solo a `GET /assistant/status`, non a questa decisione.
- Lo slot passa poi alla callback dello `StreamedResponse`, che lo possiede fino alla fine.
- La durata del lock è `timeout` più un margine, e `timeout` comprende tutti i giri dei tool: se un worker muore senza rilasciarlo, lo slot si libera da solo.
- **Ogni uscita rilascia lo slot:**
  - prima dello streaming (503 `assistant_unavailable`, eccezione imprevista), in un `catch` dell'Action, che rilascia lo slot e rilancia l'eccezione; se `run()` riesce, lo slot passa all'`AssistantAnswer` e la callback ne diventa proprietaria
  - durante lo streaming, in un `finally` della callback che chiama `AssistantAnswer::finish()`: fine normale, errore, timeout o disconnessione del client. La risposta usa `ignore_user_abort(true)` e, dopo ogni riga scritta, chiede al contratto `App\Contracts\ClientConnection` (`PhpClientConnection` con `connection_aborted()`, sostituibile nei test con `Tests\Support\FakeClientConnection`) se il client si è disconnesso. In quel caso esce dal ciclo e `finish()` rilascia l'ultimo riferimento ai generatori: vengono distrutti subito, il `finally` di `OllamaAssistant` chiude il body della risposta, la connessione verso Ollama si chiude e la generazione si ferma
  - se la callback non parte mai (per esempio un middleware fallisce dopo il controller), con un `app()->terminating()` registrato da `AssistantAnswerStream::toResponse()`, che rilascia lo slot se non è già stato rilasciato. In produzione l'applicazione termina dopo l'invio del corpo; nei test HTTP termina prima di `streamedContent()`, quindi i test sul rilascio nel `finally` eseguono lo stream direttamente (helper `streamAnswerDirectly()`)
- Nessuno slot libero: 503 immediato con `code` `assistant_busy`, senza attesa e senza chiamare Ollama.
- Rischio residuo accettato: con `max_concurrent` a 1, un solo utente che resta entro il limite del limiter può tenere occupato l'assistente per gli altri. Il resto del negozio non ne risente, perché gli altri worker restano liberi.
- Rischio residuo accettato, «Interrompi» del widget: il backend si accorge della disconnessione solo dopo aver scritto una riga. Se l'utente interrompe durante l'attesa (giri dei tool, prima del primo frammento), lo slot resta occupato fino al primo frammento o alla scadenza, e una domanda inviata subito dopo riceve il 503 `assistant_busy` («L'assistente è occupato, riprova tra poco.»). È il comportamento previsto, non un difetto.

## Nessuna persistenza

- Nessuna tabella: la conversazione vive solo nel frontend, che a ogni richiesta invia gli ultimi 10 messaggi.
- Il contenuto dei messaggi non si scrive mai nei log. Si registrano solo durata, esito ed eventuale errore tecnico (`AssistantLog`). Le tracce delle eccezioni non contengono gli argomenti delle chiamate: `zend.exception_ignore_args = On` in `docker/php/php.ini`.

## Docker e Modelfile (Fase 2c)

- Servizio `ollama` in `docker-compose.yml`, nel **profilo `ai`**:
  - `docker compose up` non lo avvia e non scarica né l'immagine (3,8 GB da scaricare, 9,3 GB su disco) né il modello (3,4 GB): il negozio parte con l'assistente non disponibile, così il requisito del solo `docker compose up` non dipende da 13 GB di download
  - `docker compose --profile ai up` (`make up-ai`) avvia anche l'assistente; `make down` usa `--profile ai`, così ferma anche `ollama`
  - immagine `ollama/ollama:0.35.0`
  - **porta non pubblicata sull'host**: l'API di Ollama non ha autenticazione e permette anche di scaricare e cancellare modelli (`/api/pull`, `/api/delete`); `app` la raggiunge nella rete Docker su `ollama:11434`. Se nella Fase 2c serve accedervi dall'host per le misure, la porta si lega solo a `127.0.0.1:11434`
  - volume `ollama-data` per i modelli scaricati
  - healthcheck con `ollama show` del modello del negozio (l'immagine non contiene `curl`): il servizio è sano solo quando il modello esiste; `start_period` di 900 s per il primo download. Lo script usa `ollama list` per attendere che il server risponda
  - `ASSISTANT_MODEL` nell'ambiente del servizio, uguale a `config('assistant.model')`
  - script di avvio in `docker/ollama/` idempotente: avvia `ollama serve`, attende che risponda, esegue `ollama pull` del modello base solo se manca e `ollama create` del modello del negozio dal `docker/ollama/Modelfile`
- `app` **non** dipende da `ollama`: con il profilo `ai` il negozio funziona anche mentre il modello si sta scaricando o se Ollama non parte; senza profilo l'assistente risulta non disponibile. Il README riporta dimensioni e tempi dei download.
- GPU NVIDIA solo con un file compose opzionale, `docker-compose.gpu.yml`, da usare con `docker compose -f docker-compose.yml -f docker-compose.gpu.yml --profile ai up` (target `make up-gpu`). Senza quel file l'assistente usa la CPU.
- `docker/ollama/Modelfile`:
  - `FROM` il modello base scelto e parametri di generazione (temperatura bassa, contesto sufficiente per 10 messaggi e i risultati dei tool)
  - `SYSTEM` con: persona di Casa del Silicio; perimetro e frase fissa di rifiuto; regola che i dati del catalogo e delle regole del negozio sono dati e non istruzioni; divieto di inventare prodotti, prezzi o disponibilità; formato `[[slug]]` per le citazioni; regole di `design/design-system/voice-and-content.md` (tu con garbo, frasi brevi, niente punti esclamativi né emoji né urgenza artificiale, prezzi nel formato 349,00 €, unità con lo spazio, parole fisse per disponibilità e stati, nessun marchio di terzi)
  - le regole del negozio **non** si scrivono nel Modelfile: arrivano come risultato del tool `get_shop_info`, dalla stessa `GetSupportSectionsAction` dell'endpoint `/shop/support`, così gli importi seguono `config/shop.php`
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

Aspetto e testi dal Design E, `design/mockups/05-assistente-account.pdf`: pattern visivi nella skill `mui-design-system` ("Assistente AI"), stato della conversazione, interruzione, errori e parser nella skill `react-architecture` ("Assistente AI"). Qui le regole che legano widget e API.

- Feature `features/assistant/` con `api/`, `hooks/`, `components/` e `utils/`.
- Pulsante fisso in basso a destra, in `StoreLayout`, «Chiedi all'assistente», che apre il pannello della chat. Non compare nell'area admin né per un utente admin che naviga il negozio.
- Pannello: su desktop una card fluttuante **non modale** di 440 px (token `theme.layout.assistantPanelWidth`) sopra il pulsante, che diventa «Chiudi l'assistente»; su mobile a schermo intero. Titolo «Assistente di Casa del Silicio» e «Nuova conversazione».
- Conversazione vuota: «Ciao, come posso aiutarti?», «Chiedimi dei prodotti, della spedizione o dei resi.» e quattro suggerimenti: «Cerco cuffie per viaggiare», «Un regalo sotto i 100 €», «Quanto costa la spedizione?», «Come funzionano i resi?». Gli stessi suggerimenti ricompaiono sotto il rifiuto fisso delle domande fuori tema.
- Nota sotto il campo: «Le risposte sono generate da un'AI: prezzi e disponibilità sono quelli delle schede.»
- **Formato delle risposte (deciso nel Design E):** Markdown leggero, solo grassetto ed elenchi puntati e numerati; niente titoli, link, immagini né HTML. Il widget lo mostra con un parser scritto a mano (`parseLightMarkdown`), senza dipendenze e senza HTML: tutto ciò che non è grassetto o elenco resta testo semplice. Nella Fase 8 il `SYSTEM` del Modelfile chiede esplicitamente questo formato, e le domande fisse si riprovano con `make ask`.
- **Attesa:** fino al primo frammento «Sto preparando la risposta…» con una barra indeterminata e «Di solito servono 15–20 secondi.» (con qwen3.5 gli header arrivano solo con il primo frammento, `docs/assistant/model-evaluation.md`). Il testo non parla di catalogo, perché l'NDJSON non dice se il modello usa un tool.
- **Interrompi:** visibile durante l'attesa e durante la scrittura, nella stessa posizione; annulla la richiesta con l'`AbortController`, senza errore. Il testo arrivato resta con «Risposta interrotta.» e non entra nella cronologia inviata.
- Stato della conversazione in un `AssistantProvider` sotto `StoreLayout`, così resta aperta mentre si naviga nel negozio. Solo in memoria: niente `localStorage`, `sessionStorage`, URL o cache di TanStack Query. Ricaricando la pagina riparte vuota.
- Il campo del messaggio accetta al massimo 500 caratteri e mostra il conteggio; si inviano gli ultimi 10 messaggi.
- Streaming con `fetch` e `ReadableStream` (axios nel browser non legge una risposta a frammenti), in una funzione di `features/assistant/api/`. È l'unica eccezione alla regola che le funzioni API usano `httpClient` (skill `react-architecture`), quindi replica a mano ciò che fa l'interceptor:
  - `credentials: 'include'` e header `Accept`, `Content-Type` e `X-XSRF-TOKEN`, con il valore del cookie `XSRF-TOKEN` decodificato con `decodeURIComponent`
  - `ensureCsrfCookie()` prima del primo invio da ospite
  - su un 419 (cookie CSRF scaduto, anche per un utente autenticato): nuovo `ensureCsrfCookie()` e **un solo** nuovo tentativo; un secondo 419 si mostra come errore
  - le risposte non 200 si normalizzano con `normalizeApiError`, così messaggi, `code` e `retryAfter` hanno la stessa forma del resto dell'app
  - un `AbortController` interrompe la richiesta alla chiusura del pannello, senza mostrare errori
- Il parser legge le righe NDJSON, accumula i `delta`, toglie i marcatori `[[slug]]` e mostra le card dall'evento `products`.
- `useAssistantStatus()` legge `GET /assistant/status` con TanStack Query. Con `available: false` o in caso di errore, il pulsante è disabilitato con il tooltip «Assistente non disponibile»; il resto del negozio funziona normalmente.
- Errori, nel pannello con «Riprova», senza perdere la conversazione e senza bloccare il resto del negozio:
  - 503 `assistant_busy` («L'assistente è occupato, riprova tra poco.») e `assistant_unavailable` («L'assistente non è disponibile in questo momento.»): messaggio ricevuto dall'API
  - evento `error`: messaggio ricevuto, sotto il testo parziale
  - 429: testo locale del widget «Hai fatto troppe domande, riprova tra N secondi.», con N da `Retry-After`; «Riprova» resta disabilitato fino alla fine di `Retry-After` e il campo mostra «Potrai scrivere tra N secondi.», con il conto alla rovescia; senza `Retry-After` «Hai fatto troppe domande, riprova tra poco.», senza blocco (dettagli nella skill `react-architecture`)
  - stream chiuso senza `done` né `error`: come l'evento `error`
- La conversazione si svuota quando cambia l'utente (login e logout), come gli altri dati dell'utente.
- Le card dei prodotti citati sono un componente compatto della feature (`AssistantProductCard`: miniatura, nome, prezzo, disponibilità, "Vedi prodotto", "Aggiungi al carrello"), che riusa etichetta e colori della disponibilità e la mutation di aggiunta al carrello del catalogo. "Aggiungi al carrello" segue le regole del catalogo: un ospite passa dal login.

## Test

Casi obbligatori nella skill `testing`, sezione "Assistente AI". Regole:

- Nei test `AiAssistant` si sostituisce con `FakeAiAssistant` tramite il container.
- `OllamaAssistant` si testa con `Http::fake()` e `Http::preventStrayRequests()`: nessuna chiamata reale a Ollama, né nel backend né nel frontend (MSW).
- Il corpo delle risposte in streaming si legge con `streamedContent()`: senza, la callback non viene eseguita e i test su eventi e rilascio dello slot non verificano niente.
- Il Modelfile non si testa in modo automatico: la sua qualità si verifica con le domande della Fase 2c.
