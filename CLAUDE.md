# E-commerce — Progetto d'esame ITS Full Stack Developer

Monorepo di un e-commerce completo.

- `backend/` — Laravel, API REST headless (nessuna vista Blade), autenticazione Sanctum SPA a cookie
- `frontend/` — React SPA in JavaScript con Vite, React Router, TanStack Query e MUI
- `docker/` — Dockerfile, script di avvio e configurazioni dei servizi
- `design/` — export di Claude Design e riferimenti visivi
- `docs/` — schema ER, decisioni architetturali, note per la presentazione

## Ambiente di sviluppo

Tutto gira in Docker Compose. Sull'host non si installano PHP, Composer o Node.

**Requisito vincolante:** da un clone pulito della repository, il solo comando `docker compose up` deve portare a un'applicazione funzionante con i dati demo. Lo script `docker/php/entrypoint.sh` prepara il backend a ogni avvio, in modo idempotente:

1. crea `.env` da `.env.example` se manca
2. esegue `composer install` se manca `vendor`
3. genera `APP_KEY` se è vuota
4. esegue `migrate --force`
5. esegue i seeder solo se il database è vuoto
6. crea `storage:link` se manca
7. avvia il server

Il container `node` fa lo stesso per il frontend: crea `.env` se manca e installa le dipendenze se mancano.

| Servizio | Ruolo | Indirizzo |
|---|---|---|
| app | Laravel con `php artisan serve` | http://localhost:8000 |
| mysql | MySQL 8.4 (database `ecommerce` ed `ecommerce_testing`) | localhost:3307 dall'host, mysql:3306 nella rete Docker |
| node | Vite dev server | http://localhost:5173 |
| mailpit | cattura delle email (SMTP 1025) | http://localhost:8025 |
| phpmyadmin | ispezione del database | http://localhost:8081 |

Scelte vincolanti:
- Nessun Nginx, nessun Redis, nessun worker delle code.
- `QUEUE_CONNECTION=sync`: i listener e le notifiche implementano comunque `ShouldQueue`, così in produzione basta cambiare driver e avviare un worker.
- Cache e sessioni usano il driver `database`.
- Frontend e backend sono su porte diverse: CORS è configurato con credenziali e Sanctum riconosce `localhost:5173` come frontend stateful.

Comandi del `Makefile`: `up`, `down`, `build`, `shell`, `logs`, `migrate`, `fresh`, `test`, `test-backend`, `test-frontend`, `coverage-backend`, `coverage-frontend`, `lint`, `artisan cmd="..."`, `composer cmd="..."`, `npm cmd="..."`.

## Lingua e convenzioni

- Rispondi, spiega e scrivi i piani in italiano.
- Tutto il codice è in inglese: classi, variabili, funzioni, tabelle, colonne, chiavi JSON, nomi di rotte, descrizioni dei test.
- I testi mostrati all'utente finale (interfaccia, messaggi di validazione, email) sono in italiano.
- Messaggi di commit in inglese, formato Conventional Commits.

## Modo di lavorare

1. Analisi prima dell'azione: per ogni task leggi il codice coinvolto, proponi un piano e attendi conferma prima di modificare file.
2. Scrivi sempre file completi e funzionanti, mai frammenti con "resto invariato".
3. Non aggiungere dipendenze non concordate: se una serve, proponila motivandola.
4. Una migration già committata non si modifica: se ne crea una nuova.
5. I test si scrivono nello stesso task del codice che verificano. Un task non è concluso finché `make test` e `make lint` non sono verdi.
6. Nei test i valori attesi si scrivono a mano, non si ricavano dal codice o dalla configurazione che il test verifica.
7. Le regole di design, dominio e contenuti si prendono dalle fonti nella repository (skill, `design/`, `docs/`) o si chiedono all'utente, mai ricostruite. Se un mockup e una decisione approvata divergono, vale la decisione.
8. A fine task elenca le verifiche manuali che l'utente deve fare (Postman per le API, browser e Mailpit per il frontend) con passi precisi.
9. A fine fase lancia il subagent `code-reviewer` e riporta i risultati prima del commit.
10. Non fare commit né `git push` senza richiesta esplicita.

## Skill del progetto

- `laravel-api` — architettura e convenzioni di `backend/`, factory e seeder
- `react-architecture` — struttura, routing, dati e form in `frontend/`
- `mui-design-system` — tema, componenti visivi, layout e stile
- `ecommerce-domain` — regole di business, dati demo, mappa delle API del negozio
- `account-security` — registrazione, login, verifica email, recupero password, doppia autenticazione, passkey, modifica di dati sensibili
- `testing` — Pest nel backend, Vitest con React Testing Library e MSW nel frontend

Consulta le skill pertinenti prima di scrivere codice nel loro ambito.
