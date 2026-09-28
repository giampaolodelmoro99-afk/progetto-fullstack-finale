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
