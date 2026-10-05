---
name: laravel-api
description: Architettura e convenzioni del backend Laravel del progetto — rotte versionate, controller sottili, Form Request, Policy, API Resource, Action, middleware personalizzati, enum, eccezioni, eventi, migration, factory, seeder e test manuale con Postman. Usala per qualsiasi modifica in backend/.
---

# Laravel API — convenzioni del progetto

## Flusso di una richiesta

Rotta → middleware → Form Request (autorizzazione tramite Policy + validazione) → Controller → Action (logica) → API Resource (risposta)

Ogni livello ha una sola responsabilità. Il controller coordina: non contiene logica di business né query complesse.

## Struttura

```
app/
├── Actions/{Area}/            # un caso d'uso = una classe
├── Contracts/                 # interfacce (PaymentGateway, AiAssistant, Security/...)
├── Enums/                     # OrderStatus, PaymentStatus, UserRole, SecurityEventType
├── Events/  Listeners/
├── Exceptions/                # eccezioni di dominio con render()
├── Http/
│   ├── Controllers/Api/V1/{Auth,Shop,Catalog,Cart,Checkout,Account,Admin}/
│   ├── Middleware/            # ForceJsonResponse, EnsureUserHasRole, EnsureIdentityConfirmed
│   ├── Requests/{Area}/{Model}/
│   └── Resources/             # pubbliche; Resources/Admin/ per le varianti admin
├── Models/
├── Notifications/             # tutte le email, in italiano
├── Observers/
├── Policies/
├── Providers/
├── Services/{Area}/           # implementazioni delle interfacce (Assistant/ per l'assistente AI)
└── Support/                   # piccole classi di utilità senza stato (UserAgentLabel)
config/
├── cors.php                   # origine del frontend, credenziali, exposed_headers con Retry-After
├── shop.php                   # spedizione, soglie ultimi pezzi e scorte basse, giorni delle novità
├── security.php               # tempi di conferma, scadenze, eventi per pagina
└── assistant.php              # driver, Ollama, timeout, limiti e concorrenza dell'assistente AI (skill ai-assistant)
database/
├── factories/
└── seeders/
    └── assets/                # immagini demo dei prodotti
routes/
├── api.php                    # carica solo i file di v1
└── api/v1/{public,customer,admin}.php
```

## Rotte

- `routes/api.php` include i file di `routes/api/v1/` con prefisso `v1` e nome `v1.`.
- `public.php`: catalogo, impostazioni e testi dell'assistenza del negozio (`GET /shop/settings`, `GET /shop/support`), assistente AI (`GET /assistant/status`, `POST /assistant/messages`), health check, registrazione, login, recupero password, conferme via link (compresa la verifica email, protetta solo dalla firma: vedi `account-security`).
- `customer.php`: middleware `auth:sanctum`.
- `admin.php`: middleware `auth:sanctum` e `role:admin`, prefisso `admin`, nome `admin.`.
- Usa `Route::apiResource` quando possibile; per le azioni singole usa controller invocabili (`__invoke`).
- Prodotti e categorie pubblici usano il route model binding sullo slug: `{product:slug}`. Le rotte admin usano l'id: `{product}`, `{category}`, `{order}`.
- Impostazioni: `Shop\SettingsController` (`GET /shop/settings`) e `Admin\SettingsController` (`GET /admin/settings`), invocabili, restituiscono rispettivamente `SettingsResource` e `Admin\SettingsResource`, costruite sui valori di `config/shop.php` (skill `ecommerce-domain`, "Impostazioni esposte al frontend").
- Rate limiter nominati definiti in `AppServiceProvider` (vedi anche la skill `account-security`), compreso `assistant` della skill `ai-assistant`.

## Controller

- In `App\Http\Controllers\Api\V1\{Area}`, un controller per risorsa, classe `final`.
- Solo metodi resource (`index`, `store`, `show`, `update`, `destroy`) oppure `__invoke`.
- Restituiscono sempre una Resource, una collection di Resource o `response()->noContent()`.
- Status: 201 per la creazione, 204 per l'eliminazione.
- Usano `$request->validated()`, mai `$request->all()` o `$request->input()` per i dati da salvare.

