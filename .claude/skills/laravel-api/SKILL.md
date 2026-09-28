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
├── Contracts/                 # interfacce (PaymentGateway, Security/...)
├── Enums/                     # OrderStatus, PaymentStatus, UserRole, SecurityEventType
├── Events/  Listeners/
├── Exceptions/                # eccezioni di dominio con render()
├── Http/
│   ├── Controllers/Api/V1/{Auth,Catalog,Cart,Checkout,Account,Admin}/
│   ├── Middleware/            # ForceJsonResponse, EnsureUserHasRole, EnsureIdentityConfirmed
│   ├── Requests/{Area}/{Model}/
│   └── Resources/             # pubbliche; Resources/Admin/ per le varianti admin
├── Models/
├── Notifications/             # tutte le email, in italiano
├── Observers/
├── Policies/
├── Providers/
└── Services/{Area}/           # implementazioni delle interfacce
config/
├── shop.php                   # spedizione, soglia scorte basse
└── security.php               # tempi di conferma e scadenze
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
- `public.php`: catalogo, health check, registrazione, login, recupero password, conferme via link.
- `customer.php`: middleware `auth:sanctum`.
- `admin.php`: middleware `auth:sanctum` e `role:admin`, prefisso `admin`, nome `admin.`.
- Usa `Route::apiResource` quando possibile; per le azioni singole usa controller invocabili (`__invoke`).
- Prodotti e categorie pubblici usano il route model binding sullo slug: `{product:slug}`.
- Rate limiter nominati definiti in `AppServiceProvider` (vedi anche la skill `account-security`).

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

        return ProductResource::make($product->load(['categories', 'images']))
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
- Non usare `before()` per concedere tutto all'admin: ogni permesso è esplicito.
- Il cliente accede solo alle proprie risorse (`$order->user_id === $user->id`).
- Il middleware `role:admin` protegge l'area admin come prima barriera; le Policy restano comunque obbligatorie.

## Form Request

- Una per azione di scrittura o per validare i filtri di un elenco: `Store{Model}Request`, `Update{Model}Request`, `Index{Model}Request`.
- Regole in forma di array, con `Rule::enum()`, `Rule::exists()`, `Rule::unique()->ignore()` negli update.
- `prepareForValidation()` per normalizzare l'input (trim, lowercase delle email, cast).
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
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'integer', 'min:1'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')],
        ];
    }
}
```

## API Resource

- Mai restituire modelli o array grezzi.
- Relazioni con `whenLoaded()`, conteggi con `whenCounted()`.
- Importi come interi in centesimi (`price`, `subtotal`, `total`); il frontend li formatta.
- Date in ISO 8601 (`toIso8601String()`).
- Enum esposti come oggetto: `{ "value": "paid", "label": "Pagato" }`.
- Elenchi paginati con `ProductResource::collection($paginator)`, che produce `data`, `links` e `meta`.
- Resource distinte quando la forma cambia: `ProductResource` (pubblica) e `Admin\ProductResource` (con SKU, stock reale, stato attivo).
- Nessun dato sensibile nelle Resource: mai password, segreti TOTP, codici di recupero o token.

## Action

- `App\Actions\{Area}\{Verbo}{Oggetto}Action`, classe `final`, un solo metodo pubblico `execute()`.
- Dipendenze iniettate nel costruttore.
- Più scritture correlate stanno in `DB::transaction()`.
- In caso di problema lanciano eccezioni di dominio, non restituiscono codici d'errore.
- Chiamate a servizi esterni (pagamenti) mai dentro una transazione.

## Modelli

- `$fillable` esplicito; `$hidden` per ogni attributo sensibile; cast nel metodo `casts()` (enum, boolean, datetime, integer, encrypted).
- Relazioni con tipo di ritorno dichiarato.
- Filtri come scope locali: `search`, `inCategory`, `priceBetween`, `inStock`, `active`.
- `Model::shouldBeStrict(! app()->isProduction())` in `AppServiceProvider`: in sviluppo il lazy loading e gli attributi mancanti generano un errore.
- Eager loading esplicito dove si caricano le relazioni.
- Slug generati in un Observer.

## Middleware personalizzati

- `ForceJsonResponse`: imposta `Accept: application/json` su ogni richiesta API; aggiunto al gruppo `api`.
- `EnsureUserHasRole`: alias `role`, usato come `role:admin`, risponde 403 con JSON.
- `EnsureIdentityConfirmed`: alias `identity.confirmed`, descritto nella skill `account-security`.
- Registrazione e alias in `bootstrap/app.php`.

## Errori

- Eccezioni di dominio in `App\Exceptions` con metodo `render()` che restituisce JSON e lo status corretto (`InsufficientStockException` → 409, `InvalidOrderTransitionException` → 422, `PaymentFailedException` → 402).
- Forma uniforme delle risposte d'errore: `{ "message": "...", "errors": { ... } }`, con `errors` presente solo per il 422.
- Gestione centralizzata in `bootstrap/app.php` con `withExceptions()`: modello non trovato → 404 JSON con messaggio in italiano, autorizzazione negata → 403.

## Enum, eventi, notifiche, servizi

- Enum string-backed in `App\Enums` con metodo `label()` in italiano.
- Listener e notifiche implementano `ShouldQueue`; in sviluppo girano in modo sincrono perché `QUEUE_CONNECTION=sync`.
- Email come Notification (canale `mail`), testi in italiano.
- Interfacce in `App\Contracts`, implementazioni in `App\Services`, binding in `AppServiceProvider` guidato da config.

## Migration

- `foreignId()->constrained()` con `cascadeOnDelete()`, `restrictOnDelete()` o `nullOnDelete()` scelti consapevolmente e motivati nel piano.
- Indici sulle colonne usate nei filtri e negli ordinamenti.
- `unsignedInteger` per importi e stock.

## Factory

- Ogni modello ha una factory, con dati realistici in italiano (`fake('it_IT')`, locale Faker `it_IT` in `.env`).
- Stati espliciti per ogni variante utile ai test:
  - `UserFactory`: `admin()`, `customer()`, `unverified()`, `withTwoFactor()`
  - `ProductFactory`: `inactive()`, `outOfStock()`, `lowStock()`
  - `OrderFactory`: `pending()`, `paid()`, `shipped()`, `delivered()`, `cancelled()`, ognuno con i timestamp coerenti
- Le relazioni si creano con `for()`, `has()` e `recycle()`.
- Gli stati coerenti si costruiscono con `afterCreating()` quando servono (es. righe d'ordine e totali).
- I test usano solo le factory, mai i seeder.

## Seeder

- Un seeder per responsabilità, tutti richiamati da `DatabaseSeeder` nell'ordine corretto:
  - `UserSeeder`: admin e clienti demo con credenziali fisse, più clienti casuali
  - `CategorySeeder`: albero fisso e coerente con il tema del negozio
  - `ProductSeeder`: prodotti per categoria con immagini da `database/seeders/assets/`
  - `AddressSeeder`: indirizzi per i clienti
  - `OrderSeeder`: ordini in tutti gli stati, distribuiti sugli ultimi mesi
- I dati fissi (categorie, prodotti principali, utenti demo) sono deterministici; i dati di contorno possono essere casuali.
- Le credenziali demo sono documentate nel README.
- `make fresh` ricrea il database pronto per la demo; lo script di avvio esegue i seeder solo se il database è vuoto.

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
