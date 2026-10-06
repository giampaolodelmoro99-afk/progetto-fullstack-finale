---
name: testing
description: Strategia e convenzioni dei test del progetto — Pest nel backend (feature, unit, architettura, copertura con PCOV) e Vitest con React Testing Library, user-event e MSW nel frontend. Definisce cosa testare, dove mettere i test, utility condivise e comandi. Usala ogni volta che scrivi o modifichi codice, perché i test fanno parte dello stesso task.
---

# Test

## Regole generali

- I test si scrivono nello stesso task del codice che verificano, mai rimandati a dopo.
- Un task è concluso solo con `make test` e `make lint` verdi.
- Descrizioni dei test in inglese, chiare e comportamentali: `it('rejects checkout when stock is insufficient')`.
- Nessun commento inline nei test.
- Ogni test crea i propri dati e non dipende dall'ordine di esecuzione.
- I valori attesi si scrivono a mano nel test o in un helper di test (es. `frontendOrigin()` restituisce `'http://localhost:5173'`), mai ricavati dal codice o dalla configurazione che il test verifica: altrimenti il test confronta il sistema con sé stesso. Quando un valore viene dalla configurazione, un test dedicato controlla che la configurazione contenga esattamente il valore atteso.

## Comandi

| Comando | Effetto |
|---|---|
| `make test` | backend e frontend |
| `make test-backend` | Pest |
| `make test-frontend` | Vitest in modalità singola esecuzione |
| `make coverage-backend` | Pest con copertura tramite PCOV |
| `make coverage-frontend` | Vitest con `@vitest/coverage-v8` |

**Mai due esecuzioni in parallelo sullo stesso database di test.** Tutti i test backend usano `ecommerce_testing` con `RefreshDatabase`: due `make test` (o `make test-backend`, `make coverage-backend`) lanciati insieme, anche da un subagent come `code-reviewer`, ricreano le tabelle nello stesso momento e falliscono a caso («Table 'users' already exists», «Failed to open the referenced table»). Si lanciano uno dopo l'altro; un fallimento di questo tipo non è un errore del codice e si verifica rilanciando da soli.

Obiettivo di copertura: almeno 80% nel backend e 70% nel frontend, con Action, Policy e hook critici vicini al 100%.

## Backend — Pest

### Struttura

```
tests/
├── Pest.php                     # configurazione e helper
├── Arch/ArchitectureTest.php
├── Feature/Api/V1/{Area}/       # un file per endpoint o flusso
└── Unit/                        # enum, calcoli, servizi isolati
```

### Configurazione

- Database MySQL `ecommerce_testing`, configurato in `.env.testing` e `phpunit.xml`.
- `RefreshDatabase` per tutti i test in `Feature`.
- Helper in `Pest.php`: `actingAsCustomer()`, `actingAsAdmin()`, `actingAsVerifiedCustomer()`, `confirmIdentity()` che imposta la conferma dell'identità in sessione.
- Fake a seconda del caso: `Notification::fake()`, `Mail::fake()`, `Event::fake()`, `Storage::fake('public')`.
- Nell'ambiente di test `uncompromised()` è disattivato.
- Il TOTP si testa generando codici validi con la stessa libreria usata dal codice.
- La crittografia delle passkey (`PasskeyCeremony`), il `PaymentGateway` e l'`AiAssistant` si sostituiscono con implementazioni finte tramite il container. Nessun test chiama Ollama.
- Passkey: `fakePasskeys()` in `Pest.php` collega `Tests\Support\FakePasskeyCeremony` per i test dei flussi; la conservazione delle opzioni in sessione (monouso, scadenza, scopo) resta reale. Helper `passkeyFor()`, `passkeyAnswer()` e `passkeyRegistrationAnswer()`. I test d'integrazione con la libreria reale (`tests/Feature/Security/PasskeyCeremonyTest.php`) usano `Tests\Support\SoftwareAuthenticator`, un autenticatore in software (chiave ES256 con openssl, attestazione `none` in CBOR con `spomky-labs/cbor-php`, dipendenza di sviluppo) che risponde alle opzioni dell'API come un browser e permette di cambiare origine, verifica dell'utente, challenge e chiave.

### Cosa testare per ogni endpoint

| Caso | Atteso |
|---|---|
| Richiesta corretta | status corretto e struttura JSON (`assertJsonStructure`, `assertJsonPath`) |
| Dati non validi | 422 con `errors` sui campi giusti; usa i `dataset` di Pest per coprire più casi |
| Utente non autenticato | 401 |
| Utente non autorizzato | 403 per i permessi di ruolo (cliente su rotta admin); 404 per le risorse personali di un altro utente (indirizzi, righe del carrello, ordini, passkey), con `denyAsNotFound()` nella Policy |
| Risorsa inesistente | 404 dove applicabile |
| Effetti collaterali | database (`assertDatabaseHas`), notifiche, eventi, file |

