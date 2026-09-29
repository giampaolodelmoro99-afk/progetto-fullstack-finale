---
name: react-architecture
description: Architettura del frontend React del progetto — struttura per feature, React Router con layout e rotte protette, pagine statiche con ancore, categorie e impostazioni del negozio lette dall'API, carrello, checkout con i dati della carta solo in memoria e conferma d'ordine, pagine raggiunte dai link email, area account con profilo, ordini e sicurezza, area admin (dashboard, prodotti con immagini, categorie, ordini, modifiche non salvate), TanStack Query per i dati del server, client axios con cookie Sanctum e CSRF, gestione degli errori 401, 419, 423 e 429, autenticazione, form con React Hook Form e Zod, stati di una vista e convenzioni di codice. Usala per qualsiasi modifica in frontend/ che non sia puramente visiva.
---

# React — architettura del frontend

Riferimenti visivi: `design/mockups/01-negozio.pdf` (negozio), `design/mockups/02-acquisto.pdf` (carrello, checkout, conferma) `design/mockups/03-account.pdf` (accesso, registrazione, pagine dai link email, area account, sicurezza) e `design/mockups/04-admin.pdf` (pannello di amministrazione). Testi e formati: `design/design-system/voice-and-content.md`.

## Stack

React + Vite in JavaScript, React Router (data router, pacchetto `react-router`), TanStack Query, axios, React Hook Form + Zod, MUI. Test con Vitest, React Testing Library e MSW (skill `testing`).

## Struttura

```
src/
├── app/
│   ├── App.jsx
│   ├── providers/            # AppProviders, AuthProvider, NotificationProvider, IdentityConfirmationProvider
│   └── router/               # router.jsx, paths.js, ProtectedRoute.jsx, GuestRoute.jsx, AdminRoute.jsx
├── api/
│   ├── httpClient.js
│   ├── queryClient.js
│   └── queryKeys.js
├── features/
│   ├── home/                 # hero, categorie in evidenza, novità; heroContent.js
│   ├── info/                 # pagine statiche: assistenza e note legali
│   ├── auth/                 # api/ hooks/ components/ pages/ schemas.js
│   ├── security/             # doppia autenticazione, passkey, conferma identità
│   ├── catalog/              # elenco, dettaglio, correlati; categoryImages.js
│   ├── cart/
│   ├── checkout/
│   ├── account/
│   ├── settings/             # api/ hooks/: useShopSettings, useAdminSettings
│   └── admin/                # navigation.js; dashboard/ products/ categories/ orders/, ognuna con api/ hooks/ components/ pages/
├── components/               # condivisi: feedback/, form/, data-display/, navigation/
├── layouts/                  # StoreLayout, AuthLayout, AccountLayout, AdminLayout
├── hooks/                    # hook condivisi, es. useDebouncedValue
├── theme/
├── test/                     # setup, utility e mock dei test
├── utils/                    # format.js, price.js, normalizeApiError.js, passwordRules.js
└── pages/                    # NotFoundPage, ErrorPage
```

Una feature contiene tutto ciò che le appartiene, compresi i suoi test. Un elemento finisce in `components/` solo quando lo usano almeno due feature.

## Convenzioni di codice

- Nessun commento inline.
- File sempre completi.
- Solo componenti funzione, un componente per file, nome del file uguale al nome del componente, PascalCase con estensione `.jsx`.
- Le pagine usano `export default`, necessario al lazy loading; componenti, hook e utility usano named export.
- Hook in `useSomething.js`, utility in camelCase.
- Nomi in inglese, testi dell'interfaccia in italiano secondo `voice-and-content.md`.
- Import tramite alias `@/` verso `src/`.
- Nessuna chiamata HTTP nei componenti: passano sempre dagli hook della feature.

## Routing

- `createBrowserRouter` in `app/router/router.jsx`, `RouterProvider` (da `react-router/dom`) in `App.jsx`.
- Percorsi centralizzati in `app/router/paths.js`; nessuna stringa di percorso sparsa nei componenti.
- Layout come rotte genitore con `<Outlet />`:
  - `StoreLayout` (con il banner di email non verificata, nascosto nelle rotte di `AuthLayout`): `/`, `/products`, `/products/:slug`, `/cart`, `/checkout`, `/checkout/success/:orderId`, `/assistenza`, `/note-legali`
  - `AccountLayout` dentro `StoreLayout`: `/account/profile`, `/account/addresses`, `/account/orders`, `/account/orders/:orderId`, `/account/security`
  - `AuthLayout` dentro `StoreLayout` (header, navigazione e footer restano visibili, il layout centra la card): `/login`, `/login/two-factor`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, `/confirm-email-change`, `/cancel-email-change`
  - `AdminLayout`: `/admin`, `/admin/products`, `/admin/products/new`, `/admin/products/:productId/edit`, `/admin/categories`, `/admin/orders`, `/admin/orders/:orderId`
