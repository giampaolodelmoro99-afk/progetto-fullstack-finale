# Fase 6 — Report della revisione del backend

Data: 6 ottobre 2026. Branch `fase-6-backend-review`, partito da `main` (03ed754).

Questo report raccoglie quello che è stato applicato subito (A1, A2), le misure (A3) e le verifiche che hanno prodotto proposte (B1, B2, B3). Le sezioni 1–6 descrivono lo stato al momento della prima fermata; tutte le proposte sono state poi confermate e applicate, con le differenze e le misure finali riportate nella **sezione 7, «Esito»**.

## 1. Già applicato

### A1 — Eccezioni di dominio fuori dal log

- 20 eccezioni con `render()` implementano `Illuminate\Contracts\Debug\ShouldntReport`, con una riga nel docblock della classe. Il piano diceva 19: le classi con `render()` sono 21, non 20, quindi le escluse sono 20 più `RefundFailedException`.
- Restano nel log: `RefundFailedException` (502), `PasskeyCeremonyFailedException`, `AssistantTimeoutException`, `AssistantFailedException`.
- Test:
  - `tests/Arch/ArchitectureTest.php`: ogni classe di `App\Exceptions` implementa `ShouldntReport`, tranne le quattro scritte a mano; le quattro non la implementano.
  - `tests/Feature/Http/DomainExceptionReportingTest.php`: login con password sbagliata (422), eliminazione dell'account con un ordine pagato (409) e carta rifiutata (402) non scrivono righe `error`; un rimborso fallito (502) ne scrive una, con il messaggio dell'eccezione. Il caso del rimborso dimostra anche che nei test il log è attivo, quindi i tre casi «nessuna riga» sono significativi.
- Skill: `laravel-api` ("Errori") con la regola e l'elenco delle eccezioni da riportare; `testing` ("Test di architettura").
- `docs/backlog.md`: voce segnata come realizzata.

### A2 — Commenti inline nei test

- Gli unici due casi erano in `backend/tests/Feature/Api/V1/Assistant/AssistantMessageTest.php`: il primo è passato nel docblock di `streamAnswerDirectly()`, il secondo in un blocco `/* | ... */` sopra il test, lo stile già usato in `DashboardTest.php` e `PasskeyUserHandleSecretTest.php`.
- Ricerca in tutto `backend/tests/` e in `frontend/src/**/*.test.js(x)` e `frontend/src/test/`, sia commenti a inizio riga sia a fine istruzione: nessun altro caso.
- `docs/backlog.md`: voce segnata come realizzata.

`make test-backend` dopo A1 e A2: **1788 test superati (7911 asserzioni)**.

## 2. A3 — Copertura del backend

`make coverage-backend` (PCOV, soglia `--min=80`): **99,4%**, soglia superata. **Nessuna classe sotto il 70%**: la più bassa è `AssistantAskCommand` con l'87,8%.

Classi sotto il 100%, con le righe scoperte:

| Classe | Copertura | Righe scoperte | Cosa non è coperto |
|---|---|---|---|
| `Services/Security/WebAuthnPasskeyCeremony` | 89,7% | 75–78 | `TypeError`/`ValueError` della libreria nella **registrazione** (il caso dell'accesso è coperto) |
| `Services/Security/PasskeyVerifier` | 91,7% | 38 | payload senza contesto o senza `credential` array |
| `Actions/Checkout/SettleOrderPaymentAction` | 93,5% | 46, 63, 88 | esito registrato su un ordine non più `pending` (checkout e riconciliazione in concorrenza), sia riuscito sia rifiutato |
| `Support/WebpImageEncoder` | 93,6% | 33, 63, 95 | errori interni di GD (lettura, rotazione, codifica WebP) |
| `Services/Assistant/FakeAiAssistant` | 94,7% | 98, 127 | rami del finto dei test (turno di default, `failAfter()` senza turni) |
| `Http/Resources/OrderResource` | 95,2% | 40 | `payment` null per un ordine del cliente senza pagamento riuscito o rimborsato |
| `Http/Requests/Checkout/PlaceOrderRequest` | 97,7% | 82 | `payment` che non è un array (uscita anticipata di `prepareForValidation()`) |
| `Services/Assistant/OllamaAssistant` | 98,9% | 198 | errore non di timeout durante la lettura dello stream di Ollama |
| `Console/Commands/AssistantAskCommand` | 87,8% | 65–70 | eccezione imprevista nel comando |

### Test mancanti proposti

Solo comportamenti reali e osservabili, con i valori attesi scritti a mano. I test scritti differiscono in due punti da questa proposta: vedi la sezione 7.

1. **`SettleOrderPaymentAction` su un ordine non più `pending`** (righe 46, 63, 88). È il caso reale di checkout e riconciliazione che registrano lo stesso esito. Due test diretti sull'Action: esito riuscito su un ordine già `cancelled` → nessuna riga in `payments`, nessuna riga di cronologia, carrello intatto, stato invariato; esito rifiutato su un ordine già `paid` → stato `paid`, scorte invariate, nessuna riga nuova.
2. **`PlaceOrderRequest` con `payment` non array** (riga 82): una voce in più nel dataset dei 422 del checkout, `payment: "4242"` → 422 sul campo `payment`, senza errore 500.
3. **`AssistantAskCommand` con un errore imprevisto** (righe 65–70): `AiAssistant` simulato che lancia `RuntimeException` → codice di uscita 1, messaggio «Si è verificato un problema con l'assistente. Riprova tra poco.», eccezione riportata e slot libero.
4. **`OllamaAssistant` con un errore a metà stream** (riga 198): con `Http::fake()` e un body `FnStream` che lancia un'eccezione alla lettura della seconda riga → evento `error` generico dopo il primo `text`.
5. **`WebAuthnPasskeyCeremony`, `TypeError` nella registrazione** (righe 75–78): stesso schema del test esistente sull'accesso → 422 su `credential` e una sola `PasskeyCeremonyFailedException` nel log.

Non propongo test per:
- `WebpImageEncoder`, righe 33, 63, 95: richiederebbero di far fallire GD con file costruiti apposta o con un mock delle funzioni native; il ramo che conta (un file che GD non legge → 422) è già coperto.
- `FakeAiAssistant`: è il finto dei test, non codice dell'applicazione.
- `OrderResource`, riga 40: un ordine visibile al cliente ha sempre un pagamento riuscito o rimborsato, quindi il ramo è solo difensivo.
- `PasskeyVerifier`, riga 38: ramo difensivo, i chiamanti passano sempre contesto e `credential` già validati dalle Form Request.

Con i test 1–5 la copertura salirebbe di qualche decimo: lo scopo è coprire i comportamenti di concorrenza ed errore, non la percentuale.

## 3. B1 — Coerenza tra skill e codice

Verificati e coerenti: tutte le 75 rotte di `api/v1` con i loro middleware (`auth:sanctum`, `can:purchase`, `verified`, `role:admin`, `identity.confirmed` e il suo ordine dopo `can:`, limiter, `->block()` su login con 2FA, passkey, reset, conferma dell'identità, cambio password, registrazione della passkey e checkout); i 10 limiter con soglie e chiavi; `config/shop.php`, `config/assistant.php`, `config/auth.php`, `config/cors.php` (`Retry-After` esposto, credenziali), `config/passkeys.php`, `.env.example`; codici, status e messaggi delle eccezioni documentate; le forme di `CartResource`, `OrderResource`, `Admin\OrderResource`, `Admin\ProductResource`, `ProductResource`, dashboard, `SecurityOverviewResource`, `SecurityEventResource`; le opzioni di `per_page`; `Cache-Control: no-store` su attivazione TOTP e codici di recupero; gli header dello stream NDJSON; `GetAssistantStatusAction::CACHE_SECONDS` e `CitedProducts::MAX_SLUGS`.

Differenze trovate:

| # | Differenza | La skill dice | Il codice fa | Proposta | Motivo |
|---|---|---|---|---|---|
| 1 | Codici d'errore non documentati | `POST /reset-password/check` con token non valido → «422»; attivazione e disattivazione TOTP senza casi d'errore; transizione non consentita → «422» | `invalid_reset_token` (422, «Il link per reimpostare la password non è valido o è scaduto.»); `two_factor_already_enabled`, `two_factor_not_enabled`, `two_factor_setup_missing` (409); `invalid_order_transition` (422, «Lo stato attuale dell'ordine non consente questa operazione.») | **skill**: aggiungerli ad `account-security` (recupero password, TOTP) e a `ecommerce-domain` (stati dell'ordine) | il frontend li riceve e la documentazione delle API li deve elencare; i valori nel codice sono già stabili e testati |
| 2 | Middleware personalizzati | `laravel-api` elenca `ForceJsonResponse`, `EnsureUserHasRole`, `EnsureIdentityConfirmed` | c'è anche `EnsureEmailIsVerified`, alias `verified`, che sostituisce quello di Laravel | **skill**: aggiungerlo a "Struttura" e "Middleware personalizzati" | il checkout dipende da questo middleware |
| 3 | Enum | `laravel-api`, "Struttura": `OrderStatus, PaymentStatus, UserRole, SecurityEventType` | anche `PasskeyPurpose`, `SecondFactorContext`, `SecondFactorMethod` | **skill**: elenco completo o «per esempio» | descrizione incompleta |
| 4 | `config/security.php` | 7 chiavi | anche `totp_issuer` = `Casa del Silicio` (emittente nell'URI del QR code) | **skill**: aggiungerla ad `account-security`, "Configurazione" | il README la mostra già nell'URI `otpauth://` |
| 5 | Metodo degli update di `apiResource` | indirizzi, prodotti e categorie admin con `PATCH` | `Route::apiResource` accetta `PUT` e `PATCH` | **skill**: una riga in `laravel-api` ("Rotte"): `PUT` è accettato come alias, il frontend usa `PATCH`. Nessuna modifica al codice | è il comportamento standard di Laravel e non fa danni; la specifica OpenAPI documenterà entrambi, come deciso nel piano |
| 6 | Lunghezza di `search` negli elenchi admin | prodotti e ordini admin: `search` «testo» | `max:100` in entrambe le Form Request | **skill**: «testo, massimo 100 caratteri», come per il catalogo pubblico | allineamento della tabella con la validazione |

Nessuna differenza nei comportamenti: tutte le proposte correggono le skill.

## 4. B2 — Coerenza tra schema ER e migration

Confronto tra `docs/database/er-diagram.md`, le 18 migration e lo schema reale (`information_schema` del database `ecommerce`): **tabelle, colonne con tipo, nullabilità e default, indici e unicità, chiavi esterne con `ON DELETE` coincidono** per tutte le 21 tabelle, compresi `credential_id` con charset `ascii` e collation `ascii_bin`, `categories.name` in `utf8mb4_unicode_ci` e la sessione MySQL in UTC (`'timezone' => '+00:00'`).

Unica differenza, nel testo del documento:

| # | Differenza | Proposta |
|---|---|---|
| 1 | L'intestazione è ancora quella della Fase 1: «Fonti» elenca le skill e le sole migration di Laravel già committate, e la convenzione «le tabelle annotate come provvisorie o con una fase sono previste e non ancora migrate» non riguarda più nessuna tabella | **documento**: «Fonti» con le migration di `backend/database/migrations/` come fonte (regola 11) e la data dell'ultimo controllo; togliere la convenzione sulle tabelle provvisorie |

## 5. B3 — Sicurezza

### La skill integrata `security-review` non è partita

Il suo primo comando è `git log origin/HEAD...`: il remoto `origin` esiste, ma il riferimento `origin/HEAD` non è impostato in questo clone, quindi la skill si ferma prima di iniziare. Per farla funzionare basta `git remote set-head origin --auto` (legge il branch predefinito da GitHub, quindi serve la rete). Non l'ho eseguito senza conferma. In ogni caso la skill esamina le modifiche del branch, che oggi sono solo quelle di A1 e A2: per una revisione di tutto il backend non sarebbe bastata.

### Revisione manuale dello stesso perimetro

Controllato:
- **sessione e Sanctum**: solo cookie, nessun token personale, Bearer ignorato (401); `statefulApi()`, `SANCTUM_STATEFUL_DOMAINS=localhost:5173`, cookie `http_only` e `same_site=lax`; CSRF attivo sulle richieste stateful; rigenerazione della sessione a login, 2FA e passkey
- **CORS**: una sola origine da `FRONTEND_URL`, `supports_credentials`
- **autorizzazione e IDOR**: risorse personali con Policy e `denyAsNotFound()` (indirizzi, righe del carrello, ordini, passkey); `can:purchase` sul gruppo; area admin con `auth:sanctum` più `role:admin` e Policy; `scopeBindings()` sulla cancellazione delle immagini, così un'immagine di un altro prodotto risponde 404
- **mass assignment**: tutti i modelli con `#[Fillable]` espliciti; `role`, `email_verified_at`, `anonymized_at` e `slug` non sono fillable; i controller non usano `all()` né `input()`
- **SQL**: nessun input concatenato; `selectRaw` solo con testo fisso; le ricerche `LIKE` passano da `LikePattern`, che fa l'escape di `%` e `_`
- **upload**: validazione di tipo, peso e dimensioni; il file originale non si salva mai, ricodifica in WebP con nome UUID; i metadati EXIF spariscono
- **link firmati**: verifica email con `signed:relative`, utente cercato con `find()` e confronto dell'hash con `hash_equals`; token di cambio email salvati come SHA-256
- **dati sensibili**: `dontFlash` per password e carta, segreti in `#[Hidden]`, cast `encrypted`, `#[\SensitiveParameter]` sui dati della carta e della cerimonia passkey, `zend.exception_ignore_args = On`, `display_errors = Off`
- **passkey**: relying party e origine da `FRONTEND_URL`, verifica dell'utente obbligatoria, opzioni monouso con `->block()`, contatore delle firme
- **assistente**: nessun dato dell'utente nel contesto, ruoli `system` e `tool` rifiutati, argomenti dei tool validati e scartati se non validi, marcatori `<dati_negozio>` rimossi, contenuto dei messaggi assente dai log

**Nessun problema bloccante né importante.**

Note per la produzione, coerenti con le scelte di sviluppo e da riportare nel README in Fase 10 (nessuna modifica proposta ora):

| # | Nota | Stato |
|---|---|---|
| 1 | `.env.example` ha `APP_DEBUG=true` e `LOG_LEVEL=debug`: in produzione vanno `false` e `warning` o `error`, altrimenti un 500 mostra la traccia | scelta di sviluppo |
| 2 | `SESSION_SECURE_COOKIE` non impostata: su HTTPS va `true`, così il cookie di sessione non viaggia in chiaro | scelta di sviluppo (localhost senza HTTPS) |
| 3 | Token di reset in chiaro nel payload della coda con un driver asincrono | già nel backlog, Fase 10 |
| 4 | `POST /email-change/confirm`, `/email-change/cancel`, `/reset-password/check` e `/reset-password/passkey-options` hanno solo il limiter `api` (120 al minuto) | accettato nella skill `account-security`: i token sono casuali con almeno 256 bit di entropia (64 caratteri) e scadono in 60 minuti |

## 6. Decisioni richieste

1. **B1**: applicare le sei correzioni alle skill della sezione 3 (nessuna modifica al codice).
2. **B2**: aggiornare l'intestazione di `docs/database/er-diagram.md`.
3. **A3**: scrivere i test mancanti 1–5 della sezione 2 (o solo alcuni).
4. **B3**: eseguire `git remote set-head origin --auto`, così la skill `security-review` torna utilizzabile nelle fasi successive.

Dopo la conferma: correzioni, poi documentazione OpenAPI (A4), README (A5), `code-reviewer`, `make test` tre volte e `make lint`.

## 7. Esito

### Decisioni prese

1. **B1**: le sei correzioni applicate alle skill `account-security`, `ecommerce-domain` e `laravel-api`; nessuna modifica al codice.
2. **B2**: intestazione di `docs/database/er-diagram.md` aggiornata (fonte: le migration; data dell'ultimo controllo).
3. **A3**: scritti tutti e cinque i test.
4. **B3**: `git remote set-head origin --auto` eseguito dall'utente.
5. Le quattro note per la produzione della sezione 5 sono in `docs/backlog.md`, riga della Fase 10, per il README.

### Test scritti per A3

| # | File | Cosa verifica |
|---|---|---|
| 1 | `tests/Feature/Actions/Checkout/SettleOrderPaymentActionTest.php` | un ordine `pending` pagato da un percorso e poi registrato dall'altro con un'istanza letta prima del pagamento: un secondo esito riuscito non crea un altro pagamento, lascia la cronologia `null → pending`, `pending → paid`, non tocca il carrello e non invia una seconda conferma; un esito rifiutato lascia l'ordine `paid`, lo stock a 7, il pagamento `succeeded` e nessuna riga `cancelled`. Diverso dalla proposta: entrambi i casi partono da un ordine già pagato, che è lo scenario reale della concorrenza |
| 2 | `tests/Feature/Api/V1/Checkout/CheckoutValidationTest.php` | `payment` stringa o numero → 422 su `payment` e sui quattro campi della carta, nessun ordine, gateway mai chiamato. Diverso dalla proposta: un dataset con stringa e numero, e gli errori anche sui campi interni, come risponde davvero la validazione |
| 3 | `tests/Feature/Console/AssistantAskCommandTest.php` | eccezione imprevista nel comando → uscita con errore, messaggio generico, una riga `error` nel log con il messaggio originale, slot libero |
| 4 | `tests/Feature/Services/Assistant/OllamaAssistantTest.php` | connessione con Ollama che cade dopo la prima riga → `AssistantFailedException` con il messaggio generico e il motivo tecnico, dopo il primo `TextDelta` |
| 5 | `tests/Feature/Security/PasskeyCeremonyTest.php` | `TypeError` della libreria nella registrazione → 422 su `credential`, una sola `PasskeyCeremonyFailedException` nel log senza il contenuto della risposta del browser, nessuna passkey salvata |

### Documentazione delle API (A4)

- `docs/api/openapi.yaml`, OpenAPI 3.1 in italiano: 75 rotte, 78 operazioni (le tre rotte di update di `apiResource` documentano anche `PUT`). Redocly CLI, lanciato in un container usa e getta e non aggiunto al progetto, la dà valida con due soli avvisi voluti (nessuna licenza, server su localhost).
- `backend/tests/Feature/Docs/OpenApiSpecTest.php`: rotte e operazioni coincidono nei due sensi, `summary`, `operationId` unici e risposte presenti, riferimenti interni risolti.
- Dipendenza di sviluppo `symfony/yaml` (`^8.1`). Il container `app` monta `./docs/api` in sola lettura su `/var/www/docs/api` (`docker-compose.yml`), così il test legge la specifica.

### Revisione finale (`code-reviewer`)

Nessun bloccante. I cinque problemi importanti, confermati dall'utente e corretti:

1. `POST /passkeys/login`: una `credential` con la forma sbagliata è un 422 di validazione, non `invalid_passkey`; descrizione e risposta aggiornate.
2. Le 422 che possono avere sia la forma di validazione sia quella di dominio (passkey login, cambio di stato admin, controllo del reset, opzioni passkey del reset, conferma e annullamento del cambio email) usano la nuova risposta `UnprocessableEntity`.
3. `SettleOrderPaymentActionTest`: valori attesi scritti a mano (cronologia esatta, stock 7) invece di letti dopo l'Action o dalla factory.
4. README: l'autorizzazione passa dalla Form Request nelle rotte con un corpo e da `Gate::authorize()` nel controller in quelle senza; `can:purchase` è un middleware di gruppo.
5. Questo report: premessa aggiornata e sezione «Esito».

Suggerimenti applicati direttamente: 402 nella tabella degli status; messaggi reali dei 403 di checkout e `DELETE /me`; `Cache-Control` come lo invia Symfony (`no-store, private`); eventi NDJSON senza `discriminator` e con `type` obbligatorio; nota sul limiter `api` valido per tutte le rotte; il test della specifica legge solo i metodi HTTP; script Postman del README con una variabile della collection. Il nono suggerimento (testi di Laravel non tradotti) è nel backlog, Fase 10, con i due casi simili emersi in questa fase (419 «CSRF token mismatch.» e il nome del campo `payment`).

### Misure finali

| Verifica | Esito |
|---|---|
| `make test`, tre esecuzioni una dopo l'altra | tutte verdi: backend 1801 test (8122 asserzioni), frontend 35 test in 4 file |
| `make lint` | verde: Pint su 519 file, ESLint senza errori |
| `make coverage-backend` | 99,8% (soglia 80%); nessuna classe sotto il 90% |

Nessun commit.
