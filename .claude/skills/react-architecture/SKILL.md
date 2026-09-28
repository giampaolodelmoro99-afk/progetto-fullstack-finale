---
name: react-architecture
description: Architettura del frontend React del progetto — struttura per feature, React Router con layout e rotte protette, pagine statiche con ancore, TanStack Query per i dati del server, client axios con cookie Sanctum e CSRF, gestione degli errori 401, 419 e 423, autenticazione, form con React Hook Form e Zod, stati di una vista e convenzioni di codice. Usala per qualsiasi modifica in frontend/ che non sia puramente visiva.
---

# React — architettura del frontend

Riferimento visivo del negozio: `design/mockups/01-negozio.pdf`. Testi e formati: `design/design-system/voice-and-content.md`.

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
│   └── admin/
├── components/               # condivisi: feedback/, form/, data-display/, navigation/
├── layouts/                  # StoreLayout, AuthLayout, AccountLayout, AdminLayout
├── hooks/
├── theme/
├── test/                     # setup, utility e mock dei test
├── utils/                    # format.js, normalizeApiError.js
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
  - `StoreLayout`: `/`, `/products`, `/products/:slug`, `/cart`, `/checkout`, `/checkout/success/:orderId`, `/assistenza`, `/note-legali`
  - `AccountLayout` dentro `StoreLayout`: `/account/profile`, `/account/addresses`, `/account/orders`, `/account/orders/:orderId`, `/account/security`
  - `AuthLayout`: `/login`, `/login/two-factor`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, `/confirm-email-change`, `/cancel-email-change`
  - `AdminLayout`: `/admin`, `/admin/products`, `/admin/products/new`, `/admin/products/:productId/edit`, `/admin/categories`, `/admin/orders`, `/admin/orders/:orderId`
- `ProtectedRoute`: se l'utente non è autenticato reindirizza a `/login` salvando la pagina di provenienza in `state.from`, così dopo il login si torna lì.
- `GuestRoute`: login e registrazione reindirizzano a `/` se l'utente è già autenticato.
- `AdminRoute`: se l'utente non è admin reindirizza a `/`.
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
  accountOrders: '/account/orders',
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
- I testi riprendono i valori della configurazione del negozio (spedizione gratuita sopra 99,00 €, consegna in 24/48 ore, reso entro 30 giorni, garanzia di 2 anni).

## Navigazione e footer del negozio

Dal mockup `01-negozio.pdf`:

- Menu principale: "Novità" porta a `paths.newArrivals`; ogni categoria porta a `paths.category(slug)`.
- Footer, colonna **Categorie**: le cinque categorie, con `paths.category(slug)`.
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

- Le cinque categorie arrivano da `GET /categories`, con nome e descrizione.
- Le immagini sono statiche nel frontend, in `features/catalog/categoryImages.js`: una mappa slug → immagine importata, con un'immagine di ripiego per gli slug che non ne hanno una. Il backend non conosce le immagini delle categorie.

### Novità

- Anteprima di `GET /products?new=1&sort=newest` con quattro prodotti e il link "Vedi tutte le novità" verso `paths.newArrivals`.

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

## Dati dal server (TanStack Query)

- Tutto lo stato che arriva dal server passa da TanStack Query. Vietato il pattern `useEffect` + `useState` per caricare dati.
- `features/x/api/*.js`: funzioni pure che usano `httpClient` e restituiscono `response.data`.
- `features/x/hooks/*.js`: `useQuery` e `useMutation` che usano quelle funzioni.
- Query key centralizzate in `api/queryKeys.js` come factory.
- Dopo una mutation si invalidano le query toccate (aggiunta al carrello → `cart`; cambio di stato di un ordine → `orders`; modifiche di sicurezza → `me` e `security`).
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
  - 423: il backend chiede di confermare l'identità. L'interceptor apre il `ConfirmIdentityDialog` tramite `IdentityConfirmationProvider`, attende l'esito e ripete la richiesta originale; se l'utente annulla, la richiesta fallisce con un errore gestito.
- Errori normalizzati da `utils/normalizeApiError.js` nella forma `{ status, message, fieldErrors }`: il messaggio del server si usa solo per 422, 409 e 402; per gli altri status e per l'errore di rete si usano testi italiani locali.

## Autenticazione

- `AuthProvider` basato sulla query `me` (`GET /api/v1/me`); un 401 significa ospite, non errore.
- Hook `useAuth()` che restituisce `{ user, isAuthenticated, isAdmin, isEmailVerified, isLoading, login, logout, register }`.
- Il login può rispondere con la richiesta del secondo fattore: in quel caso si naviga a `/login/two-factor` (dettagli nella skill `account-security`).
- Durante il caricamento iniziale dell'utente le rotte protette mostrano un indicatore, non un redirect.
- Dopo login e logout si invalidano `me` e `cart`.

## Form

- React Hook Form con `zodResolver`; schema Zod in `features/x/schemas.js`, con messaggi in italiano.
- Campi MUI collegati con `Controller`; `error` e `helperText` presi dallo stato del form.
- Gli errori 422 del server vengono mappati sui campi con `setError`.
- Il pulsante di invio è disabilitato e mostra un indicatore durante l'invio.

## Stato

- Dati del server: TanStack Query.
- Stato di interfaccia locale: `useState`.
- Stato globale: solo autenticazione, notifiche (`NotificationProvider` con Snackbar) e conferma dell'identità (`IdentityConfirmationProvider`).
- Niente Redux né Zustand.

## Stati di una vista

Ogni vista con dati remoti gestisce esplicitamente quattro stati:

1. Caricamento: Skeleton con la forma del contenuto.
2. Errore: Alert con pulsante "Riprova" che chiama `refetch`.
3. Vuoto: messaggio chiaro con un'azione (es. "Nessun prodotto corrisponde ai filtri" → "Azzera filtri").
4. Contenuto.

## Presentazione dei valori

- Prezzi con `formatPrice(cents)` in `utils/format.js`: divide per 100 e usa `Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', useGrouping: 'always' })`. Senza `useGrouping: 'always'` gli importi da 1.000 a 9.999 escono senza punto delle migliaia.
- Date con `formatDate` e `formatDateTime`, basati su `Intl.DateTimeFormat('it-IT')`.
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