- `ProtectedRoute`: se l'utente non è autenticato reindirizza a `/login` salvando la pagina di provenienza in `state.from`, così dopo il login si torna lì.
- `GuestRoute`: login, verifica in due passaggi, registrazione e password dimenticata reindirizzano a `/` se l'utente è già autenticato. Le pagine raggiunte dai link email non sono né protette né ospiti: funzionano in entrambi i casi.
- `/account` reindirizza a `/account/profile`.
- `AdminRoute`: se l'utente non è autenticato si comporta come `ProtectedRoute`; se è autenticato ma non è admin reindirizza a `/`.
- Carrello, checkout e conferma d'ordine sono rotte protette.
- Il checkout richiede email verificata: se non lo è, la pagina mostra l'invito a verificarla invece del form.
- Le pagine admin si caricano con `React.lazy` e `Suspense`, con un fallback di caricamento.
- `errorElement` con `ErrorPage`; rotta `*` con `NotFoundPage`.
- I filtri del catalogo vivono nella query string (`useSearchParams`): i link si possono condividere e il pulsante indietro del browser funziona.
- Le pagine raggiunte da link nelle email leggono i parametri dalla query string e chiamano l'API al caricamento.

```js
export const paths = {
  home: '/',
  products: '/products',
  product: (slug) => `/products/${slug}`,
  category: (slug) => `/products?category=${slug}`,
  newArrivals: '/products?new=1',
  support: '/assistenza',
  supportSection: (section) => `/assistenza#${section}`,
  legal: '/note-legali',
  legalSection: (section) => `/note-legali#${section}`,
  cart: '/cart',
  checkout: '/checkout',
  checkoutSuccess: (orderId) => `/checkout/success/${orderId}`,
  login: '/login',
  loginTwoFactor: '/login/two-factor',
  register: '/register',
  forgotPassword: '/forgot-password',
  resetPassword: '/reset-password',
  verifyEmail: '/verify-email',
  confirmEmailChange: '/confirm-email-change',
  cancelEmailChange: '/cancel-email-change',
  account: '/account',
  accountProfile: '/account/profile',
  accountAddresses: '/account/addresses',
  accountOrders: '/account/orders',
  accountOrder: (orderId) => `/account/orders/${orderId}`,
  accountSecurity: '/account/security',
  admin: '/admin',
  adminProducts: '/admin/products',
  adminProductNew: '/admin/products/new',
  adminProductEdit: (productId) => `/admin/products/${productId}/edit`,
  adminCategories: '/admin/categories',
  adminOrders: '/admin/orders',
  adminOrder: (orderId) => `/admin/orders/${orderId}`,
};
```

## Pagine statiche (`features/info`)

Due pagine in `StoreLayout`, con testi fissi nel frontend e un'ancora (`id`) su ogni sezione:

| Pagina | Percorso | Sezioni e ancore |
|---|---|---|
| Assistenza | `/assistenza` | `#spedizioni`, `#resi`, `#garanzia`, `#contatti` |
| Note legali | `/note-legali` | `#condizioni-di-vendita`, `#diritto-di-recesso`, `#privacy`, `#cookie` |

- Gli identificativi delle sezioni sono costanti esportate dalla feature e usate sia dalla pagina sia dai link, mai stringhe ripetute.
- React Router non scorre fino all'ancora: la pagina legge `location.hash` e porta in vista la sezione corrispondente, anche quando cambia solo l'hash.
- Costo di spedizione e soglia della spedizione gratuita arrivano da `useShopSettings()` (sezione "Impostazioni del negozio"); consegna in 24/48 ore, reso entro 30 giorni e garanzia di 2 anni sono testi fissi.

## Navigazione e footer del negozio

Dal mockup `01-negozio.pdf`:

- Le categorie non sono un elenco fisso: menu principale, `Drawer` mobile e footer le leggono con `useCategories()` da `GET /categories`, nell'ordine ricevuto. Durante il caricamento, Skeleton al posto delle voci; in caso di errore restano solo "Novità" e le altre voci fisse, senza messaggio.
- Menu principale: "Novità" porta a `paths.newArrivals`; ogni categoria porta a `paths.category(slug)`.
- Footer, colonna **Categorie**: le categorie ricevute, con `paths.category(slug)`.
- Footer, colonna **Assistenza**:
  - "Spedizioni e consegne" → `/assistenza#spedizioni`
  - "Resi e rimborsi" → `/assistenza#resi`
  - "Garanzia" → `/assistenza#garanzia`
  - "Stato dell'ordine" → `/account/orders` (protetta: un ospite passa dal login e poi torna lì)
  - "Contatti" → `/assistenza#contatti`
- Footer, colonna **Note legali**:
  - "Condizioni di vendita" → `/note-legali#condizioni-di-vendita`
  - "Diritto di recesso" → `/note-legali#diritto-di-recesso`
  - "Privacy" → `/note-legali#privacy`
  - "Cookie" → `/note-legali#cookie`
  - "Note legali" → `/note-legali`

## Home

### Hero

- Testi fissi nel frontend, in `features/home/heroContent.js`: etichetta overline, titolo, paragrafo, testo del pulsante e slug del prodotto (`cuffie-aria`).
- Nome, prezzo e immagine del prodotto arrivano dall'API con l'hook del dettaglio prodotto, sullo slug configurato.
- Stati:
  - caricamento: testi visibili, Skeleton al posto di prezzo e immagine
  - errore o 404: testi visibili, nessun prezzo né immagine, pulsante verso il catalogo
  - contenuto: prezzo formattato, immagine con `alt` e pulsante verso `paths.product(slug)`