### Catalogo

- Filtri di `GET /products` (`category`, `min_price`, `max_price`, `in_stock`, `new`) con un dataset di casi, e 422 per valori non validi (es. `sort=popular`).
- Ordinamenti `newest` (predefinito), `price_asc` e `price_desc`, compreso l'ordine stabile a parità di valore.
- `meta.price_range` calcolato sulla categoria e non influenzato da prezzo, disponibilità e novità.
- `is_new` ai bordi della finestra: creato 29 giorni fa sì, 31 giorni fa no (con `travelTo()`).
- Prodotti correlati: massimo 4, stessa categoria, esclusi il prodotto corrente, gli esauriti e i disattivati; meno di 4 quando la categoria non ne ha abbastanza.
- Prodotti disattivati o eliminati: assenti dall'elenco e 404 sul dettaglio.

### Carrello

- `summary.items_count` somma le quantità di tutte le righe, comprese quelle esaurite, disattivate o eliminate.
- Subtotale, spedizione e totale escludono le righe non acquistabili; una riga con quantità oltre lo stock resta nei totali.
- Spedizione: 690 sotto la soglia, 0 da 9900 in su; `free_shipping_remaining` con un dataset (subtotale 5800 → 4100, 9900 → 0, 12000 → 0); con sole righe esaurite (subtotale 0) spedizione 0, totale 0 e `free_shipping_remaining` null.
- Totali del Design B: 34900 + 2 × 5900 + 2900 = 49600 con spedizione gratuita; 2 × 2900 = 5800 + 690 = 6490 con 4100 mancanti.
- Tutte le scritture rispondono con la `CartResource` aggiornata, anche `DELETE /cart` (200).
- `exceeds_stock`, `is_purchasable`, `line_total` null per le righe non acquistabili e `can_checkout` con un dataset di carrelli: vuoto, tutto acquistabile, una riga esaurita, una riga disattivata, una riga oltre lo stock.
- `max_quantity` con un dataset: stock 3 → 3, stock 10 → 10, stock 25 → 10.
- 422 su aggiunta o modifica oltre `max_quantity`, anche quando la somma con la quantità già presente supera il massimo; quantità 0 → 422.
- Il carrello usa il prezzo attuale: cambiando il prezzo del prodotto cambiano `unit_price` e totali.
- Un cliente non vede né modifica le righe del carrello di un altro: 404 e riga invariata.
- Admin: 403 con «Gli account di amministrazione non possono fare acquisti.» su ogni rotta di indirizzi e carrello, senza effetti, anche su una risorsa di un altro utente (il 403 di ruolo arriva prima del 404).
- Quantità molto oltre uno stock basso (stock 3, quantità 11) → «Puoi avere al massimo 3 pezzi…», in `POST` e `PATCH`; riga non acquistabile con quantità 11 → messaggio «non più disponibile».
- Account anonimizzato: ogni Action di carrello e indirizzi, chiamata con un'istanza non aggiornata di un utente anonimizzato nel database, lancia `AuthenticationException` senza effetti; via HTTP 401.

### Indirizzi

- Dataset di dati non validi con 422 sul campo giusto: CAP `5612` e `561255`, provincia `PIS` e `P1`, telefono mancante, più lungo di 20 caratteri, con lettere o con un `+` non iniziale.
- Dati validi: CAP `56125`, provincia `PI` e `pi` (salvata come `PI`), telefono `+39 333 123 4567` e `3331234567`.
- `country` salvato sempre come `IT`, anche se la richiesta invia un altro paese.
- Indirizzo predefinito: creare o aggiornare un indirizzo con `is_default` true lo toglie agli altri indirizzi dello stesso utente e non tocca quelli di un altro utente; il primo indirizzo è predefinito; `is_default` false sul predefinito è ignorato; eliminando il predefinito diventa predefinito il più recente.
- Indirizzo di un altro utente: 404 su `GET`, `PATCH` e `DELETE`, indirizzo invariato.

### Checkout

