---
name: account-security
description: Autenticazione e sicurezza dell'account — registrazione con verifica email, login con secondo fattore opzionale, doppia autenticazione TOTP con codici di recupero, passkey WebAuthn, conferma dell'identità per le operazioni sensibili, recupero e cambio password, cambio email, sessioni, notifiche e registro degli eventi di sicurezza. Usala per qualsiasi lavoro su login, account, profilo e sicurezza, sia nel backend sia nel frontend.
---

# Sicurezza dell'account

## Principi

1. Il secondo fattore è **opzionale**: è l'utente a decidere se attivarlo, dalla pagina Sicurezza. Vale per tutti i ruoli.
2. Esistono due tipi di secondo fattore, attivabili anche insieme: **TOTP** (app di autenticazione) e **passkey** (WebAuthn).
3. Un utente "ha il secondo fattore" se ha il TOTP confermato oppure almeno una passkey registrata: `User::hasSecondFactor()`.
4. Una volta attivo, il secondo fattore è richiesto al login, al recupero password e in ogni operazione sensibile.
5. Ogni operazione sensibile invia una notifica email e scrive un evento di sicurezza.
6. I segreti non si salvano mai in chiaro e non compaiono mai nelle risposte, tranne nel momento in cui l'utente deve vederli una sola volta.
7. Le risposte non rivelano se un indirizzo email è registrato.

## Librerie

- Verifica email e reset password: strumenti nativi di Laravel (`MustVerifyEmail`, URL firmati, password broker).
- TOTP: libreria candidata `pragmarx/google2fa`, con QR code SVG generato dal backend (es. `bacon/bacon-qr-code`).
- Passkey: libreria WebAuthn per Laravel, candidate `laragear/webauthn` e `spatie/laravel-passkeys`. Verifica la compatibilità con la versione di Laravel installata e proponi la scelta prima di installarla. La crittografia WebAuthn non si scrive a mano.
- Frontend passkey: `@simplewebauthn/browser` (`startRegistration`, `startAuthentication`, `browserSupportsWebAuthn`).
- Le passkey funzionano su `http://localhost` perché i browser lo considerano un contesto sicuro. Relying party: id `localhost`, origine ammessa `http://localhost:5173`.
- Niente Fortify: rotte e struttura seguono le convenzioni del progetto (`/api/v1`, Action, Resource).

## Dati

| Tabella o colonna | Contenuto | Protezione |
|---|---|---|
| users.email_verified_at | data di verifica | — |
| users.two_factor_secret | segreto TOTP | cast `encrypted`, `$hidden` |
| users.two_factor_recovery_codes | elenco degli hash dei codici di recupero | cast `encrypted:array`, `$hidden` |
| users.two_factor_confirmed_at | data di attivazione, null se non attivo | — |
| users.two_factor_last_used_step | ultimo intervallo TOTP usato | impedisce il riutilizzo dello stesso codice |
| passkeys (tabella della libreria) | credenziali WebAuthn, nome, ultimo utilizzo | chiave pubblica soltanto |
| password_reset_tokens | token di reset di Laravel | hash, scadenza 60 minuti |
| email_change_requests | user_id, new_email, token_hash, cancel_token_hash, expires_at, confirmed_at, cancelled_at | hash, scadenza 60 minuti, monouso |
| security_events | user_id, type, ip_address, user_agent, metadata (JSON), created_at | nessun segreto nei metadata |
| sessions | sessioni del driver `database` | usate per chiudere le altre sessioni |

Enum `SecurityEventType`: `login`, `login_failed`, `two_factor_challenge_failed`, `logout`, `email_verified`, `password_changed`, `password_reset`, `email_change_requested`, `email_changed`, `email_change_cancelled`, `two_factor_enabled`, `two_factor_disabled`, `recovery_codes_regenerated`, `recovery_code_used`, `passkey_added`, `passkey_removed`, `other_sessions_closed`. Ogni caso ha `label()` in italiano.

## Configurazione

`config/security.php`:

- `confirmation_timeout`: 600 secondi
- `login_challenge_ttl`: 300 secondi
- `email_change_ttl`: 60 minuti
- `recovery_codes_count`: 8

## Architettura del secondo fattore

- Interfaccia `App\Contracts\Security\SecondFactorVerifier` con `verify(User $user, array $payload): bool`.
- Implementazioni: `TotpVerifier`, `RecoveryCodeVerifier`, `PasskeyVerifier`.
- `VerifySecondFactorAction` sceglie l'implementazione in base al campo `method` (`totp`, `recovery_code`, `passkey`) e lancia `InvalidSecondFactorException` (422) se la verifica fallisce.
- Il TOTP accetta una finestra di un intervallo e rifiuta intervalli già usati.
- Un codice di recupero usato viene rimosso; se ne restano due o meno, la risposta lo segnala e il frontend invita a rigenerarli.
- Nei test `PasskeyVerifier` si sostituisce con un'implementazione finta tramite il container.