### Categorie in evidenza

- Le categorie arrivano da `GET /categories` (la stessa query del menu), con nome e descrizione: una tessera per categoria, qualunque sia il loro numero.
- Le immagini sono statiche nel frontend, in `features/catalog/categoryImages.js`: una mappa slug → immagine importata, con un'immagine di ripiego per gli slug che non ne hanno una. Il backend non conosce le immagini delle categorie.

### Novità

- Anteprima di `GET /products?new=1&sort=newest` con quattro prodotti e il link "Vedi tutte le novità" verso `paths.newArrivals`.

## Impostazioni del negozio (`features/settings`)

- `useShopSettings()` legge `GET /shop/settings` (`queryKeys.settings.shop`); `useAdminSettings()` legge `GET /admin/settings` (`queryKeys.settings.admin`). Entrambe con `staleTime: Infinity`: i valori cambiano solo con un nuovo deploy.
- Nessun importo o soglia della configurazione (`shipping_cost`, `free_shipping_threshold`, `low_stock_threshold`) si scrive a mano nel frontend: rassicurazioni, pagina prodotto, carrello, checkout, pagine statiche, dashboard e form prodotto li leggono da qui. I valori che arrivano già in una risposta (es. `summary` del carrello) si usano da lì.
- Durante il caricamento il testo con l'importo mostra uno Skeleton in linea; in caso di errore l'elemento che contiene l'importo si omette, senza valori di ripiego.

## Catalogo

- Filtri nella query string: `category`, `min_price`, `max_price`, `in_stock`, `new`, `sort`, `page`.
- Select "Ordina per":

| Opzione | Valore di `sort` |
|---|---|
| Novità (predefinita) | `newest` |
| Prezzo crescente | `price_asc` |
| Prezzo decrescente | `price_desc` |

- Il valore predefinito non compare nella query string; cambiare ordinamento o filtri riporta a pagina 1.
- I limiti dello slider del prezzo arrivano da `meta.price_range` della risposta, in centesimi.
- Il numero di prodotti mostrato sotto il titolo arriva da `meta.total`.
- Il dettaglio prodotto carica i correlati con `GET /products/{slug}/related`; se l'elenco è vuoto la sezione non si mostra.
- `is_new` e `availability` arrivano dall'API: il frontend li mostra, non li calcola.
- Il selettore di quantità della pagina prodotto va da 1 a `max_quantity` del prodotto.
- Dopo l'aggiunta al carrello compare uno Snackbar "Aggiunto al carrello" con l'azione "Vai al carrello"; si invalida `cart`.

## Carrello (`/cart`)

Dal mockup `02-acquisto.pdf`. Tutti i valori arrivano dalla `CartResource` (skill `ecommerce-domain`): il frontend non ricalcola totali, conteggi o disponibilità.

- Titolo "Il tuo carrello" con "N articoli" da `summary.items_count`; lo stesso valore alimenta il badge dell'AppBar.
- Ogni riga: selettore di quantità da 1 a `max_quantity`, prezzo unitario "cad." e `line_total`. Le righe non acquistabili (`is_purchasable: false`) mostrano l'etichetta di disponibilità al posto del selettore, "—" al posto del totale e un avviso con "Rimuovi dal carrello".
- Riepilogo con subtotale, spedizione ("Gratuita" quando è 0), totale e, se `free_shipping_remaining` è maggiore di 0, la barra "Ti mancano … per la spedizione gratuita".
- "Procedi al checkout" disabilitato quando `can_checkout` è false, con il motivo accanto ("Rimuovi i prodotti esauriti per procedere al checkout").
- Carrello vuoto: messaggio con "Scopri il negozio" e sezione "Potrebbero piacerti" con `GET /products?in_stock=1&sort=newest&per_page=4`.
- Modifica della quantità e rimozione sono mutation che invalidano `cart`.

## Checkout (`/checkout`)

- Tre passi: Indirizzo, Pagamento, Riepilogo. Il riepilogo dell'ordine a lato (a scomparsa su mobile) legge `cart`.
- Email non verificata: al posto del passo 1 compare l'invito a confermarla con "Invia di nuovo l'email".
- **Stato del checkout:** passo corrente, indirizzo scelto, dati della carta e accettazione delle condizioni vivono in un `useReducer` di `CheckoutPage`, solo in memoria. Ricaricando la pagina il checkout riparte dal passo 1 con i campi vuoti.
- **Passo 1, indirizzo:** card selezionabili degli indirizzi dell'utente (predefinito preselezionato) e form "Nuovo indirizzo" con le regole della skill `ecommerce-domain` replicate nello schema Zod. Il campo "Paese" mostra "Italia" e non è modificabile.
- **Passo 2, pagamento:** intestatario, numero, scadenza `MM/AA` e CVC, con validazione Zod dello stesso formato del backend; nota "Ambiente dimostrativo: nessun addebito reale."
- **Passo 3, riepilogo:** indirizzo e carta ("Carta che termina con 4242") con "Modifica", prodotti, totali, checkbox "Ho letto e accetto le condizioni di vendita" (link a `paths.legalSection('condizioni-di-vendita')`) e pulsante "Paga {totale}". "Paga" è disabilitato senza accettazione o quando `can_checkout` è false; in quel caso compare l'avviso "Disponibilità cambiata" con "Torna al carrello".
- **Esiti di `POST /checkout`:**
  - 201: si svuota lo stato della carta, si invalidano `cart` e `orders` e si naviga a `paths.checkoutSuccess(order.id)`
  - 402: `Alert` "Pagamento non riuscito" con "Modifica la carta", che torna al passo 2 conservando i dati in memoria
  - 409: si invalida `cart` e compare "Disponibilità cambiata" con "Torna al carrello"
  - 422: errori mappati sui campi del passo corrispondente