- Flusso riuscito: 201, ordine `paid` con `paid_at` e `terms_accepted_at`, righe e indirizzo in snapshot, stock decrementato, carrello svuotato, `payments.card_last_four` = `4242`, `number` nel formato `ORD-{anno}-{id a 6 cifre}` (es. per l'ordine con id 42 creato nel 2026, con `travelTo()`, `ORD-2026-000042`).
- Pagamento rifiutato con un prodotto eliminato con soft delete tra la creazione dell'ordine e la risposta del gateway (gateway finto dal container che elimina il prodotto prima di rispondere): stock del prodotto ripristinato.
- Il numero completo della carta e il CVC non sono in nessuna tabella: `assertDatabaseMissing` su `payments` con il numero completo e nessuna colonna che li contenga.
- 409 con l'elenco `{ product, requested, available }` per una riga esaurita, disattivata o con quantità oltre lo stock; nessun ordine creato e stock invariato.
- 402 con la carta `4000 0000 0000 0002`: ordine `cancelled` senza `paid_at`, stock ripristinato, carrello intatto, riga `payments` con `status` `failed` e `card_last_four` `0002`, cronologia `null → pending` e `pending → cancelled` con `changed_by` null e `reason` «Carta rifiutata dalla banca».
- 422 senza `terms_accepted` o con `terms_accepted: false`.
- Dataset della carta con 422 sul campo giusto: numero che non supera Luhn, numero di 12 e di 20 cifre, lettere nel numero, scadenza passata, mese `13`, formato `8/29`, CVC di 2 e di 4 cifre, intestatario mancante. La scadenza del mese corrente è valida (con `travelTo()`).
- Indirizzo di un altro utente → 422; email non verificata → 403.
- `dontFlash`: l'elenco del gestore delle eccezioni contiene `payment.number`, `payment.cvc`, `password`, `password_confirmation` e `current_password`, scritti come valori attesi nel test. Non serve un test con una richiesta non JSON: `ForceJsonResponse` rende JSON ogni richiesta API, quindi passerebbe comunque.
- Con `Log::spy()`, un checkout riuscito e uno rifiutato non scrivono nei log il numero completo della carta né il CVC.

### Ordini e visibilità

- `GET /orders` restituisce solo gli ordini del cliente con `paid_at`: ne sono esclusi i tentativi falliti (`failedPayment()`), gli ordini `pending` e quelli di altri utenti; è incluso un ordine `cancelledAfterPayment()`.
- `GET /orders/{order}`: 200 per un proprio ordine pagato, anche annullato dopo il pagamento; 404 per un tentativo fallito, per un ordine `pending` e per l'ordine di un altro cliente.
- `OrderResource` con `payment.card_last_four` e `items[].cover` null quando il prodotto è stato eliminato con soft delete e quando `product_id` è null.
- Admin: `GET /admin/orders` include i tentativi falliti; `failed_payment=1` restituisce solo quelli; `GET /admin/orders/{order}` di un tentativo fallito → 200.

### Impostazioni del negozio

- `GET /shop/settings` da ospite: 200 con `shipping_cost` 690 e `free_shipping_threshold` 9900, valori scritti nel test; nessun'altra chiave.
- `GET /admin/settings`: 200 per l'admin con in più `low_stock_threshold` 5; 401 da ospite, 403 per il cliente.

### Testi dell'assistenza

- `GET /shop/support` da ospite: 200 con le sezioni `spedizioni`, `resi`, `garanzia` e `contatti` in quest'ordine, con `title` scritti nel test.
- Gli importi nei testi seguono la configurazione: con `shop.shipping_cost` a 500 e `shop.free_shipping_threshold` a 12000 impostati nel test, i paragrafi contengono «5,00 €» e «120,00 €».

### Eliminazione dell'account

- `DELETE /me`: 401 da ospite; 423 per un cliente senza conferma dell'identità; 403 per l'admin **senza** conferma dell'identità (il permesso si controlla prima della conferma) e con la conferma.
- 409 con `code` `account_has_open_orders`, senza nessun effetto, con un dataset: un ordine `paid`, uno `shipped`, uno `pending` creato 14 minuti prima (con `travelTo()`). 204 con un ordine `pending` creato 16 minuti prima, con ordini solo consegnati, annullati o tentativi falliti, e senza ordini.
- Indirizzi `.invalid`: `POST /register` e `POST /me/email` → 422 sul campo `email` con un dataset di indirizzi qualsiasi con dominio di primo livello `.invalid` (es. `mario@esempio.invalid`, `deleted-7@anonymized.invalid`, `Mario@Posta.INVALID`); un indirizzo `.it` resta valido.
- Dopo il 204:
  - cancellati indirizzi, carrello e righe, eventi di sicurezza, richieste di cambio email, token di reset e sessioni dell'utente; le stesse righe di un altro utente restano intatte
  - colonne `two_factor_*` a null per un utente con doppia autenticazione (`withTwoFactor()`)
  - `name` «Utente eliminato», `email` `deleted-{id}@anonymized.invalid` con l'id dell'utente, `email_verified_at` e `remember_token` null, `anonymized_at` valorizzata, password diversa da quella di prima (`Hash::check` falso): valori attesi scritti nel test
  - ordini, righe, snapshot dell'indirizzo, cronologia e pagamenti invariati
  - sessione corrente chiusa: `GET /me` successivo → 401. L'utente si autentica con `actingAs($user, 'web')` o con un vero login, non con `Sanctum::actingAs`, che lo terrebbe in memoria e darebbe un falso esito
- Notifica con `Notification::fake()`: `assertSentOnDemand` all'indirizzo originale; nessuna notifica se la transazione fallisce e nessuna con il 409. Il fallimento si provoca registrando nel test `User::saved(fn () => throw new RuntimeException())`, che scatta al salvataggio dell'anonimizzazione dentro la transazione; oltre alla notifica assente si verifica che nulla sia cambiato (indirizzi presenti, `anonymized_at` null, email originale).
- Nessuna nuova riga in `security_events`.
- Login con l'email originale e la vecchia password → 422 `invalid_credentials`; un utente con `anonymized_at` valorizzata e una password nota → 422 `invalid_credentials` senza evento `login_failed`.
- Nuova registrazione con l'indirizzo originale → 201 con un utente nuovo, senza gli ordini precedenti.
- Dalla Fase 5, con gli endpoint di ordini e dashboard admin (`docs/roadmap.md`): `GET /admin/orders`, `GET /admin/orders/{order}` e `latest_orders` della dashboard mostrano `customer.name` «Cliente eliminato» ed `email` null; la cronologia dell'annullamento fatto dal cliente dice «da Cliente eliminato»; la ricerca con l'email originale non trova l'ordine.
- Passkey cancellate, quelle degli altri utenti conservate.
- Dati demo (Fase 3b): dopo il seeding esiste un utente con `anonymized_at` valorizzata, `name` «Utente eliminato», email con dominio `.invalid` e almeno un ordine `delivered`; nessun indirizzo, carrello o evento di sicurezza collegato.

### Assistente AI

Regole nella skill `ai-assistant`. `AiAssistant` sostituito con `FakeAiAssistant` dal container; `Http::preventStrayRequests()` in tutti i test dell'assistente.

- `GET /assistant/status`: `available` true e false secondo il finto.
- `POST /assistant/messages`, validazione con un dataset di 422: `messages` mancante, non array o vuoto, 11 messaggi, un messaggio `user` di 501 caratteri (500 valido), un messaggio `assistant` di 4001 caratteri (4000 valido), `content` mancante e non stringa, ruolo `system`, ruolo `tool`, ruolo sconosciuto, ultimo messaggio di ruolo `assistant`.
- Il corpo di una risposta in streaming si legge con `streamedContent()` (senza, la callback dello `StreamedResponse` non viene eseguita e i controlli su eventi e lock non verificano niente); le righe NDJSON si decodificano una per una.
- Risposta: `Content-Type` `application/x-ndjson`, eventi `text` con i frammenti del finto nell'ordine, poi `products` e `done`.
- Giro completo del tool calling con il finto programmato: una tool call `search_products`, il risultato restituito al finto con i soli prodotti attivi, poi il testo finale e l'evento `products`.
- Slug citati: uno slug inesistente, uno disattivato, uno eliminato con soft delete e uno esaurito sono scartati; i duplicati compaiono una volta; non più di `max_products`, con il valore scritto nel test; ordine delle card uguale all'ordine di citazione; cinque slug con il primo esaurito danno quattro card (lo scarto avviene prima del limite); nessuno slug citato → evento `products` con `data` vuoto.
- Finto non disponibile → 503 `assistant_unavailable`; errore o timeout a metà → evento `error` con messaggio in italiano dopo gli eventi `text` già inviati.
- Concorrenza: con lo slot occupato nel test (`Cache::lock('assistant:slot:1')` preso prima della richiesta e `max_concurrent` 1) → 503 `assistant_busy` senza chiamare il finto; lo slot risulta libero (`Cache::lock('assistant:slot:1')->get()` riesce) dopo una risposta completa, dopo una generazione terminata con errore e dopo un 503 `assistant_unavailable`; in ognuno dei tre casi la richiesta successiva risponde 200.
- Rilascio dello slot nel `finally` dello stream: nei test HTTP l'applicazione termina prima che `streamedContent()` esegua la callback, e il `terminating()` rilascerebbe lo slot per primo; questi controlli eseguono quindi lo stream direttamente (`StartAssistantAnswerAction` più `AssistantAnswerStream::toResponse()->sendContent()`, in un buffer con callback). `terminating()` si verifica a parte: risposta mai inviata → slot libero dopo `app()->terminate()`; risposta inviata e poi `terminate()` → una sola riga di log.
- Errori imprevisti, con un mock di `AiAssistant` nel container: un `RuntimeException` durante lo streaming → evento `error` generico dopo i `text` già inviati, slot libero, log `failed` con `reason`; prima dello streaming → 500 e slot libero.
- Disconnessione con `Tests\Support\FakeClientConnection` al posto di `ClientConnection`: nessun evento dopo quello in corso, nessun `products` né `done`, slot libero, log `aborted`; con il driver `ollama` e un body `FnStream` il body risulta chiuso subito dopo la prima riga, mentre la risposta è ancora viva.
- Limiter `assistant`: undicesima richiesta nel minuto → 429 con `Retry-After`, per utente e per IP da ospite.
- Tool: `search_products` con un dataset di filtri restituisce solo prodotti attivi e non eliminati; prezzi in euro convertiti in centesimi; argomenti non validi scartati.
- `OllamaAssistant` con `Http::fake()`, sull'endpoint con il driver `ollama` e un utente autenticato con nome ed email riconoscibili: il corpo inviato a `/api/chat` contiene il modello configurato, nessun messaggio di ruolo `system`, i messaggi con i soli ruoli ammessi, gli slug delle categorie nell'enum di `search_products` e nessun marcatore `<dati_negozio>` proveniente dai messaggi; non contiene nome, email, indirizzi, carrello o ordini di quell'utente.
- Il contenuto dei messaggi non compare nei log (`Log::spy()`).

### Cronologia e annullamento

- Una riga di cronologia per ogni transizione, con `from_status`, `to_status`, `changed_by` e `reason` attesi: creazione, pagamento riuscito, pagamento rifiutato, spedizione e consegna dall'admin, annullamento dal cliente e dall'admin.
- Descrizioni esatte della tabella della skill `ecommerce-domain`, scritte nel test; la variante admin con «da {nome}», quella del cliente senza autore.
- `GET /orders/{order}` del cliente: `status_history` senza le chiavi `changed_by` e `reason` (`assertJsonMissingPath`).
- `CancelOrderAction`, dal cliente e dall'admin: rimborso riuscito → stato `cancelled` con `cancelled_at`, scorte ripristinate (anche per un prodotto eliminato con soft delete dopo l'acquisto), pagamento `refunded`, riga di cronologia con l'autore.
- Rimborso fallito (gateway finto dal container): 502 con `code` `refund_failed`; stato dell'ordine, `cancelled_at`, scorte e `payments.status` invariati; nessuna nuova riga di cronologia; nessuna notifica.
- Annullamento di un ordine non annullabile (`pending`, spedito, consegnato, già annullato): 422 e il gateway non viene chiamato, sia dal cliente sia dall'admin.
- `POST /orders/{order}/cancel` su un ordine di un altro cliente e su un tentativo fallito → 404; `can_cancel` true solo per `paid`.
- Doppio annullamento: la seconda richiesta non rimborsa di nuovo e non scrive una seconda riga di cronologia.
- Notifiche con `Notification::fake()`: una sola email per spedito, consegnato e annullato con rimborso; nessuna notifica di transizione per `pending → paid` (solo la conferma di `OrderPlaced`) e nessuna email per il pagamento rifiutato.

