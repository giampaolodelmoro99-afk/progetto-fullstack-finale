# Backend — Laravel API

Consulta sempre le skill `laravel-api`, `ecommerce-domain` e `testing`; per autenticazione e dati dell'account anche `account-security`.

- API headless versionata: tutte le rotte sotto `/api/v1`, definite in `routes/api/v1/`.
- Autenticazione Sanctum SPA con cookie di sessione (`statefulApi()`), nessun token personale.
- Il frontend è su `http://localhost:5173`: CORS con `supports_credentials`, `SANCTUM_STATEFUL_DOMAINS=localhost:5173`, `SESSION_DOMAIN=localhost`, `FRONTEND_URL=http://localhost:5173`.
- `QUEUE_CONNECTION=sync`; cache e sessioni con driver `database`.
- Le email vanno a Mailpit (http://localhost:8025).
- Importi sempre in centesimi, come interi.
- `.env.example` contiene valori già funzionanti in Docker: copiarlo deve bastare.
- Test con Pest sul database MySQL `ecommerce_testing`: `make test-backend`.
- Artisan e Composer si eseguono solo nel container: `docker compose exec app ...` o i target del `Makefile`.