### Dati della carta

- Esistono solo nello stato in memoria del checkout. Mai in cache di TanStack Query, `localStorage`, `sessionStorage`, URL, query string, `state` della navigazione, log o console.
- Si inviano solo con `POST /checkout`.
- `useMutation` conserva le `variables` nella MutationCache: il payload con la carta **non** si passa come variabile. L'hook `usePlaceOrder(getPayload)` riceve una funzione che legge i dati dallo stato del checkout al momento dell'invio e si chiama con `mutate()` senza argomenti.
- L'errore di axios contiene `config.data`, cioè il corpo della richiesta con la carta, e TanStack Query salva l'errore nello stato della mutation: la funzione API del checkout intercetta l'errore e rilancia quello normalizzato da `normalizeApiError`, senza la richiesta originale.
- I campi della carta hanno `autoComplete` corretto (`cc-name`, `cc-number`, `cc-exp`, `cc-csc`) e `inputMode="numeric"` dove serve.

## Conferma d'ordine (`/checkout/success/:orderId`)

- Legge `GET /orders/{orderId}` con l'hook del dettaglio ordine: la pagina funziona anche ricaricandola e non dipende dallo stato del checkout.
- Mostra "Grazie per il tuo ordine" con l'email dell'utente, numero, data, stato, "Carta che termina con {card_last_four}", indirizzo, prodotti e totali; pulsanti "Vedi il tuo ordine" (`paths.accountOrder(id)`) e "Continua lo shopping".
- 404 (ordine non pagato o di un altro utente): stato d'errore con link agli ordini.

## Accesso (`features/auth`)

Dal mockup `03-account.pdf`; regole e flussi nella skill `account-security`.

- **Login:** 422 con `error.code === 'invalid_credentials'` → solo l'`Alert` "Credenziali non valide", nessun errore mappato sui campi; 429 → `Alert` con «Troppi tentativi. Riprova tra N secondi» da `error.retryAfter`. Se la risposta contiene `two_factor_required`, si naviga a `paths.loginTwoFactor` passando `methods` nello `state` della navigazione (nessun segreto). "Accedi con una passkey" usa `startAuthentication` e si mostra solo se `browserSupportsWebAuthn()`. Dopo l'accesso si torna a `state.from` o alla home.
- **Verifica in due passaggi al login:** modalità iniziale `totp`; i link passano a codice di recupero o passkey (solo se `methods` contiene `passkey`). Aperta senza `state` (ricaricando la pagina) riporta al login. Se `POST /login/two-factor` risponde 422 con `error.code === 'two_factor_challenge_expired'`, si torna al login con il messaggio «La verifica è scaduta: accedi di nuovo.»; un 422 senza quel `code` è un codice sbagliato e mostra l'errore sul campo, restando sulla pagina.
- **Registrazione:** dopo `POST /register` si invalidano `me` e `cart` e si naviga a `paths.verifyEmail` senza parametri.
- **Password dimenticata:** dopo l'invio la stessa pagina mostra "Controlla la tua email", qualunque sia l'indirizzo.

## Pagine raggiunte dai link email

Leggono i parametri dalla query string con `useSearchParams` e chiamano l'API al caricamento con una mutation lanciata una sola volta (protetta dal doppio montaggio di `StrictMode`). Ogni pagina ha gli stati: caricamento, esito positivo, link scaduto o non valido.