### Admin

- Accesso: ogni rotta admin risponde 401 da ospite e 403 per il cliente.
- Coerenza dashboard-elenco: con gli stessi dati del test, per ogni voce di `orders_by_status` il `count` coincide con `meta.total` di `GET /admin/orders?status={stato}&paid_month={period.month}`, e la somma dei conteggi coincide con `orders_month`; i dati comprendono un tentativo fallito, un ordine annullato dopo il pagamento e un ordine pagato il mese precedente e ancora `paid`.
- Dashboard, con `travelTo()` a metà mese e dati costruiti nel test:
  - ordine pagato e poi annullato: escluso da `revenue_month`, contato in `orders_month` e in `orders_by_status` come Annullato
  - tentativo fallito e ordine `pending`: esclusi da tutti i dati
  - ordine pagato il mese precedente: escluso dai dati del mese, ma contato in `orders_to_ship` se è ancora `paid`
  - ordine pagato il primo giorno del mese alle 00:30 ora italiana: incluso
  - `orders_by_status` senza `pending` e con somma uguale a `orders_month`
  - `latest_orders`: al massimo 5, solo con `paid_at`, per `created_at` decrescente
  - scorte basse con un dataset: stock 0 e 5 inclusi, 6 escluso, disattivato ed eliminato esclusi; `low_stock_products` al massimo 5 per stock crescente