```php
final class ProductController extends Controller
{
    public function store(StoreProductRequest $request, CreateProductAction $createProduct): JsonResponse
    {
        $product = $createProduct->execute($request->validated());

        return Admin\ProductResource::make($product->load(['category', 'images']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return response()->noContent();
    }
}
```

## Autorizzazione

- Ogni permesso passa da una Policy, una per modello, scoperta automaticamente per convenzione di nomi.
- Se l'azione ha una Form Request, la Policy si invoca in `authorize()` della Form Request, così l'autorizzazione avviene prima della validazione.
- Se l'azione non ha una Form Request (`index`, `show`, `destroy`), si usa `Gate::authorize()` come prima istruzione del controller.
- Eccezione: le rotte pubbliche senza regole di visibilità (categorie, elenco prodotti, impostazioni e testi dell'assistenza del negozio) non hanno Policy; il dettaglio e i correlati di un prodotto passano da `ProductPolicy::viewInShop`, che risponde 404 per i prodotti disattivati anche all'admin; `view` resta per lo `show` dell'area admin, che vede anche i disattivati.
- Non usare `before()` per concedere tutto all'admin: ogni permesso è esplicito.
- Il cliente accede solo alle proprie risorse (`$order->user_id === $user->id`).
- Il middleware `role:admin` protegge l'area admin come prima barriera; le Policy restano comunque obbligatorie.
- Quando l'esistenza stessa della risorsa non va rivelata, la Policy nega con `Response::denyAsNotFound()` (404) invece di un 403. Caso del progetto: `OrderPolicy::view` per il cliente consente solo i propri ordini con `paid_at` valorizzato e risponde 404 per tutti gli altri; l'admin vede tutti gli ordini. L'elenco del cliente applica lo stesso criterio con lo scope `visibleToCustomer`.

## Form Request

- Una per azione di scrittura o per validare i filtri di un elenco: `Store{Model}Request`, `Update{Model}Request`, `Index{Model}Request`.
- Regole in forma di array, con `Rule::enum()`, `Rule::exists()`, `Rule::unique()->ignore()` negli update.
- `prepareForValidation()` per normalizzare l'input (trim, lowercase delle email, cast; spazi rimossi dal numero della carta).
- Regole di indirizzi e checkout: tabelle di `StoreAddressRequest` e `PlaceOrderRequest` nella skill `ecommerce-domain`. Il numero della carta si valida con una Rule dedicata (`LuhnRule` in `App\Rules`); `country` non è tra le regole degli indirizzi perché lo imposta il server a `IT`.
- Messaggi in italiano tramite `lang/it/validation.php`; nomi dei campi leggibili con `attributes()`.

```php
final class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9-]+$/', Rule::unique('products', 'sku')],
            'short_description' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'specifications' => ['present', 'array', 'max:20'],
            'specifications.*.label' => ['required', 'string', 'max:64'],
            'specifications.*.value' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:1', 'max:999999'],
            'stock' => ['required', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
```

- Il messaggio dell'unicità dello SKU è «Questo SKU è già in uso», in `messages()` della Form Request.
- `Rule::unique('products', 'sku')` controlla tutte le righe, comprese quelle eliminate con soft delete: è il comportamento voluto (skill `ecommerce-domain`), quindi **non** si aggiunge `withoutTrashed()`. In `UpdateProductRequest` si usa `->ignore($this->route('product'))`.
- `UpdateProductRequest` accetta anche `images` (tutte le immagini del prodotto, nell'ordine voluto, con `id` e `alt`) e un `after()` verifica che gli id siano esattamente quelli del prodotto. Il caricamento dei file ha una richiesta dedicata, `StoreProductImagesRequest`, con `images` array da 1 a 10 elementi e `images.*` con `image`, `mimes:jpg,jpeg,png,webp` e `max:5120`.
- Le Form Request delle categorie non contengono `slug`: `validated()` non lo restituisce mai, quindi un `PATCH` che lo invia non lo modifica. L'unicità dello slug derivato si controlla in `after()` con `Str::slug($this->name)`, escludendo nell'update la categoria stessa, con l'errore sul campo `name`.
- `Admin\UpdateOrderStatusRequest` accetta `status` solo tra `shipped`, `delivered` e `cancelled` (`Rule::in`): le transizioni del sistema non sono raggiungibili dall'API.

## API Resource

- Mai restituire modelli o array grezzi.
- Relazioni con `whenLoaded()`, conteggi con `whenCounted()`.
- Importi come interi in centesimi (`price`, `subtotal`, `total`); il frontend li formatta.
- Date in ISO 8601 (`toIso8601String()`).
- Enum esposti come oggetto: `{ "value": "paid", "label": "Pagato" }`.
- Elenchi paginati con `ProductResource::collection($paginator)`, che produce `data`, `links` e `meta`.
- Resource distinte quando la forma cambia: `ProductResource` (pubblica) e `Admin\ProductResource` (con SKU, stock reale, `stock_status`, stato attivo), `OrderResource` e `Admin\OrderResource` (cliente, `allowed_transitions`, pagamento anche fallito), `OrderStatusChangeResource` (senza autore né `reason`) e `Admin\OrderStatusChangeResource` (con `changed_by`, `reason` e descrizione con l'autore), `CategoryResource` e `Admin\CategoryResource` (con `products_count` e `can_delete`). Le forme sono nella skill `ecommerce-domain`.
- La descrizione di una riga di cronologia si compone in un solo punto, il metodo `OrderStatusChange::description(bool $withAuthor)`, che le due Resource chiamano. La variante admin legge `changedBy`: il controller carica `statusChanges.changedBy` con l'eager loading, altrimenti `shouldBeStrict` segnala il lazy loading.
- Nessun dato sensibile nelle Resource: mai password, segreti TOTP, codici di recupero o token.

## Action

- `App\Actions\{Area}\{Verbo}{Oggetto}Action`, classe `final`, un solo metodo pubblico `execute()`.
- Dipendenze iniettate nel costruttore.
- Più scritture correlate stanno in `DB::transaction()`.
- In caso di problema lanciano eccezioni di dominio, non restituiscono codici d'errore.
- Chiamate a servizi esterni (pagamenti, rimborsi, assistente AI) mai dentro una transazione.
- **Ordine dei lock:** dentro una transazione si blocca sempre prima l'utente (`lockForUpdate()` sulla riga di `users`) e poi le risorse collegate (richieste di cambio email, carrello, ordini). Nelle Action guidate da un token (es. conferma e annullamento del cambio email) si legge la risorsa **senza** lock solo per ricavare l'utente, poi si blocca l'utente e si rilegge la risorsa con il lock, ricontrollando che sia ancora valida. **Motivo:** due Action che bloccano le stesse righe in ordine opposto si attendono a vicenda e MySQL ne annulla una con un deadlock (errore 1213, un 500 per il client).
- Cambi di stato dell'ordine:
  - `ChangeOrderStatusAction::execute(Order $order, OrderStatus $to, ?User $by = null, ?string $reason = null)` è l'unico punto che cambia `orders.status`: apre sempre `DB::transaction()` (annidata con savepoint quando il chiamante ne ha già una), rilegge l'ordine con `lockForUpdate()`, controlla `canTransitionTo()` (altrimenti `InvalidOrderTransitionException`), valorizza il timestamp corrispondente, scrive la riga in `order_status_changes` e invia la notifica al cliente solo per le transizioni dell'elenco chiuso della skill `ecommerce-domain` (`paid → shipped`, `shipped → delivered`, `paid → cancelled`). La notifica usa `afterCommit()`, così parte solo se la transazione va a buon fine. Così anche spedito e consegnato, chiamati dal controller, sono transazionali senza che il controller usi `DB`.
  - La creazione dell'ordine in `PlaceOrderAction` scrive la riga iniziale (`from_status` null, `to_status` `pending`) nella stessa transazione.
  - `CancelOrderAction::execute(Order $order, User $by)` è l'unica via di annullamento di un ordine pagato, usata dal controller del cliente e da quello admin. Ordine dei passi: controllo che l'ordine sia `paid` con un pagamento `succeeded`; rimborso con `PaymentGateway::refund()` **fuori** dalla transazione, con l'id del pagamento come chiave di idempotenza; se fallisce, `RefundFailedException` senza nessun cambiamento; se riesce, `DB::transaction()` con `lockForUpdate()` su ordine, pagamento e prodotti, nuovo controllo dello stato sotto lock, ripristino dello stock, pagamento a `refunded` e `ChangeOrderStatusAction` con l'autore (dettagli nella skill `ecommerce-domain`).
- La dashboard admin si calcola in `GetDashboardStatsAction`, perché i controller non contengono query complesse.
- Le categorie si eliminano con `DeleteCategoryAction`, che lancia `CategoryHasProductsException` (409) se esistono prodotti collegati, contati con `withTrashed()`.

## Modelli

- Attributi assegnabili espliciti con `#[Fillable]`; ogni attributo sensibile in `#[Hidden]`; cast nel metodo `casts()` (enum, boolean, datetime, integer, encrypted).
- Relazioni con tipo di ritorno dichiarato.
- Scope locali con l'attributo `#[Scope]`, come gli altri attributi di Laravel già usati nei modelli (`#[Fillable]`, `#[Hidden]`, `#[ObservedBy]`). Prodotti: `search` (nome e descrizione breve, catalogo pubblico), `searchByNameOrSku` (elenco admin), `inCategory`, `priceBetween`, `inStock`, `active`, `newArrivals`, `lowStock`. Ordini: `visibleToCustomer`, `failedPayments` (al plurale, perché `failed_payment` è l'accessor), `search` (numero o email del cliente) e `paidInMonth(string $month)`, con i limiti del mese nel fuso dell'applicazione, usato sia dalla dashboard sia dal filtro `paid_month` dell'elenco admin; ordinamenti con uno scope `sortBy` che accetta solo `newest`, `price_asc` e `price_desc` e aggiunge `id` come secondo criterio.
- Valori calcolati (`is_new`, `availability` del prodotto) in un solo punto, un accessor o una piccola classe dedicata, letti dalle soglie di `config/shop.php`: la Resource li espone, i controller non li calcolano.
- `Model::shouldBeStrict(! app()->isProduction())` in `AppServiceProvider`: in sviluppo il lazy loading e gli attributi mancanti generano un errore.
- Eager loading esplicito dove si caricano le relazioni.
- Slug generati in un Observer. `ProductObserver` e `CategoryObserver` lo impostano solo in `creating`: lo slug della categoria non cambia più, nemmeno se cambia il nome.
- `ProductObserver` rende lo slug unico anche rispetto ai prodotti eliminati con soft delete: in caso di collisione aggiunge un suffisso numerico (`-2`, `-3`…), troncando prima la base perché slug e suffisso stiano in 255 caratteri. Se il nome non produce uno slug (es. «!!!»), la base è `prodotto` (`categoria` per le categorie). Lo slug non è fillable e l'Observer lo genera sempre dal nome, anche se viene passato. Per le categorie la collisione non arriva mai all'Observer, perché la Form Request la respinge con un 422 su `name`.
- `OrderStatusChange` ha `const UPDATED_AT = null`, cast di `from_status` e `to_status` a `OrderStatus` e la relazione `changedBy()` verso `User`.
- `SecurityEvent` ha `const UPDATED_AT = null` e cast di `type` a `SecurityEventType` e di `metadata` ad `array`.

## Middleware personalizzati

- `ForceJsonResponse`: imposta `Accept: application/json` su ogni richiesta API; aggiunto al gruppo `api`.
- `EnsureUserHasRole`: alias `role`, usato come `role:admin`, risponde 403 con JSON.
- `EnsureIdentityConfirmed`: alias `identity.confirmed`, descritto nella skill `account-security`.
- Registrazione e alias in `bootstrap/app.php`.

## Errori

- Eccezioni di dominio in `App\Exceptions` con metodo `render()` che restituisce JSON e lo status corretto (`InsufficientStockException` → 409, `InvalidOrderTransitionException` → 422, `PaymentFailedException` → 402, `CategoryHasProductsException` → 409, `RefundFailedException` → 502 con `code` `refund_failed` e il messaggio «Il rimborso non è riuscito: l'ordine non è stato annullato. Riprova tra poco.»).
- Forma uniforme delle risposte d'errore: `{ "message": "...", "errors": { ... }, "code": "..." }`, con `errors` presente solo per il 422 con errori sui campi e `code` presente solo quando il frontend deve distinguere errori con lo stesso status. I valori di `code` sono stringhe stabili in snake_case (es. `two_factor_challenge_expired`), definite nell'eccezione che le produce (es. `InvalidCredentialsException` → `invalid_credentials`, `TwoFactorChallengeExpiredException` → `two_factor_challenge_expired`).
- Le risposte 429 includono l'header `Retry-After`, esposto al frontend da `config/cors.php`.
- Gestione centralizzata in `bootstrap/app.php` con `withExceptions()`: modello non trovato → 404 JSON con messaggio in italiano, autorizzazione negata → 403, `InvalidSignatureException` del middleware `signed` → 403 JSON con lo stesso messaggio italiano dell'eccezione che la verifica email lancia per un link non valido (skill `account-security`), così firma non valida, utente inesistente e hash sbagliato sono indistinguibili. Il renderer del 404 intercetta ogni `HttpException` con status 404, non solo `NotFoundHttpException`: il 404 di una Policy con `denyAsNotFound()` arriva come `HttpException` generica, e senza questo filtro avrebbe il messaggio inglese «Not Found».
- Dati sensibili mai rimandati indietro o salvati in sessione: in `withExceptions()` si aggiungono a `dontFlash` i campi della carta insieme a quelli delle password:

```php
$exceptions->dontFlash([
    'current_password',
    'password',
    'password_confirmation',
    'payment.number',
    'payment.cvc',
]);
```

- Numero della carta, CVC e scadenza non compaiono mai in log, messaggi di eccezione, eventi o risposte: le eccezioni di pagamento portano solo il riferimento dell'ordine e l'esito.

## Enum, eventi, notifiche, servizi

- Enum string-backed in `App\Enums` con metodo `label()` in italiano, con le parole fisse di `design/design-system/voice-and-content.md` (es. `OrderStatus`: In attesa, Pagato, Spedito, Consegnato, Annullato).
- Listener e notifiche implementano `ShouldQueue`; in sviluppo girano in modo sincrono perché `QUEUE_CONNECTION=sync`.
- Email come Notification (canale `mail`), testi in italiano.
- Interfacce in `App\Contracts`, implementazioni in `App\Services`, binding in `AppServiceProvider` guidato da config.

## Migration

- Lo schema completo (tipi, nullable, default, chiavi, indici, cancellazione) e le decisioni che lo hanno fissato sono in `docs/database/er-diagram.md`. Dalla Fase 2 (migration e modelli) le migration sono la fonte: ogni migration che cambia il database aggiorna quel documento nello stesso task.
- `foreignId()->constrained()` con `cascadeOnDelete()`, `restrictOnDelete()` o `nullOnDelete()` scelti consapevolmente e motivati nel piano.
- FK verso `users`: il progetto non elimina utenti, li anonimizza (skill `account-security`, "Eliminazione dell'account"); ogni FK dichiara comunque il comportamento. `restrictOnDelete()` per `orders.user_id` e `order_status_changes.changed_by`; `cascadeOnDelete()` per `addresses`, `carts`, `security_events`, `email_change_requests` e la tabella delle passkey.
- Indici sulle colonne usate nei filtri e negli ordinamenti.
- `unsignedInteger` per stock e prezzi unitari (`products.price`, `order_items.unit_price`, `orders.shipping_cost`); `unsignedBigInteger` per le somme (`order_items.line_total`, `orders.subtotal`, `orders.total`, `payments.amount`).
- `orders.number` è nullable e unico: dipende dall'id, quindi `PlaceOrderAction` lo scrive subito dopo l'insert, nella stessa transazione.
- `product_images`: indice su (`product_id`, `position`) **non** unico, così il riordino non viola il vincolo a metà aggiornamento.
- `order_status_changes`: `order_id` con `cascadeOnDelete()` (la cronologia non esiste senza l'ordine), `changed_by` nullable con `restrictOnDelete()` (null significa sistema: un utente eliminato non deve diventare "sistema"; il progetto non elimina utenti che hanno ordini o transizioni), `from_status` nullable, `to_status`, `reason` nullable, solo `timestamp('created_at')` non nullable e **senza default**, indice su (`order_id`, `created_at`).
- `security_events`: solo `timestamp('created_at')` non nullable e **senza default**, indice su (`user_id`, `created_at`).
- Niente `useCurrent()` sulle colonne data: Laravel scrive le date nel fuso dell'applicazione (`Europe/Rome`), mentre `CURRENT_TIMESTAMP` di MySQL userebbe il fuso della sessione (UTC). Un insert fuori da Eloquent senza data deve fallire, non salvare un'ora UTC in un database in ora italiana. Eloquent valorizza sempre `created_at` anche con `const UPDATED_AT = null`.
- `orders` con indice composto (`status`, `paid_at`): serve al filtro `status`, a `failed_payment` (`cancelled` e `paid_at` null) e a `orders_to_ship`. I tentativi rifiutati si riconoscono dall'ordine, non da `payments.status`, che non ha indice.
- Il fuso dell'applicazione è `Europe/Rome` (`config/app.php`): il "mese corrente" della dashboard e le date delle Resource dipendono da questo.

## Factory

- Ogni modello ha una factory, con dati realistici in italiano (`fake('it_IT')`, locale Faker `it_IT` in `.env`).
- Stati espliciti per ogni variante utile ai test:
  - `UserFactory`: `admin()`, `customer()`, `unverified()`, `withTwoFactor()` (segreto, codici di recupero e `two_factor_confirmed_at`), `withPasskey(string $name = 'MacBook di prova')` (una passkey finta di `PasskeyFactory`, senza doppia autenticazione: la sua chiave pubblica non appartiene a nessun autenticatore, quindi verifica solo con la cerimonia finta dei test), `withPendingEmailChange(string $newEmail)` (richiesta in sospeso non scaduta); `password_changed_at` è sempre valorizzata
  - `CategoryFactory`: nome e descrizione realistici; lo slug lo genera l'Observer
  - `ProductFactory`: `inactive()`, `outOfStock()`, `lastUnits(int $stock = 2)` (sotto `last_units_threshold`), `lowStock(int $stock = 4)` (entro `low_stock_threshold`), `newArrival()` e `older()` (`created_at` dentro o fuori `new_arrival_days`), più `trashed()` per il soft delete; la categoria si assegna con `for(Category::factory())` o `recycle()`
  - `OrderFactory`: `pending()`, `paid()`, `shipped()`, `delivered()`, `cancelledAfterPayment()` (con `paid_at` e `cancelled_at`), `failedPayment(string $reason = 'Carta rifiutata dalla banca')` (`cancelled` senza `paid_at`), ognuno con i timestamp coerenti e, in `afterCreating()`, le righe di cronologia e la riga in `payments` corrispondenti (`succeeded`, `refunded` per `cancelledAfterPayment()`, `failed` per `failedPayment()`)
  - `AddressFactory`: indirizzi italiani validi (CAP di 5 cifre, sigla di provincia, telefono), `country` sempre `IT`
- Le relazioni si creano con `for()`, `has()` e `recycle()`.
- Gli stati coerenti si costruiscono con `afterCreating()` quando servono (es. righe d'ordine e totali).
- I test usano solo le factory, mai i seeder.

## Seeder

- Un seeder per responsabilità, tutti richiamati da `DatabaseSeeder` nell'ordine corretto:
  - `UserSeeder`: l'admin Marta Lenzi e i clienti demo con credenziali fisse, più clienti casuali
  - `CategorySeeder`: le sei categorie dei dati demo, con slug e descrizione, elencate nella skill `ecommerce-domain` ("Viaggio" senza prodotti)
  - `ProductSeeder`: prodotti per categoria con immagini da `database/seeders/assets/`, nomi secondo `design/design-system/voice-and-content.md`, `created_at` deterministici per avere novità e prodotti più vecchi
  - `AddressSeeder`: indirizzi per i clienti
  - `OrderSeeder`: ordini in tutti gli stati, distribuiti sugli ultimi mesi e anche nel mese corrente, con cronologia e pagamenti coerenti
- I dati fissi (categorie, prodotti principali, utenti demo) sono deterministici; i dati di contorno possono essere casuali.
- Le estrazioni decise dai seeder (giorni, ore, clienti, stati, righe d'ordine) usano un `Random\Randomizer` con motore `Mt19937` e seme proprio, non `fake()`. Faker resta per nomi e indirizzi, con `fake()->seed()`. **Motivo:** `fake()->seed()` imposta il generatore globale `mt_rand` di PHP, e `Faker\Generator::__destruct()` richiama `seed()` senza valore, cioè `mt_srand()` con un seme casuale. Il generatore di un container precedente (un altro test, un altro seeding) viene distrutto dal garbage collector dei cicli in un momento imprevedibile; se succede a metà seeding, tutte le estrazioni successive cambiano e i dati demo non sono più gli stessi (il test di riproducibilità falliva in modo intermittente). Per questo `DatabaseSeeder::run()` chiama prima `gc_collect_cycles()`, che distrugge i vecchi generatori prima di fissare i semi, e lo fa anche `seedDemoShop()` nei test. Il `Randomizer` ha uno stato suo che nessun `mt_srand()` tocca.
- Le credenziali demo sono documentate nel README.
- `make fresh` ricrea il database pronto per la demo; lo script di avvio esegue i seeder solo se il database è vuoto.
- `DatabaseSeeder` esegue tutti i seeder in un'unica `DB::transaction()`: un seeding interrotto non lascia il database a metà, così `db:seed-if-empty` (che controlla solo `users`) al riavvio lo ripete da capo. I file copiati sul disco `public` non sono coperti dalla transazione: un seeding fallito può lasciare immagini orfane, che il `ProductSeeder` rimuove al tentativo successivo svuotando la cartella `products/` prima di copiare.
- Il mese corrente ha sempre almeno un ordine pagato, anche seedando il primo giorno del mese: l'`OrderSeeder` crea un ordine fisso con `paid_at` a metà tra l'inizio del mese e `now()`.

## Test manuale con Postman

Con l'autenticazione a cookie Postman va configurato così:

1. Environment con `base_url = http://localhost:8000` e `xsrf_token` vuoto.
2. Header su tutte le richieste della collection: `Accept: application/json`, `Origin: http://localhost:5173`, `X-XSRF-TOKEN: {{xsrf_token}}`. L'header `Origin` fa riconoscere a Sanctum la richiesta come proveniente dal frontend.
3. Script post-response a livello di collection, che aggiorna il token dopo ogni risposta:

```js
const token = pm.cookies.get('XSRF-TOKEN');
if (token) {
    pm.environment.set('xsrf_token', decodeURIComponent(token));
}
```

4. Prima richiesta di ogni sessione di test: `GET {{base_url}}/sanctum/csrf-cookie`, poi `POST {{base_url}}/api/v1/login`.

Quando proponi verifiche manuali, indica per ogni richiesta metodo, URL, body JSON e risposta attesa con status code.

## Checklist per un nuovo endpoint

1. Rotta nel file dell'area corretta
2. Form Request con `authorize()` e `rules()`
3. Policy aggiornata
4. Action se c'è logica di business
5. Resource
6. Factory e stati necessari
7. Test Pest secondo la skill `testing`
8. `make test-backend` verde
9. Richiesta Postman da far provare all'utente
