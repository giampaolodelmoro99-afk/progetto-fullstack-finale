# Backlog

Suggerimenti emersi dalla revisione della Fase 0 e rimandati alla fase in cui trovano posto naturale.

| Argomento | Fase | Cosa fare |
|---|---|---|
| Rate limiting | 3a | Limiter nominati in `AppServiceProvider` (`login`, `two-factor`, `password-reset`, `verification`, `email-change`, `identity-confirmation`, come nella skill `account-security`) e un limiter generale per il gruppo `api`, con risposta 429 e messaggio in italiano. Test sul superamento dei limiti. |
| Token Bearer di Sanctum disattivati | 3a | La tabella `personal_access_tokens` non esiste (autenticazione solo a cookie), ma la guard di Sanctum prova a leggere un token quando arriva un header `Authorization: Bearer ...`: su una rotta `auth:sanctum` produrrebbe un 500 invece di un 401. In `AppServiceProvider::boot()` aggiungere `Sanctum::getAccessTokenFromRequestUsing(fn () => null);` e un test: richiesta con Bearer su una rotta protetta → 401. |
| Interceptor 401 e `AuthProvider` | 7 | Oggi l'interceptor di `src/api/httpClient.js` rimuove `me` solo dal `queryClient` condiviso, mentre i test usano un client separato. Con l'`AuthProvider` va registrato il client in uso, per esempio con una funzione chiamata da `AppProviders`, così il 401 svuota la cache corretta anche nei test. |
| `errorElement` e `NotFoundPage` | 7 | Aggiungere `ErrorPage` come `errorElement` e la rotta `*` con `NotFoundPage` in `src/app/router/router.jsx`, insieme ai layout. Test del redirect e della pagina 404. |
| Regole ESLint del design system | 7 | Con `no-restricted-syntax` e `no-restricted-imports` in `frontend/eslint.config.js`: vietare l'attributo JSX `style` e l'import di file `.css`, così il lint fa rispettare le regole della skill `mui-design-system`. |
| Aggiornamento delle dipendenze dopo `git pull` | ora + 10 | Già documentato nel README (sezione "Dopo un `git pull`"). In Fase 10, eventualmente: gli entrypoint salvano un hash di `composer.lock` e `package-lock.json` in `vendor/` e `node_modules/` e reinstallano quando cambia. |
| Controllo del database nell'health check | 10 | `GET /api/v1/health` verifica anche la connessione al database, tramite un'Action o un servizio (i controller non usano `DB`), e risponde 503 se non è raggiungibile, così l'healthcheck di compose lo rileva. Test per entrambi i casi. |

## Fase 7 — integrazione del design system

Fonti: `design/design-system/` (README, `theme.js`, `voice-and-content.md`) e `design/mockups/01-negozio.pdf`.

| Punto | Cosa fare |
|---|---|
| Tema | `design/design-system/theme.js` è un riferimento: va diviso in `frontend/src/theme/` (`palette.js`, `typography.js`, `components.js`, `index.js`) secondo la skill `mui-design-system`, sostituendo il tema provvisorio della Fase 0. |
| `formatPrice` | In `src/utils/format.js`: riceve centesimi, divide per 100 e usa `Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', useGrouping: 'always' })`. Test con 349,00 €, 1.249,00 € e 0,00 €. |
| Stati d'ordine | Nel frontend solo la mappa dei colori (`pending` warning, `paid` info, `shipped` secondary, `delivered` success, `cancelled` error); l'etichetta arriva dall'API. La skill `mui-design-system` è già aggiornata: resta da applicarlo al codice. |
| Ombra delle card | L'ombra in hover di `MuiCard` si applica solo alle card cliccabili, cioè con `CardActionArea`, non a tutte le card come in `theme.js`. |
| Font | Bodoni Moda e Manrope self-hosted con i pacchetti `@fontsource-variable` (da proporre come dipendenze all'inizio della fase), niente Google Fonts da CDN. |
| Voce e contenuti | Le regole di `design/design-system/voice-and-content.md` (tu, sentence case, niente punti esclamativi né urgenza artificiale, formati di prezzi e unità, parole fisse per stati e disponibilità) sono già riassunte nella skill `mui-design-system`: in Fase 7 vanno rispettate in tutti i testi dell'interfaccia e verificate in revisione. |