- Categorie:
  - slug generato dal nome alla creazione (`Casa smart` → `casa-smart`) e invariato dopo un `PATCH` che cambia il nome o invia `slug`
  - 422 per nome duplicato, per un nome che genera uno slug già usato, per un nome di 51 caratteri e una descrizione di 256; rinominare una categoria con la sola differenza di maiuscole è valido
  - `DELETE`: 204 senza prodotti; 409 con un prodotto; 409 con un solo prodotto eliminato con soft delete; `can_delete` e `products_count` coerenti con questi casi
  - `GET /categories` pubblico: comprende una categoria senza prodotti, ordinate per `id`
- Prodotti, con un dataset di 422: SKU `cds-aud-001`, `CDS AUD 001`, `CDS_AUD`, SKU già usato da un prodotto attivo e da un prodotto eliminato; `short_description` mancante e di 161 caratteri (160 valida); `price` 0, `12.5` e 1000000 (999999 valido); `stock` -1 e 1000000; il 422 dello SKU già usato ha il messaggio «Questo SKU è già in uso». Nell'update lo SKU del prodotto stesso è valido.
- Filtri di `GET /admin/products`: `search` per nome e per SKU, `category`, `status`; include i disattivati, esclude gli eliminati; `stock_status` con un dataset di stock 0, 1, 4, 5 e 6.
- Immagini, con `Storage::fake('public')`: caricamento di JPG, PNG e WebP con `alt` uguale al nome del prodotto e posizioni accodate; 422 per GIF, per un file di 5121 KB e per più di 10 file; `DELETE` con file rimosso e posizioni ricompattate; `PATCH` del prodotto con `images` riordinate e `alt` modificati; 422 per `images` con un id di un altro prodotto, con un id mancante o duplicato.
- Ordini: `search` per parte del numero e per email del cliente; `status`; `failed_payment=1`; `paid_month` con un dataset (ordine pagato il 1° settembre alle 00:30 ora italiana incluso in `2026-09`, pagato il 31 agosto alle 23:30 ora italiana escluso, tentativo fallito e ordine `pending` esclusi) e 422 per `2026-9`, `2026-13` e `settembre`; `paid_month` con `failed_payment=1` vuoto; combinazione di `status=paid` e `failed_payment=1` vuota; ordine per `created_at` decrescente; `items_count` come somma delle quantità; `allowed_transitions` per ogni stato; `PATCH .../status` con `paid` o `pending` → 422; `cancelled` su un ordine `pending` → 422 senza chiamare il gateway; `delivered` su un ordine `paid` → 422; `shipped` scrive la riga con l'admin come autore.