| Pagina | Chiamata | Esiti |
|---|---|---|
| `/verify-email` senza parametri | nessuna | "Controlla la tua email" con l'indirizzo dell'utente, link valido 24 ore, "Invia di nuovo il link" (`POST /email/verification-notification`), "Torna al negozio"; da ospite porta al login |
| `/verify-email?id&hash&expires&signature` | `POST /email/verify/{id}/{hash}`, che funziona anche da ospite | "Email confermata" con «Grazie: l'indirizzo {data.email} è confermato.», "Vai al negozio" e "Vai al tuo account", anche se l'email era già verificata; invalida `me`. L'indirizzo mostrato è quello della risposta, non quello dell'utente autenticato, che potrebbe essere un altro. 403 → "Il link è scaduto" con "Invia un nuovo link" (il reinvio richiede l'accesso: da ospite passa dal login) |
| `/reset-password?token&email` | `POST /reset-password/check` | form "Scegli una nuova password" con la sezione "Verifica in due passaggi" se `requires_second_factor`; token non valido → "Il link è scaduto" con "Richiedi un nuovo link" e "Torna all'accesso"; dopo il reset si naviga al login con un messaggio di esito |
| `/confirm-email-change?token` | `POST /email-change/confirm` | "Email aggiornata" con "Vai al tuo account", invalida `me`; errore → link scaduto |
| `/cancel-email-change?token` | `POST /email-change/cancel` | "Richiesta annullata" con "Cambia la password" (`paths.accountSecurity`, da ospite passa dal login) e "Torna al negozio"; errore → link scaduto |

## Area account (`features/account` e `features/security`)

Dal mockup `03-account.pdf`.

- **Menu account nell'header:** per l'ospite l'icona porta a `paths.login`; per l'utente autenticato apre un menu con nome ed email e le voci Profilo, Indirizzi, I miei ordini, Sicurezza ed Esci. Le stesse voci formano la navigazione di `AccountLayout` (lista laterale su desktop, tab su mobile), definite una sola volta in un array condiviso con etichetta, icona e percorso. La voce attiva si ricava dalla rotta corrente.
- **Esci:** `logout()` di `useAuth`, poi navigazione alla home.
- **Profilo:**
  - "Dati personali": form del nome.
  - Email con lo stato di verifica da `user.email_verified_at`.
  - Cambio email: "Cambia email" chiama prima la conferma esplicita di `IdentityConfirmationProvider` (con la descrizione «Prima di cambiare l'email, conferma che sei tu.», valida sia con sola password sia con secondo fattore), poi apre il `Dialog` "Nuova email" che invia `POST /me/email`. Se la conferma scade nel frattempo, il 423 lo gestisce l'interceptor. Dopo l'invio si invalida `me`.
  - Richiesta in sospeso: se `user.pending_email_change` non è null si mostra l'avviso con "Annulla la richiesta", mutation su `DELETE /me/email-change` che invalida `me`.
- **Ordini:** elenco da `GET /orders`, dettaglio da `GET /orders/{id}`. "Annulla l'ordine" compare solo con `can_cancel`; un 502 con `code` `refund_failed` lascia aperto il dialog con l'`Alert` del messaggio ricevuto e l'ordine invariato. La timeline "Stato dell'ordine" usa `status_history`: i passi compiuti (Pagato, Spedito, Consegnato) prendono data e descrizione dalla riga con lo stesso `status`; i passi futuri mostrano il suggerimento; un ordine annullato mostra la riga Annullato con la sua data e nessun passo futuro. Nessun autore: la risposta del cliente non lo contiene. Il pagamento mostra solo "Carta che termina con {card_last_four}": intestatario e scadenza non esistono nella risposta. "Annulla l'ordine" apre il dialog di conferma e chiama `POST /orders/{id}/cancel`, poi invalida `orders`.
- **Sicurezza:**
  - panoramica da `GET /me/security` (`queryKeys.security.overview`), con "Ultima modifica" da `password_changed_at`
  - cambio password nel form della card, con il secondo fattore in linea quando `two_factor_enabled`; per la passkey le opzioni arrivano da `POST /me/password/passkey-options`; dopo il cambio si invalidano `me` e `security`
  - attivazione TOTP con lo stato del passo in `useState` del componente; i codici di recupero vivono solo nello stato locale del passo 3 e si perdono chiudendolo
  - passkey da `GET /me/passkeys`, con aggiunta, rinomina e rimozione; le rotte `identity.confirmed` passano la descrizione dell'operazione nella configurazione della richiesta
  - attività recente con `useInfiniteQuery` su `GET /me/security-events` (`queryKeys.security.events`), `getNextPageParam` dai `meta` della paginazione e pulsante "Mostra altre attività" finché `hasNextPage`

## Area admin (`features/admin`)

Dal mockup `04-admin.pdf`; regole e forme delle risposte nella skill `ecommerce-domain`, sezione "Area admin".

### Layout e navigazione

- `AdminLayout` fuori da `StoreLayout`, dentro `AdminRoute`. Le voci Dashboard, Prodotti, Categorie e Ordini sono definite una sola volta in `features/admin/navigation.js` con etichetta, icona e percorso; la voce attiva si ricava dalla rotta corrente (Prodotti resta attiva anche su nuovo e modifica prodotto). In fondo "Vai al negozio" (`paths.home`) ed "Esci" (`logout()` e poi la home).
- Il titolo della pagina nell'`AppBar` arriva dalla rotta (`handle.title` letto con `useMatches()`): Dashboard, Prodotti, Nuovo prodotto, Modifica prodotto, Categorie, Ordini, Dettaglio ordine.
- Il Drawer mobile si chiude dopo la navigazione.

### Elenchi (prodotti e ordini)

- Filtri, pagina e righe per pagina nella query string (`useSearchParams`), come nel catalogo: i valori predefiniti non compaiono, cambiare un filtro riporta a pagina 1.
- La ricerca aggiorna la query string dopo 300 ms di pausa con `hooks/useDebouncedValue.js`; la X del campo la svuota subito.
- Prodotti: `search`, `category` (slug, opzioni da `useCategories()`), `status` (`active`, `inactive`), `page`, `per_page`.
- Ordini: `status`, `search`, `failed_payment=1` (`Switch` "Solo pagamenti non riusciti"), `paid_month` (`AAAA-MM`, senza un controllo proprio nella barra: arriva dai link della dashboard e compare come filtro attivo rimovibile sopra la tabella; rimuoverlo toglie il parametro e riporta a pagina 1), `page`, `per_page`; «N ordini» sopra la tabella da `meta.total`. "Azzera filtri" toglie anche `paid_month`.
- `TablePagination` è a base 0, l'API a base 1: la conversione avviene solo nel componente.
- Stati vuoti con il testo che cita la ricerca e "Azzera filtri", che riporta a `paths.adminProducts` o `paths.adminOrders` senza parametri.

### Dashboard

- Una query su `GET /admin/dashboard` (`queryKeys.admin.dashboard`), più `useAdminSettings()` per «N pezzi o meno».
- Collegamenti: "Ordini da spedire" → `paths.adminOrders` con `?status=paid`; ogni stato di "Ordini per stato" → ordini con `status` e `paid_month` uguale a `period.month` della risposta; "Vedi tutti" → `paths.adminOrders`; ogni ordine → `paths.adminOrder(id)`; "Modifica" di una scorta bassa → `paths.adminProductEdit(id)`.
- Mese e anno delle didascalie si formattano da `period.month` con `formatMonthYear` («Settembre 2026», con la maiuscola iniziale aggiunta dalla utility perché `Intl` restituisce «settembre 2026»), non dalla data del browser.

### Form prodotto (nuovo e modifica)

- Un solo componente di form per le due pagine, React Hook Form con lo schema Zod di `features/admin/products/schemas.js`, che replica le regole di `StoreProductRequest`: SKU `^[A-Z0-9-]+$` («Usa solo lettere maiuscole, numeri e trattini»), descrizione breve obbligatoria e massimo 160 caratteri, stock intero ≥ 0, categoria obbligatoria («Scegli una categoria»).
- **Prezzo:** il campo contiene testo in euro con la virgola (es. «249,00»). Lo schema accetta `^\d{1,6}(,\d{1,2})?$`, con il messaggio «Inserisci un prezzo, ad esempio 249,00», e un valore di almeno 0,01. `utils/price.js` esporta `parseEuroToCents('249,5')` → 24950, che lavora sulle stringhe senza float, e `formatCentsForInput(34900)` → «349,00» senza simbolo né separatore delle migliaia. L'API riceve sempre centesimi.
- **Scheda tecnica:** `useFieldArray` con `append`, `move` (su e giù, disabilitati in prima e ultima riga) e `remove`.
- **Errori 422:** mappati sui campi con `setError` (i campi annidati come `specifications.0.value` compresi); l'`Alert` in cima «Il prodotto non è stato salvato» conta i campi con errore.
- **Salvataggio:**
  - nuovo: `POST /admin/products`, poi `reset()` con i valori salvati, Snackbar «Prodotto creato» e navigazione a `paths.adminProductEdit(id)` con `replace`, così il blocker non scatta e le immagini diventano disponibili
  - modifica: `PATCH /admin/products/{id}` con i campi e `images` (id e `alt` nell'ordine del form), poi `reset()` con la risposta e Snackbar «Modifiche salvate»
  - entrambi invalidano `admin.products`, `admin.dashboard`, `admin.categories` (cambiano `products_count` e `can_delete`) e `products`; lo stesso vale per l'eliminazione di un prodotto
- **Annulla:** nella modifica riporta il form ai valori salvati (`reset()`) ed è disabilitato senza modifiche; nel nuovo prodotto torna a `paths.adminProducts`.
- **Modifiche non salvate:** `useBlocker` di React Router, attivo quando `isDirty` e non è in corso un salvataggio, apre il Dialog di conferma; "Esci senza salvare" chiama `blocker.proceed()`, "Resta sulla pagina" `blocker.reset()`. `useBeforeUnload` copre chiusura e ricarica della scheda. Vale per le pagine di form, non per i dialog delle categorie.
- **Immagini** (solo in modifica; nel nuovo prodotto la sezione mostra «Potrai aggiungere le immagini dopo il primo salvataggio»):
  - area di caricamento con gli eventi nativi `onDragOver`, `onDragLeave` e `onDrop` e un `<input type="file" multiple accept="image/jpeg,image/png,image/webp">` nascosto aperto da "Scegli i file"; nessuna libreria di drag and drop
  - prima dell'invio si scartano i file non validi con un messaggio per file: «Formato non supportato: usa JPG, PNG o WebP», «Il file supera 5 MB»
  - caricamento (`POST .../images`, `FormData`) ed eliminazione (`DELETE .../images/{id}`, dopo il dialog di conferma) sono immediati; la risposta si unisce al field array `images` del form senza toccare gli `alt` e l'ordine non ancora salvati delle altre immagini, e senza rendere il form "modificato"
  - ordine (← e →, disabilitati agli estremi) e testi alternativi fanno parte del form e si salvano con "Salva"
- L'helper dello stock «Con N pezzi o meno il prodotto entra tra le scorte basse.» legge N da `useAdminSettings()`.

### Categorie

- Elenco da `GET /admin/categories` (`queryKeys.admin.categories`). Nuova e modifica in un `Dialog` con React Hook Form; lo slug non è un campo del form e si mostra in sola lettura in modifica.
- Il pulsante elimina è disabilitato quando `can_delete` è false; un 409 dell'API mostra comunque il messaggio ricevuto.
- Creazione, modifica ed eliminazione invalidano `admin.categories` e `categories`, così menu e footer del negozio si aggiornano.

### Ordini

- Dettaglio da `GET /admin/orders/{id}` (`queryKeys.admin.orders.detail(id)`).
- Le azioni si ricavano solo da `allowed_transitions`: `shipped` → "Segna come spedito", `delivered` → "Segna come consegnato", `cancelled` → "Annulla l'ordine" con il dialog di conferma; lista vuota → «Nessuna azione disponibile per questo ordine.».
- Mutation su `PATCH /admin/orders/{id}/status` che invalida `admin.orders` e `admin.dashboard`, e dopo un annullamento anche `admin.products` e `products` (scorte ripristinate). Un 502 con `code` `refund_failed` lascia il dialog aperto con l'`Alert` del messaggio ricevuto; l'ordine resta invariato.
- Pagamento rifiutato (`failed_payment`): `Chip` "Annullato" dallo stato; `Alert` "Pagamento non riuscito" e riga "Motivo" della card Pagamento con il `reason` della riga di cronologia `cancelled`, più `payment.card_last_four`.

## Dati dal server (TanStack Query)

- Tutto lo stato che arriva dal server passa da TanStack Query. Vietato il pattern `useEffect` + `useState` per caricare dati.
- `features/x/api/*.js`: funzioni pure che usano `httpClient` e restituiscono `response.data`.
- `features/x/hooks/*.js`: `useQuery` e `useMutation` che usano quelle funzioni.
- Query key centralizzate in `api/queryKeys.js` come factory.
- Dopo una mutation si invalidano le query toccate (aggiunta al carrello → `cart`; annullamento di un ordine dal cliente → `orders`, `products` e le query admin di ordini, prodotti e dashboard; cambio di stato dall'admin → `admin.orders` e `admin.dashboard`; prodotti e categorie dall'admin → le query admin e quelle pubbliche di `products` e `categories`; modifiche di sicurezza → `me` e `security`).
- `QueryClient` con `staleTime` ragionevole e nessun retry per gli errori 4xx.

```js
export const queryKeys = {
  me: ['me'],
  cart: ['cart'],
  categories: ['categories'],
  products: {
    all: ['products'],
    list: (filters) => ['products', 'list', filters],
    detail: (slug) => ['products', 'detail', slug],
    related: (slug) => ['products', 'related', slug],
  },
  orders: {
    all: ['orders'],
    detail: (orderId) => ['orders', 'detail', orderId],
  },
  security: {
    overview: ['security', 'overview'],
    passkeys: ['security', 'passkeys'],
    events: ['security', 'events'],
  },
  settings: {
    shop: ['settings', 'shop'],
    admin: ['settings', 'admin'],
  },
  admin: {
    dashboard: ['admin', 'dashboard'],
    categories: ['admin', 'categories'],
    products: {
      all: ['admin', 'products'],
      list: (filters) => ['admin', 'products', 'list', filters],
      detail: (productId) => ['admin', 'products', 'detail', productId],
    },
    orders: {
      all: ['admin', 'orders'],
      list: (filters) => ['admin', 'orders', 'list', filters],
      detail: (orderId) => ['admin', 'orders', 'detail', orderId],
    },
  },
};
```

```js
export function useProducts(filters) {
  return useQuery({
    queryKey: queryKeys.products.list(filters),
    queryFn: () => fetchProducts(filters),
    placeholderData: keepPreviousData,
  });
}
```

## httpClient

- `axios.create` con `baseURL: import.meta.env.VITE_API_URL`, `withCredentials: true`, `withXSRFToken: true` e header `Accept: application/json`.
- Funzione `ensureCsrfCookie()` che chiama `GET /sanctum/csrf-cookie` prima di login, registrazione e recupero password.
- Interceptor di risposta:
  - 401: rimuove l'utente dalla cache (`queryKeys.me`); il redirect lo gestiscono le rotte protette.
  - 419: richiede di nuovo il cookie CSRF e ripete la richiesta una sola volta.
  - 423: il backend chiede di confermare l'identità. L'interceptor apre il `ConfirmIdentityDialog` tramite `IdentityConfirmationProvider`, attende l'esito e ripete la richiesta originale; se l'utente annulla, la richiesta fallisce con un errore gestito. La descrizione mostrata nel dialog arriva da una proprietà personalizzata della configurazione della richiesta (`identityPrompt`), con un testo generico come ripiego.
- `IdentityConfirmationProvider` espone anche `confirmIdentity({ prompt })`, che apre il dialog senza una richiesta fallita e restituisce una Promise risolta alla conferma e rifiutata all'annullamento.
- Errori normalizzati da `utils/normalizeApiError.js` nella forma `{ status, message, fieldErrors, retryAfter, code }`:
  - `code` è il campo `code` della risposta del server, `null` se manca; serve a distinguere errori con lo stesso status (es. `two_factor_challenge_expired` rispetto a un codice sbagliato, entrambi 422)
  - il messaggio del server si usa solo per 422, 409, 402 e per il 502 con `code` `refund_failed`; per gli altri status e per l'errore di rete si usano testi italiani locali
  - `retryAfter` è il numero di secondi letto dall'header `Retry-After` delle risposte 429, `null` se l'header manca o non è un numero
  - per il 429 il messaggio è «Troppi tentativi. Riprova tra N secondi», con N da `retryAfter`, oppure «Troppi tentativi. Riprova tra poco» se manca

## Autenticazione

- `AuthProvider` basato sulla query `me` (`GET /api/v1/me`); un 401 significa ospite, non errore.
- Hook `useAuth()` che restituisce `{ user, isAuthenticated, isAdmin, isEmailVerified, isLoading, login, logout, register }`.
- Il login può rispondere con la richiesta del secondo fattore: in quel caso si naviga a `/login/two-factor` (dettagli nella skill `account-security`).
- Durante il caricamento iniziale dell'utente le rotte protette mostrano un indicatore, non un redirect.
- Dopo login e logout si invalidano `me` e `cart`.

## Form

- React Hook Form con `zodResolver`; schema Zod in `features/x/schemas.js`, con messaggi in italiano. Uno schema usato da più feature sta in `utils/` (es. `utils/passwordRules.js`).
- Le regole della nuova password stanno in `utils/passwordRules.js`, perché le usano due feature (`auth` per registrazione e reset, `security` per il cambio password). Il file esporta l'elenco dei requisiti (etichetta e controllo) e lo schema Zod `newPasswordSchema` costruito da quell'elenco. Rispecchiano `Password::defaults()` del backend: almeno 10 caratteri, lettere maiuscole e minuscole, almeno un numero. Il controllo sulle password compromesse resta solo nel backend e arriva come errore 422 sul campo.
- L'elenco dei requisiti sotto il campo è `components/form/PasswordRequirements.jsx` e legge gli stessi requisiti, così elenco e validazione non possono divergere.
- Campi MUI collegati con `Controller`; `error` e `helperText` presi dallo stato del form.
- Gli errori 422 del server vengono mappati sui campi con `setError`.
- Il pulsante di invio è disabilitato e mostra un indicatore durante l'invio.

## Stato

- Dati del server: TanStack Query.
- Stato di interfaccia locale: `useState`.
- Stato del form prodotto e delle immagini non ancora salvate: React Hook Form della pagina.
- Stato globale: solo autenticazione, notifiche (`NotificationProvider` con Snackbar) e conferma dell'identità (`IdentityConfirmationProvider`).
- Codici di recupero, chiave TOTP e QR code: solo nello stato locale del componente che li mostra, mai in cache di TanStack Query oltre la risposta della mutation, `localStorage`, URL o log.
- Stato del checkout: locale alla pagina, mai globale né persistito (sezione "Checkout").
- Niente Redux né Zustand.

## Stati di una vista

Ogni vista con dati remoti gestisce esplicitamente quattro stati:

1. Caricamento: Skeleton con la forma del contenuto.
2. Errore: Alert con pulsante "Riprova" che chiama `refetch`.
3. Vuoto: messaggio chiaro con un'azione (es. "Nessun prodotto corrisponde ai filtri" → "Azzera filtri").
4. Contenuto.

## Presentazione dei valori

- Prezzi con `formatPrice(cents)` in `utils/format.js`: divide per 100 e usa `Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', useGrouping: 'always' })`. Senza `useGrouping: 'always'` gli importi da 1.000 a 9.999 escono senza punto delle migliaia.
- Date con `formatDate` e `formatDateTime`, basati su `Intl.DateTimeFormat('it-IT')` (es. «29 settembre 2026», «29 settembre 2026, 09:14»). Tabelle e cronologia dell'admin usano `formatDateTimeShort` («29 set 2026, 16:52») e, su mobile, `formatDayTimeShort` («29 set, 16:52»); `formatMonthYear` produce «Settembre 2026». Tutte in `utils/format.js`, con i test.
- Etichette di eventi di sicurezza e dispositivi (`label`, `device`): quelle ricevute dall'API, mai calcolate dallo user agent nel frontend.
- Ogni valore numerico ha etichetta e unità, con lo spazio prima dell'unità (es. "Disponibilità: 12 pezzi", "Totale: 49,90 €", "65 W").
- Etichette di stati d'ordine e disponibilità: quelle ricevute dall'API, mai riscritte nel frontend.

## Checklist per una nuova feature

1. Funzioni API della feature
2. Query key
3. Hook
4. Componenti e pagina
5. Rotta nel router e percorso in `paths.js`
6. Stati di caricamento, errore e vuoto
7. Test secondo la skill `testing`
8. `make test-frontend` e `make lint` verdi
9. Elenco per l'utente di pagine da aprire e azioni da provare nel browser