## Conferma dell'identità (step-up)

- Endpoint `POST /me/confirm-identity` con `password` e, se l'utente ha il secondo fattore, `method` più `code` oppure la risposta della passkey.
- Se riuscita, la sessione salva `identity_confirmed_at`, valida per `confirmation_timeout`.
- Middleware `EnsureIdentityConfirmed`, alias `identity.confirmed`: se la conferma manca o è scaduta risponde **423** con `{ "message": "...", "requires": ["password", "second_factor"] }`, dove `second_factor` compare solo se l'utente ce l'ha.
- Il frontend intercetta il 423, apre `ConfirmIdentityDialog` e ripete la richiesta originale.
- La conferma si azzera al logout e dopo ogni cambio password.

## Flussi

### Registrazione e verifica email

1. `POST /register`: crea un `customer`, invia l'email di verifica, avvia la sessione.
2. Il link nell'email punta al frontend (`/verify-email?...`) e contiene i parametri firmati dell'API: si configura con `VerifyEmail::createUrlUsing()`.
3. La pagina del frontend chiama `POST /email/verify/{id}/{hash}` inoltrando `expires` e `signature`.
4. `POST /email/verification-notification` reinvia l'email (limitato).
5. Senza email verificata l'utente naviga e usa il carrello, ma il checkout è bloccato dal middleware `verified`.

### Login

1. `POST /login` con email e password.
2. Senza secondo fattore: rigenera la sessione e restituisce l'utente.
3. Con secondo fattore: **non** autentica. Salva in sessione `login.pending_user_id` con scadenza `login_challenge_ttl` e risponde `{ "two_factor_required": true, "methods": [...] }`.
4. `POST /login/two-factor` con `method` e `code`, oppure con la risposta della passkey ottenuta da `POST /login/two-factor/passkey-options`. Se riesce, autentica e rigenera la sessione.
5. Accesso senza password con passkey: `POST /passkeys/login/options`, poi `POST /passkeys/login`.
6. Credenziali errate: messaggio generico "Credenziali non valide", mai "email non trovata".

### Recupero password

1. `POST /forgot-password` con l'email: risponde **sempre** lo stesso messaggio generico, che l'indirizzo esista o no.
2. Il link nell'email punta al frontend: `/reset-password?token=...&email=...`.
3. `POST /reset-password/check` con token ed email: se il token è valido risponde `{ "requires_second_factor": bool, "methods": [...] }`.
4. `POST /reset-password` con token, email, nuova password, conferma e, se richiesto, il secondo fattore. Per la passkey le opzioni si ottengono da `POST /reset-password/passkey-options` con token ed email.
5. Dopo il reset: tutte le sessioni dell'utente chiuse, notifica email, evento `password_reset`, nessun login automatico.

### Cambio password

- `PUT /me/password` con `current_password`, `password` e `password_confirmation`.
- La password attuale è sempre richiesta nella richiesta stessa.
- Se l'utente ha il secondo fattore, la rotta richiede anche la conferma dell'identità.
- Dopo il cambio: le altre sessioni vengono chiuse, la sessione corrente è rigenerata, notifica email, evento `password_changed`.

### Cambio email

1. `POST /me/email` con il nuovo indirizzo, protetto da `identity.confirmed` (password, più secondo fattore se attivo). Il nuovo indirizzo deve essere diverso dall'attuale e non già registrato.
2. Crea una `email_change_requests` (annulla eventuali richieste precedenti in sospeso) e invia:
   - al **nuovo** indirizzo il link di conferma: `/confirm-email-change?token=...`
   - al **vecchio** indirizzo un avviso con il link di annullamento: `/cancel-email-change?token=...`
3. `POST /email-change/confirm` con il token: ricontrolla l'unicità, aggiorna l'email e `email_verified_at`, notifica il vecchio indirizzo, evento `email_changed`.
4. `POST /email-change/cancel` con il token di annullamento: annulla la richiesta, evento `email_change_cancelled`, e il messaggio consiglia di cambiare la password.
5. Finché la richiesta non è confermata, l'email dell'account resta quella vecchia.

### Doppia autenticazione TOTP