### Test di flusso

- Checkout completo, stock insufficiente, pagamento rifiutato con ripristino dello stock e carrello intatto.
- Annullamento dell'ordine con ripristino dello stock.
- Transizioni di stato consentite e vietate, ognuna con la sua riga di cronologia.
- Flussi di sicurezza elencati nella skill `account-security`.

### Unit test

- `OrderStatus::canTransitionTo()` con un dataset di tutte le coppie, ed etichette esatte di `label()` (In attesa, Pagato, Spedito, Consegnato, Annullato).
- Etichette esatte di `PaymentStatus::label()`: Riuscito, Non riuscito, Rimborsato.
- Disponibilità del prodotto con un dataset di stock 0, 1, 2, 3 e 4 (soglia 3): Esaurito, Ultimo pezzo, Ultimi 2 pezzi, Ultimi 3 pezzi, Disponibile. I valori attesi sono scritti nel test, non letti dalla configurazione.
- Calcolo di subtotale, spedizione e totale.
- `LuhnRule` con un dataset: `4242424242424242` e `4000000000000002` validi, `4242424242424241` non valido.
- Generazione del numero d'ordine.
- Generazione dello slug di prodotti e categorie. Prodotti: due prodotti con lo stesso nome sono ammessi e il secondo ha il suffisso `-2`, sia quando il primo è attivo sia quando è eliminato con soft delete; con un nome di 255 caratteri lo slug con il suffisso resta entro 255 caratteri.
- Verificatori del secondo fattore.

### Test di architettura

In `tests/Arch/ArchitectureTest.php`, con `arch()` di Pest:

- nessun uso di `dd`, `dump`, `ray`, `var_dump` nel codice applicativo
- controller `final` e con suffisso `Controller`
- Action `final`, con suffisso `Action` e un solo metodo pubblico `execute`
- Form Request estendono `FormRequest`, Resource estendono `JsonResource`
- enum in `App\Enums`, interfacce in `App\Contracts`
- i controller non usano direttamente `DB`
- le eccezioni di `App\Exceptions` implementano `ShouldntReport`, tranne quelle dei guasti elencate nella skill `laravel-api` ("Errori"); un test feature verifica con richieste reali che una risposta di dominio non scriva righe `error` nel log e che un rimborso fallito sì
- preset di sicurezza di Pest, se disponibile nella versione installata

## Frontend — Vitest, React Testing Library, MSW

### Strumenti

- **Vitest** con ambiente `jsdom`, configurato nel blocco `test` di `vite.config.js`.
- **React Testing Library** e **@testing-library/user-event** per interagire con i componenti come farebbe una persona.
- **@testing-library/jest-dom** per le asserzioni sul DOM.
- **MSW** per intercettare le chiamate HTTP: i test non dipendono dal backend acceso.

### Struttura

```
src/
├── test/
│   ├── setup.js                 # jest-dom, avvio e reset del server MSW
│   ├── renderWithProviders.jsx  # tema, QueryClient nuovo per ogni test, router in memoria, AuthProvider
│   ├── factories.js             # buildUser, buildProduct, buildCart, buildOrder, buildCategory, buildAdminProduct, buildAdminOrder, buildDashboard
│   └── mocks/
│       ├── server.js
│       └── handlers.js          # risposte di successo predefinite, comprese /shop/settings, /shop/support, /admin/settings, /categories e /assistant/status
└── features/cart/components/CartItemRow.test.jsx
```

- I test stanno accanto al file testato, con suffisso `.test.jsx` o `.test.js`.
- `setup.js` avvia MSW con `onUnhandledRequest: 'error'`: una chiamata non prevista fa fallire il test.
- Ogni test personalizza le risposte con `server.use(...)` per i casi di errore.
- Le factory producono oggetti con la stessa forma delle API Resource del backend.
- `renderWithProviders(ui, { route, user })` accetta la rotta iniziale e l'utente autenticato (o nessuno).
- `QueryClient` dei test con `retry: false`.

### Come si scrive un test

- Query in ordine di preferenza: `getByRole`, `getByLabelText`, `getByText`. `getByTestId` solo come ultima scelta, motivata.
- Interazioni con `const user = userEvent.setup()` e `await user.click(...)`, `await user.type(...)`.
- Contenuti asincroni con `findBy...` o `waitFor`.
- Si verifica ciò che vede l'utente, non lo stato interno dei componenti.
- Le passkey si testano simulando `@simplewebauthn/browser` con `vi.mock`.

### Cosa testare

| Area | Casi |
|---|---|
| Utility | `formatDateTimeShort` («29 set 2026, 16:52»), `formatDayTimeShort` («29 set, 16:52»), `formatMonthYear` («Settembre 2026»); `parseEuroToCents` con un dataset (`249,00` → 24900, `249,5` → 24950, `249` → 24900, `0,01` → 1) e rifiuto di `1.249`, `249.00`, `249,001` e testo vuoto; `formatCentsForInput` (34900 → «349,00», 124900 → «1249,00»); `formatPrice` (349,00 €, 1.249,00 € con il punto delle migliaia, 0,00 €), `formatDate`, `normalizeApiError` (compreso il 429 con `Retry-After: 45` → `retryAfter` 45 e «Troppi tentativi. Riprova tra 45 secondi», e senza header → `retryAfter` null; `code` dalla risposta, null se manca) |
| httpClient | ripetizione dopo 419, gestione del 423 con conferma e ripetizione |
| Rotte | redirect al login con ritorno alla pagina di partenza, blocco dell'area admin, pagine ospite; `CustomerRoute`: da ospite redirect al login, per un admin redirect a `/account/profile` da `/account/addresses`, `/account/orders` e `/account/orders/:orderId`, e alla home da `/cart`, `/checkout` e `/checkout/success/:orderId`; per un cliente la pagina si apre |
| Form | validazione Zod, errori 422 del server mostrati sui campi, pulsante disabilitato durante l'invio |
| Viste con dati | stati di caricamento, errore con "Riprova", vuoto, contenuto |
| Catalogo | filtri e ordinamento (Novità, Prezzo crescente, Prezzo decrescente) che aggiornano la query string e i risultati, ritorno a pagina 1, limiti dello slider da `meta.price_range`, chip "Novità" ed etichetta di disponibilità ricevuti dall'API, correlati nascosti se vuoti |
| Home e pagine statiche | hero con i dati del prodotto e con errore o 404, immagine di ripiego per una categoria senza immagine (es. "Viaggio"), menu, `Drawer` e footer con le categorie ricevute da `GET /categories` (compresa una sesta categoria) e con solo "Novità" se la richiesta fallisce, link del footer verso le ancore di `/assistenza` e `/note-legali`, `/assistenza` con sezioni e testi ricevuti da `GET /shop/support` (testi diversi da quelli reali nel test) e i quattro stati della vista |
| Assistente AI | pulsante «Assistente non disponibile» disabilitato con `available: false` e con errore di `/assistant/status`; suggerimenti con la conversazione vuota; nota sulle risposte generate da un'AI; limite di 500 caratteri nel campo; streaming NDJSON simulato con MSW che mostra il testo man mano, senza i marcatori `[[slug]]` (anche incompleti); card con prezzo e disponibilità dall'evento `products`; messaggi di 503 `assistant_busy`, 503 `assistant_unavailable`, 429 ed evento `error` nel pannello senza perdere la conversazione; al massimo 10 messaggi nel corpo inviato; header `X-XSRF-TOKEN` decodificato dal cookie; 419 seguito da una nuova richiesta del cookie CSRF e da un solo nuovo tentativo (un secondo 419 mostra l'errore); richiesta interrotta alla chiusura del pannello (`AbortController`, nessun errore mostrato); conversazione conservata navigando tra le pagine del negozio e assente da `localStorage` e `sessionStorage` |
| Impostazioni del negozio | testi della spedizione gratuita con l'importo ricevuto da `/shop/settings` (un valore diverso da quello reale nel test, es. 12000 → «120,00 €», per dimostrare che non è scritto nel codice), Skeleton durante il caricamento, elemento omesso in caso di errore |
| Carrello | "N articoli" da `items_count` con le righe esaurite, totali formattati, selettore da 1 a `max_quantity`, riga esaurita con "—" e "Rimuovi dal carrello", "Procedi al checkout" disabilitato con `can_checkout` false, barra della spedizione gratuita, carrello vuoto con "Potrebbero piacerti", Snackbar dopo l'aggiunta |
| Checkout | avanzamento dei tre passi, email non verificata, campo "Paese" non modificabile, validazione Zod di indirizzo e carta, "Paga" disabilitato senza accettazione delle condizioni o con `can_checkout` false, 402 che torna al passo 2 con i dati della carta conservati, 409 con "Disponibilità cambiata", 201 con navigazione alla conferma |
| Dati della carta | dopo l'invio, riuscito e fallito, la MutationCache non contiene i dati della carta (`queryClient.getMutationCache()`), l'errore della mutation non contiene `config.data`, `localStorage` e `sessionStorage` sono vuoti, l'URL non contiene parametri della carta; rimontando la pagina il checkout riparte dal passo 1 con i campi vuoti |
| Conferma d'ordine | dati letti da `GET /orders/{id}` (numero, stato, "Carta che termina con 4242", totali), immagine di ripiego per un prodotto eliminato, 404 con link agli ordini |
| Sicurezza | login con secondo fattore (app, codice di recupero, passkey solo se in `methods`), credenziali errate (422 con `code` `invalid_credentials`) con un solo avviso e nessun errore sui campi, challenge scaduta (422 con `code` `two_factor_challenge_expired`) che riporta al login e codice sbagliato che resta sulla pagina con l'errore sul campo, messaggio 429 al login, verifica email da ospite con esito positivo e l'indirizzo preso dalla risposta, banner di email non verificata assente nelle pagine di accesso, `PasswordRequirements` che si aggiorna mentre si scrive e `newPasswordSchema` (9 caratteri, senza maiuscola, senza minuscola, senza numero), pagine dai link email (successo e link scaduto), reset con la sezione di verifica solo se richiesta, cambio password con secondo fattore in linea solo con la doppia autenticazione attiva, attivazione TOTP con codici di recupero, conferma dell'identità (esplicita e dopo 423, con la descrizione dell'operazione), cambio email con il `Dialog` "Nuova email" dopo la conferma, avviso di cambio in sospeso con "Annulla la richiesta", attività recente con "Mostra altre attività" che sparisce all'ultima pagina, eliminazione dell'account (conferma disabilitata finché non si spunta la checkbox, conferma dell'identità dopo 423 con la descrizione dell'operazione, 204 con navigazione alla home e Snackbar, 409 `account_has_open_orders` con il dialog aperto e il messaggio) |
| Area account | menu account per ospite e utente autenticato; per un admin, nel menu account dell'header e nei `Tabs` dell'area account, voci «Indirizzi» e «I miei ordini» assenti e voce «Amministrazione» che porta a `/admin`, mentre per un cliente «Amministrazione» è assente; navigazione del layout con la voce attiva, dettaglio ordine con solo "Carta che termina con 4242" e nessun intestatario, timeline da `status_history`, "Annulla l'ordine" solo con `can_cancel`, dialog di annullamento con "Mantieni l'ordine" e "Annulla l'ordine", 502 `refund_failed` con il dialog aperto e il messaggio |
| Admin | layout con la voce attiva e il titolo della pagina, nome ed email nell'`AppBar`; dashboard con indicatori, «N pezzi o meno» dalle impostazioni admin e link filtrati (ogni stato con `status` e `paid_month`); elenco ordini con `paid_month` nella query string mostrato come `Chip` «Pagati a Settembre 2026» e rimosso con la sua X, con ritorno a pagina 1; elenco prodotti e ordini con filtri nella query string, ritorno a pagina 1, ricerca con attesa (timer finti di Vitest), stato vuoto con "Azzera filtri", «N ordini» e «1 ordine»; form prodotto: validazione Zod (SKU, descrizione breve di 161 caratteri, prezzo `1.249` e `10000,00` rifiutati, `9999,99` valido), prezzo inviato in centesimi, errori 422 sui campi e conteggio nell'`Alert`, su/giù della scheda tecnica, dopo la creazione navigazione alla modifica senza dialog, dialog delle modifiche non salvate con "Resta sulla pagina" ed "Esci senza salvare", "Annulla" che ripristina i valori, messaggio sulle immagini nel nuovo prodotto; immagini: file non validi scartati con il messaggio, caricamento che non perde gli `alt` non salvati, ← → che cambiano l'ordine inviato; categorie: slug in sola lettura, elimina disabilitato con tooltip, 409 mostrato, invalidazione di `categories`; dettaglio ordine: azioni da `allowed_transitions`, dialog di annullamento con rimborso e scorte, 502 `refund_failed` con il dialog aperto, pagamento rifiutato con `Chip` "Annullato", `Alert` con il motivo e nessuna azione |

Fuori perimetro: test end-to-end con Playwright, extra facoltativo della Fase 10.