1. `POST /me/two-factor` (`identity.confirmed`): genera un segreto non ancora confermato e restituisce QR code SVG e chiave per l'inserimento manuale.
2. `POST /me/two-factor/confirm` con un codice valido: attiva il TOTP e restituisce i codici di recupero in chiaro, **una sola volta**.
3. `POST /me/two-factor/recovery-codes` (`identity.confirmed`): rigenera i codici, invalida i precedenti, li mostra una sola volta.
4. `DELETE /me/two-factor` (`identity.confirmed`): disattiva il TOTP, chiude le altre sessioni, notifica, evento.
5. Formato dei codici di recupero: `XXXX-XXXX`, salvati come hash e monouso.

### Passkey

1. `GET /me/passkeys`: elenco con nome, data di creazione e ultimo utilizzo.
2. `POST /me/passkeys/options` (`identity.confirmed`), poi `POST /me/passkeys` con la risposta del browser e un nome scelto dall'utente.
3. `PATCH /me/passkeys/{passkey}`: rinomina.
4. `DELETE /me/passkeys/{passkey}` (`identity.confirmed`): rimozione, notifica, evento.
5. Alla prima passkey registrata, se l'utente non ha codici di recupero, se ne generano e si mostrano una sola volta. L'interfaccia consiglia di registrare almeno due passkey.
6. Se il browser non supporta WebAuthn, le opzioni passkey non vengono mostrate.

### Sessioni ed eventi

- `GET /me/security`: panoramica (TOTP attivo, numero di passkey, codici di recupero rimasti, email verificata).
- `GET /me/security-events`: ultimi eventi paginati.
- Extra opzionale: `GET /me/sessions`, `DELETE /me/sessions/{id}`, `DELETE /me/sessions` per le altre sessioni.

## Riepilogo delle protezioni

| Operazione | Senza secondo fattore | Con secondo fattore |
|---|---|---|
| Login | password | password + secondo fattore, oppure solo passkey |
| Recupero password | link via email | link via email + secondo fattore o codice di recupero |
| Cambio password | password attuale | password attuale + secondo fattore |
| Cambio email | password + verifica del nuovo indirizzo + avviso al vecchio | lo stesso + secondo fattore |
| Disattivare TOTP, rimuovere passkey, rigenerare codici | — | password + secondo fattore |

## Notifiche email (in italiano)

Verifica email, reset password, password cambiata, conferma cambio email (nuovo indirizzo), avviso cambio email (vecchio indirizzo), email cambiata, doppia autenticazione attivata o disattivata, codici di recupero rigenerati, codice di recupero usato, passkey aggiunta o rimossa. Ogni avviso indica data, ora e dispositivo, e invita a cambiare la password se l'operazione non è stata fatta dall'utente.

## Password

- `Password::defaults()` in `AppServiceProvider`: minimo 10 caratteri, lettere maiuscole e minuscole, numeri, `uncompromised()`.
- `uncompromised()` è disattivato nell'ambiente di test perché fa una chiamata esterna.

## Rate limiting

| Limiter | Limite |
|---|---|
| `login` | 5 al minuto per email + IP |
| `two-factor` | 5 al minuto per sessione |
| `password-reset` | 3 ogni 10 minuti per email + IP |
| `verification` | 6 al minuto per utente |
| `email-change` | 3 all'ora per utente |
| `identity-confirmation` | 5 al minuto per utente |

Oltre il limite si risponde 429 con messaggio in italiano; il frontend mostra dopo quanti secondi riprovare.

## Frontend

- Pagine in `features/auth`: login, verifica secondo fattore al login, registrazione, verifica email, password dimenticata, reset password, conferma e annullamento del cambio email.
- Pagine in `features/security`: sicurezza dell'account con TOTP, codici di recupero, passkey, cambio password, eventi recenti. Il cambio email sta nel profilo in `features/account`.
- `IdentityConfirmationProvider` gestisce il 423 intercettato da `httpClient` e mostra `ConfirmIdentityDialog`.
- Banner di email non verificata in `StoreLayout` con reinvio.
- Campi del codice con `autoComplete="one-time-code"`; campi password con `current-password` o `new-password`.
- I codici di recupero si mostrano una volta sola, con copia e download in un file `.txt`.

## Test obbligatori

Oltre ai casi standard della skill `testing`:

- login con e senza secondo fattore, challenge scaduta, codice errato, codice riutilizzato
- ogni rotta `identity.confirmed` risponde 423 senza conferma e 200 dopo la conferma
- codice di recupero accettato una sola volta
- forgot password con email inesistente: stessa risposta di quella esistente
- reset con secondo fattore richiesto e mancante → 422
- cambio email: il vecchio indirizzo resta fino alla conferma, token scaduto, token riutilizzato, annullamento
- chiusura delle altre sessioni dopo cambio e reset password
- notifiche inviate (`Notification::fake()`) ed eventi di sicurezza registrati
- segreti mai presenti nelle risposte JSON
- rate limiting sulle rotte di autenticazione
