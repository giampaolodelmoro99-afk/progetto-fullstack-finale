---
name: account-security
description: Autenticazione e sicurezza dell'account — registrazione con verifica email, login con doppia autenticazione opzionale, TOTP con codici di recupero, passkey WebAuthn per l'accesso senza password e come secondo fattore, conferma dell'identità per le operazioni sensibili, recupero e cambio password, cambio email, sessioni, notifiche, registro degli eventi di sicurezza ed eliminazione dell'account con anonimizzazione. Usala per qualsiasi lavoro su login, account, profilo e sicurezza, sia nel backend sia nel frontend.
---

# Sicurezza dell'account

Riferimento visivo: `design/mockups/03-account.pdf` (accesso, registrazione, pagine raggiunte dai link email, area account, sicurezza, dialog di conferma). Dove il mockup e questa skill divergono, vale questa skill.

## Principi

1. La doppia autenticazione è **opzionale**: è l'utente a decidere se attivarla, dalla pagina Sicurezza. Vale per tutti i ruoli.
2. La doppia autenticazione è attiva **solo** se il TOTP è confermato (`two_factor_confirmed_at` non null). `User::hasSecondFactor()` dipende solo dal TOTP.
3. Le **passkey** (WebAuthn) permettono sempre l'accesso senza password. Quando la doppia autenticazione è attiva valgono anche come secondo fattore al posto del codice: al login, al recupero password, al cambio password e nella conferma dell'identità. Senza doppia autenticazione non si chiedono mai come secondo fattore.
4. I **codici di recupero** esistono solo con il TOTP: si generano alla sua conferma e si cancellano alla sua disattivazione. Registrare una passkey non genera codici.
5. Con la doppia autenticazione attiva il secondo fattore (codice TOTP, codice di recupero o passkey) è richiesto al login con password, al recupero password, al cambio password e in ogni operazione sensibile.
6. Ogni operazione sensibile invia una notifica email e scrive un evento di sicurezza. Unica eccezione: l'eliminazione dell'account invia la notifica ma non scrive l'evento, perché il registro dell'utente viene cancellato (sezione "Eliminazione dell'account").
7. I segreti non si salvano mai in chiaro e non compaiono mai nelle risposte, tranne nel momento in cui l'utente deve vederli una sola volta.
8. Le risposte non rivelano se un indirizzo email è registrato.
9. L'eliminazione dell'account **anonimizza** l'utente invece di cancellarlo: gli ordini si conservano per gli obblighi fiscali, tutto il resto si cancella. Un account anonimizzato non può più accedere.

## Librerie

- Verifica email e reset password: strumenti nativi di Laravel (`MustVerifyEmail`, URL firmati, password broker).
- TOTP: libreria candidata `pragmarx/google2fa`, con QR code SVG generato dal backend (es. `bacon/bacon-qr-code`).
- Passkey: libreria WebAuthn per Laravel, candidate `laragear/webauthn` e `spatie/laravel-passkeys`. Verifica la compatibilità con la versione di Laravel installata e proponi la scelta prima di installarla. La crittografia WebAuthn non si scrive a mano.
- Frontend passkey: `@simplewebauthn/browser` (`startRegistration`, `startAuthentication`, `browserSupportsWebAuthn`).
- Le passkey funzionano su `http://localhost` perché i browser lo considerano un contesto sicuro. Relying party: id `localhost`, origine ammessa `http://localhost:5173`.
- User agent: parser interno senza dipendenze (sezione "Eventi di sicurezza").
- Niente Fortify: rotte e struttura seguono le convenzioni del progetto (`/api/v1`, Action, Resource).

## Dati

| Tabella o colonna | Contenuto | Protezione |
|---|---|---|
| users.email_verified_at | data di verifica | — |
| users.password_changed_at | data dell'ultima impostazione della password: registrazione, cambio, reset | — |
| users.two_factor_secret | segreto TOTP | cast `encrypted`, `$hidden` |
| users.two_factor_recovery_codes | elenco degli hash bcrypt (`Hash::make`) dei codici di recupero, null senza TOTP | cast `encrypted:array`, `$hidden` |
| users.two_factor_confirmed_at | data di attivazione della doppia autenticazione, null se non attiva | — |
| users.two_factor_last_used_step | ultimo intervallo TOTP usato | impedisce il riutilizzo dello stesso codice |
| users.anonymized_at | data dell'eliminazione dell'account, null per un account attivo | nuova migration nella Fase 3b; cast `datetime`, non fillable: la valorizza solo `DeleteAccountAction` |
| passkeys (tabella della libreria, forma provvisoria) | credenziali WebAuthn, nome, ultimo utilizzo | chiave pubblica soltanto |
| password_reset_tokens | token di reset di Laravel | hash, scadenza 60 minuti |
| email_change_requests | user_id, new_email, token_hash, cancel_token_hash, expires_at, confirmed_at, cancelled_at | hash SHA-256 in esadecimale (`char(64)`, indice unico), scadenza 60 minuti, monouso |
| security_events | user_id, type, ip_address, user_agent (grezzo), metadata (JSON), created_at | nessun segreto nei metadata |
| sessions | sessioni del driver `database` | usate per chiudere le altre sessioni |

- `role`, `password_changed_at` e le quattro colonne `two_factor_*` si aggiungono a `users` con un'unica nuova migration, perché quella di Laravel è già committata. `password_changed_at` è nullable nel database (colonna aggiunta a una tabella esistente), ma registrazione, cambio e reset la valorizzano sempre, e così la `UserFactory`.
- La tabella `passkeys` di `docs/database/er-diagram.md` è provvisoria, modellata su `spatie/laravel-passkeys` (nome e `last_used_at`); `laragear/webauthn` usa `webauthn_credentials` con `alias` e senza ultimo utilizzo. Lo schema si aggiorna alla scelta della libreria.
- I token del cambio email si salvano come SHA-256 in esadecimale con indice unico, non con l'hash lento del password broker. Sono casuali ad alta entropia, quindi un hash veloce senza salt basta; e la richiesta va cercata per hash anche da ospite, perché il link di annullamento arriva al vecchio indirizzo e si apre senza login.
- I codici di recupero, invece, si salvano con `Hash::make` (bcrypt). I codici `XXXX-XXXX` hanno bassa entropia: in caso di furto del database un hash veloce come SHA-256 sarebbe forzabile, mentre bcrypt è lento e con salt. Non serve cercarli con un indice: la verifica confronta il codice con gli hash dell'utente, al massimo 8.
- `security_events.user_id` non è nullable: gli eventi riguardano solo utenti esistenti. `login_failed` si registra solo quando l'email appartiene a un utente; per un'email inesistente non si scrive nessun evento, e la risposta HTTP resta identica nei due casi.
- Negli eventi `passkey_added` e `passkey_removed`, e nel `login` fatto con una passkey, i `metadata` contengono `passkey_name`: il nome della passkey al momento dell'evento.

Enum `SecurityEventType`: `login`, `login_failed`, `two_factor_challenge_failed`, `logout`, `email_verified`, `password_changed`, `password_reset`, `email_change_requested`, `email_changed`, `email_change_cancelled`, `two_factor_enabled`, `two_factor_disabled`, `recovery_codes_regenerated`, `recovery_code_used`, `passkey_added`, `passkey_removed`, `other_sessions_closed`. Ogni caso ha `label()` in italiano, con queste parole fisse:

| Valore | Etichetta |
|---|---|
| `login` | Accesso effettuato |
| `login_failed` | Accesso non riuscito |
| `two_factor_challenge_failed` | Verifica in due passaggi non riuscita |
| `logout` | Disconnessione |
| `email_verified` | Email confermata |
| `password_changed` | Password cambiata |
| `password_reset` | Password reimpostata |
| `email_change_requested` | Cambio email richiesto |
| `email_changed` | Email aggiornata |
| `email_change_cancelled` | Cambio email annullato |
| `two_factor_enabled` | Doppia autenticazione attivata |
| `two_factor_disabled` | Doppia autenticazione disattivata |
| `recovery_codes_regenerated` | Codici di recupero rigenerati |
| `recovery_code_used` | Codice di recupero usato |
| `passkey_added` | Passkey aggiunta |
| `passkey_removed` | Passkey rimossa |
| `other_sessions_closed` | Altre sessioni chiuse |

Enum `UserRole`: `customer` → «Cliente», `admin` → «Amministrazione» (un'etichetta che non dipende dal genere della persona).

## Configurazione

`config/auth.php`:

- `verification.expire`: 1440 minuti (il link di verifica email vale 24 ore)
- `passwords.users.expire`: 60 minuti

`config/security.php`:

- `confirmation_timeout`: 600 secondi
- `login_challenge_ttl`: 300 secondi
- `email_change_ttl`: 60 minuti
- `recovery_codes_count`: 8
- `account_deletion_pending_window`: 15 minuti (un ordine `pending` più recente blocca l'eliminazione dell'account)
- `security_events_per_page`: 10

## Architettura del secondo fattore

- Interfaccia `App\Contracts\Security\SecondFactorVerifier` con `verify(User $user, array $payload): bool`.
- Implementazioni: `TotpVerifier`, `RecoveryCodeVerifier`, `PasskeyVerifier`.
- `VerifySecondFactorAction` si usa solo se `hasSecondFactor()`: sceglie l'implementazione in base al campo `method` (`totp`, `recovery_code`, `passkey`) e lancia `InvalidSecondFactorException` (422) se la verifica fallisce.
- I metodi disponibili, restituiti nel campo `methods` delle risposte, sono `totp` e `recovery_code`, più `passkey` se l'utente ha almeno una passkey registrata.
- Il TOTP accetta una finestra di un intervallo e rifiuta intervalli già usati.
- Un codice di recupero usato viene rimosso; se ne restano due o meno, la risposta lo segnala e il frontend invita a rigenerarli.
- Nei test `PasskeyVerifier` si sostituisce con un'implementazione finta tramite il container.

## Conferma dell'identità (step-up)

- Endpoint `POST /me/confirm-identity` con `password` e, se la doppia autenticazione è attiva, `method` più `code` oppure la risposta della passkey (opzioni da `POST /me/confirm-identity/passkey-options`).
- Se riuscita, la sessione salva `identity_confirmed_at`, valida per `confirmation_timeout`.
- Middleware `EnsureIdentityConfirmed`, alias `identity.confirmed`: se la conferma manca o è scaduta risponde **423** con `{ "message": "...", "requires": ["password", "second_factor"] }`, dove `second_factor` compare solo se `hasSecondFactor()`.
- Il frontend intercetta il 423, apre `ConfirmIdentityDialog` e ripete la richiesta originale. La richiesta può portare nella sua configurazione una descrizione dell'operazione, che il dialog mostra (es. «Per disattivare la doppia autenticazione, conferma che sei tu.»).
- Il frontend può anche aprire la conferma in modo esplicito prima di un'azione che richiede altri dati all'utente (cambio email).
- La conferma si azzera al logout e dopo ogni cambio password.

## Flussi

### Registrazione e verifica email

1. `POST /register`: l'email non può avere il dominio di primo livello `.invalid` (sezione "Indirizzi `.invalid`"). Crea un `customer` con `password_changed_at` valorizzata, invia l'email di verifica, avvia la sessione. Il frontend porta a `/verify-email` senza parametri: "Controlla la tua email", con il link valido 24 ore e "Invia di nuovo il link".
2. Il link nell'email punta al frontend (`/verify-email?...`) e contiene i parametri firmati dell'API: si configura con `VerifyEmail::createUrlUsing()`, che firma con `URL::temporarySignedRoute()` la rotta `v1.verification.verify` (le rotte hanno il prefisso di nome `v1.`). La callback personalizzata legge da sé la scadenza da `config('auth.verification.expire')`: 24 ore.
3. La pagina del frontend chiama `POST /email/verify/{id}/{hash}` inoltrando `expires` e `signature`. La rotta è in `public.php` con il middleware `signed` e **non richiede l'autenticazione**: la protegge solo la firma del link, che copre id dell'utente, hash dell'email e scadenza.
   - **Motivo:** chi si registra dal computer e apre l'email dal telefono non deve fare login solo per confermare l'indirizzo. La firma basta perché lega il link a quell'utente e a quell'indirizzo, e scade.
   - Non si usano la rotta e la Form Request standard di Laravel (`EmailVerificationRequest`), che richiedono l'utente autenticato. Il parametro `{id}` **non** usa il route model binding, perché la gestione centralizzata trasformerebbe un utente inesistente in 404: una Action cerca l'utente con `find()` e, se non esiste, lancia la stessa eccezione del 403 con lo stesso messaggio; poi verifica che `hash` corrisponda a `sha1` dell'email attuale (`hash_equals`) e, se non è già verificato, valorizza `email_verified_at` e scrive l'evento `email_verified`.
   - Firma non valida o scaduta → 403. Utente inesistente o `hash` che non corrisponde all'email attuale (per esempio dopo un cambio email) → 403 con lo stesso messaggio, così non si rivela nulla. La pagina mostra "Il link è scaduto" con "Invia un nuovo link".
   - Utente già verificato con link valido → 200, senza nuovo evento: la pagina mostra comunque "Email confermata".
   - La risposta 200 è `{ "data": { "email": "..." } }` con l'indirizzo appena confermato: la pagina lo mostra («Grazie: l'indirizzo {email} è confermato.»), così un altro utente autenticato che apre il link di qualcun altro vede quale indirizzo è stato confermato e non pensa che sia il proprio.
   - Limiter `verification`, per utente se autenticato e per IP altrimenti.
   - La verifica non avvia una sessione: l'ospite resta ospite.
4. `POST /email/verification-notification` reinvia l'email (autenticato, limitato).
5. Senza email verificata l'utente naviga e usa il carrello, ma il checkout è bloccato dal middleware `verified`.

### Login

1. `POST /login` con email e password.
2. Senza doppia autenticazione: rigenera la sessione e restituisce l'utente.
3. Con doppia autenticazione: **non** autentica. Salva in sessione `login.pending_user_id` con scadenza `login_challenge_ttl` e risponde `{ "two_factor_required": true, "methods": [...] }`.
4. `POST /login/two-factor` con `method` e `code`, oppure con la risposta della passkey ottenuta da `POST /login/two-factor/passkey-options`. Se riesce, autentica e rigenera la sessione.
   - Challenge mancante o scaduta (`login.pending_user_id` assente o oltre `login_challenge_ttl`): `TwoFactorChallengeExpiredException`, 422 con `{ "message": "La verifica è scaduta: accedi di nuovo.", "code": "two_factor_challenge_expired" }`. Il frontend torna al login.
   - Codice sbagliato: 422 con errore sul campo `code` e nessun `code` di primo livello. Il frontend resta sulla pagina.
   - Non si usa il 419, che appartiene al CSRF e viene ripetuto dall'interceptor.
5. Accesso senza password con passkey, sempre disponibile per chi ha una passkey, con o senza doppia autenticazione: `POST /passkeys/login/options`, poi `POST /passkeys/login`. La passkey da sola basta, non si chiede altro.
6. Credenziali errate: `InvalidCredentialsException` (422) con `{ "message": "Credenziali non valide", "code": "invalid_credentials" }`, senza errori sui campi (non si usa `ValidationException`, che porta sempre `errors`), così il frontend mostra un solo avviso e non fa pensare che il problema sia l'indirizzo. Mai "email non trovata".
7. Account anonimizzato (`anonymized_at` valorizzata): la stessa `InvalidCredentialsException`, senza evento `login_failed`. Con email fittizia e password casuale l'accesso non riuscirebbe comunque; il controllo esplicito rende la regola indipendente da questo. Per difesa in profondità lo stesso controllo vale per `forgot-password` (nessuna email, stessa risposta generica) e per l'accesso con passkey.

### Recupero password

1. `POST /forgot-password` con l'email: risponde **sempre** lo stesso messaggio generico, che l'indirizzo esista o no.
2. Il link nell'email punta al frontend: `/reset-password?token=...&email=...`. Scade dopo 60 minuti.
3. `POST /reset-password/check` con token ed email: se il token è valido risponde `{ "requires_second_factor": bool, "methods": [...] }`; se non lo è, 422 e la pagina mostra "Il link è scaduto".
4. `POST /reset-password` con token, email, nuova password, conferma e, se la doppia autenticazione è attiva, il secondo fattore: codice TOTP, codice di recupero o passkey. Per la passkey le opzioni si ottengono da `POST /reset-password/passkey-options` con token ed email.
5. Dopo il reset: `password_changed_at` aggiornata, tutte le sessioni dell'utente chiuse, notifica email, evento `password_reset`, nessun login automatico.

### Cambio password

- `PUT /me/password` con `current_password`, `password`, `password_confirmation` e, se la doppia autenticazione è attiva, `method` più `code` oppure la risposta della passkey (opzioni da `POST /me/password/passkey-options`).
- Il secondo fattore si chiede **in linea**, nel form stesso (mockup, pagina Sicurezza): la rotta non usa `identity.confirmed`, così la password non viene chiesta due volte.
- Secondo fattore mancante con doppia autenticazione attiva → 422 sul campo `code`.
- Dopo il cambio: `password_changed_at` aggiornata, altre sessioni chiuse, sessione corrente rigenerata, conferma dell'identità azzerata, notifica email, evento `password_changed`.

### Cambio email

1. `POST /me/email` con il nuovo indirizzo, protetto da `identity.confirmed` (password, più secondo fattore se la doppia autenticazione è attiva). Il nuovo indirizzo deve essere diverso dall'attuale, non già registrato e senza dominio di primo livello `.invalid` (sezione "Indirizzi `.invalid`").
2. Crea una `email_change_requests` (annulla eventuali richieste precedenti in sospeso) e invia:
   - al **nuovo** indirizzo il link di conferma: `/confirm-email-change?token=...`
   - al **vecchio** indirizzo un avviso con il link di annullamento: `/cancel-email-change?token=...`
3. `POST /email-change/confirm` con il token: ricontrolla l'unicità, aggiorna l'email e `email_verified_at`, notifica il vecchio indirizzo, evento `email_changed`.
4. `POST /email-change/cancel` con il token di annullamento: annulla la richiesta, evento `email_change_cancelled`, e il messaggio consiglia di cambiare la password.
5. `DELETE /me/email-change` (autenticato, senza conferma dell'identità, limiter `email-change`): annulla dal profilo la richiesta in sospeso, invia all'indirizzo attuale l'avviso di cambio annullato, evento `email_change_cancelled`, risposta 204. Senza richiesta in sospeso risponde 404.
6. `UserResource` espone `pending_email_change`: `{ "new_email": "...", "expires_at": "..." }` per una richiesta non confermata, non annullata e non scaduta, altrimenti `null`.
7. Finché la richiesta non è confermata, l'email dell'account resta quella vecchia.

### Doppia autenticazione TOTP

1. `POST /me/two-factor` (`identity.confirmed`): genera un segreto non ancora confermato e restituisce QR code SVG e chiave per l'inserimento manuale.
2. `POST /me/two-factor/confirm` con un codice valido: attiva la doppia autenticazione e restituisce i codici di recupero in chiaro, **una sola volta**.
3. `POST /me/two-factor/recovery-codes` (`identity.confirmed`): rigenera i codici, invalida i precedenti, li mostra una sola volta.
4. `DELETE /me/two-factor` (`identity.confirmed`): disattiva la doppia autenticazione, cancella segreto e codici di recupero, chiude le altre sessioni, notifica, evento. Le passkey restano e continuano a permettere l'accesso senza password.
5. Formato dei codici di recupero: `XXXX-XXXX`, salvati come hash e monouso.

### Passkey

1. `GET /me/passkeys`: elenco con nome, data di creazione e ultimo utilizzo.
2. `POST /me/passkeys/options` (`identity.confirmed`), poi `POST /me/passkeys` con la risposta del browser e un nome scelto dall'utente. Evento `passkey_added` con `passkey_name`.
3. `PATCH /me/passkeys/{passkey}`: rinomina.
4. `DELETE /me/passkeys/{passkey}` (`identity.confirmed`): rimozione, notifica, evento `passkey_removed` con `passkey_name`.
5. L'interfaccia consiglia di registrare almeno due passkey, su dispositivi diversi.
6. Se il browser non supporta WebAuthn, le opzioni passkey non vengono mostrate.

### Eliminazione dell'account

Si realizza nella Fase 3b (`docs/roadmap.md`), con la colonna `users.anonymized_at` aggiunta da una nuova migration.

1. `DELETE /me`, con i middleware in quest'ordine: `can:delete,…` sull'utente autenticato (Policy), poi `identity.confirmed` (password, più secondo fattore se la doppia autenticazione è attiva). La logica sta in `DeleteAccountAction`.
2. **Chi può farlo:** solo un `customer`. Un admin riceve 403 dalla Policy, così il negozio non resta senza amministratori. Il permesso si controlla **prima** della conferma dell'identità: un admin riceve subito 403, senza che gli si chieda la password.
3. **Ordini aperti:** sono aperti gli ordini `paid` e `shipped`, e gli ordini `pending` creati negli ultimi `account_deletion_pending_window` minuti (15), cioè un pagamento ancora in corso. Un ordine `pending` più vecchio è un orfano e non blocca per sempre l'eliminazione. Con ordini aperti: `AccountHasOpenOrdersException` (409) con `code` `account_has_open_orders` e il messaggio «Hai ordini ancora in corso: potrai eliminare l'account quando saranno consegnati o annullati.». Nessun effetto.
4. **Prima della transazione** si legge l'indirizzo email originale e il nome, che servono alla notifica.
5. **In `DB::transaction()`, con `lockForUpdate()` sull'utente** (il controllo degli ordini aperti si ripete sotto lock; un checkout che arriva dopo trova le sessioni chiuse e riceve 401):
   - si cancellano indirizzi, carrello con le sue righe, eventi di sicurezza, richieste di cambio email, token di reset dell'indirizzo originale (`password_reset_tokens`) e tutte le sessioni dell'utente, compresa quella corrente
   - si disattiva la doppia autenticazione: `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` e `two_factor_last_used_step` a null
   - si cancellano le passkey: la cancellazione si aggiunge a `DeleteAccountAction` nella Fase 3c, insieme alla tabella della libreria
   - si anonimizza l'utente: `name` «Utente eliminato»; `email` fittizia e univoca `deleted-{id}@anonymized.invalid` (il dominio `.invalid` è riservato e non riceve posta); `password` con l'hash di una stringa casuale lunga; `email_verified_at` e `remember_token` a null; `anonymized_at` valorizzata
   - ordini, righe, cronologia degli stati e pagamenti **non** si toccano
6. **Dopo il commit:** notifica on-demand `AccountDeletedNotification` all'indirizzo originale (`Notification::route('mail', $originalEmail)`), con `afterCommit()`. Così parte solo se l'eliminazione è riuscita, e anche con un driver di coda asincrono non finisce all'indirizzo `.invalid`. Poi logout della guard `web`, invalidazione della sessione e risposta 204.
7. Nessun evento di sicurezza: il registro dell'utente è appena stato cancellato.
8. L'indirizzo originale torna libero: una nuova registrazione crea un account nuovo, senza gli ordini di quello eliminato.
9. Nell'area admin gli ordini di un account anonimizzato mostrano «Cliente eliminato» (skill `ecommerce-domain`, "Area admin").

**Motivazione (GDPR e obblighi fiscali).** Il diritto alla cancellazione (GDPR, art. 17) non si applica quando il trattamento serve ad adempiere un obbligo di legge (art. 17, par. 3, lett. b). Ordini, righe, snapshot dell'indirizzo di spedizione e pagamenti sono documentazione contabile che va conservata per 10 anni (art. 2220 del codice civile). Per questo:

- si conservano solo quei dati, che restano staccati da un account riconoscibile: nome ed email dell'account sono sostituiti, mentre lo snapshot dell'indirizzo resta com'era al momento dell'acquisto, perché fa parte del documento contabile
- si cancella tutto ciò che non serve a quell'obbligo: indirizzi salvati, carrello, sessioni, eventi di sicurezza, richieste di cambio email, passkey e doppia autenticazione
- l'utente non viene cancellato fisicamente: `orders.user_id` e `order_status_changes.changed_by` hanno `restrictOnDelete` (`docs/database/er-diagram.md`, N1 e D13)

La cancellazione automatica degli ordini dopo 10 anni è fuori dal progetto: è elencata nel README tra gli sviluppi futuri.

### Indirizzi `.invalid`

- Il dominio di primo livello `.invalid` è riservato (RFC 2606) e non riceve posta: il progetto lo usa per le email fittizie degli account anonimizzati.
- Registrazione (`POST /register`) e cambio email (`POST /me/email`) rifiutano **qualunque** indirizzo che termina con `.invalid`, senza distinzione di maiuscole: 422 sul campo `email` con il messaggio «Inserisci un indirizzo email valido.».
- Motivo: l'email fittizia `deleted-{id}@anonymized.invalid` è prevedibile. Se qualcuno potesse registrarla prima, l'indice unico su `users.email` farebbe fallire l'eliminazione dell'account `{id}`.
- La regola sta in un solo punto (una Rule dedicata, es. `App\Rules\NotReservedEmailDomain`), usata da entrambe le Form Request.

### Panoramica, sessioni ed eventi

- `GET /me/security`:

```json
{
  "data": {
    "two_factor_enabled": true,
    "two_factor_confirmed_at": "2026-09-12T21:40:00+02:00",
    "recovery_codes_remaining": 6,
    "recovery_codes_total": 8,
    "passkeys_count": 2,
    "email_verified": true,
    "password_changed_at": "2026-09-12T21:31:00+02:00"
  }
}
```

  Senza doppia autenticazione `two_factor_confirmed_at` è null e i conteggi dei codici sono 0.
- `GET /me/security-events`: eventi dell'utente dal più recente, paginati a `security_events_per_page` (10), con `meta` e `links` della paginazione standard.
- Extra opzionale: `GET /me/sessions`, `DELETE /me/sessions/{id}`, `DELETE /me/sessions` per le altre sessioni.

### Eventi di sicurezza

- Il database conserva lo user agent grezzo; l'etichetta si calcola nella risposta.
- `App\Support\UserAgentLabel`, parser interno senza dipendenze, trasforma lo user agent in «Browser su sistema» (es. "Chrome su macOS", "Safari su iOS"). Riconosce almeno Chrome, Safari, Firefox, Edge e Opera, e Windows, macOS, iOS, iPadOS, Android e Linux; l'ordine dei controlli tiene conto che Edge e Opera contengono anche "Chrome" e Chrome contiene anche "Safari". Se browser o sistema non si riconoscono, o lo user agent manca, restituisce «Dispositivo sconosciuto».
- `SecurityEventResource`:

```json
{
  "id": 42,
  "type": "passkey_added",
  "label": "Passkey aggiunta",
  "device": "Safari su macOS",
  "ip_address": "93.45.112.18",
  "passkey_name": "MacBook di Giulia",
  "created_at": "2026-09-20T18:02:00+02:00"
}
```

  `passkey_name` è null per gli eventi che non riguardano una passkey.

## Riepilogo delle protezioni

| Operazione | Doppia autenticazione non attiva | Doppia autenticazione attiva |
|---|---|---|
| Login | password, oppure solo passkey | password + (codice TOTP, codice di recupero o passkey), oppure solo passkey |
| Recupero password | link via email | link via email + (codice TOTP, codice di recupero o passkey) |
| Cambio password | password attuale | password attuale + (codice TOTP, codice di recupero o passkey), nel form |
| Cambio email | conferma dell'identità con password + verifica del nuovo indirizzo + avviso al vecchio | lo stesso, con conferma dell'identità con password + secondo fattore |
| Attivare il TOTP, aggiungere o rimuovere una passkey | conferma dell'identità con password | conferma dell'identità con password + secondo fattore |
| Disattivare il TOTP, rigenerare i codici | — | conferma dell'identità con password + secondo fattore |
| Annullare un cambio email in sospeso | sessione autenticata o link dell'email | sessione autenticata o link dell'email |
| Eliminare l'account (solo cliente, senza ordini aperti) | conferma dell'identità con password + dialog di conferma esplicito | conferma dell'identità con password + secondo fattore + dialog di conferma esplicito |

## Notifiche email (in italiano)

Verifica email, reset password, password cambiata, conferma cambio email (nuovo indirizzo), avviso cambio email (vecchio indirizzo), cambio email annullato dal profilo, email cambiata, doppia autenticazione attivata o disattivata, codici di recupero rigenerati, codice di recupero usato, passkey aggiunta o rimossa, account eliminato. Ogni avviso indica data, ora e dispositivo (etichetta di `UserAgentLabel`), e invita a cambiare la password se l'operazione non è stata fatta dall'utente. Fa eccezione l'avviso di account eliminato: va all'indirizzo originale, indica data e ora, dice che gli ordini restano conservati per gli obblighi fiscali e, invece di invitare a cambiare la password (non c'è più un account), invita a contattare l'assistenza (sezione `contatti` di `GET /shop/support`) se l'operazione non è stata fatta dall'utente. L'email di verifica dice che il link vale 24 ore; quelle di reset e di cambio email dicono 60 minuti.

## Password

- `Password::defaults()` in `AppServiceProvider`: minimo 10 caratteri, lettere maiuscole e minuscole, numeri, `uncompromised()`.
- `uncompromised()` è disattivato nell'ambiente di test perché fa una chiamata esterna.
- Lo schema Zod della password nel frontend rispecchia le stesse regole (almeno 10 caratteri, maiuscole e minuscole, numeri). Il controllo `uncompromised` resta solo nel backend: il suo errore arriva come 422 e si mostra sul campo.

## Rate limiting

| Limiter | Limite |
|---|---|
| `login` | 5 al minuto per email + IP |
| `two-factor` | 5 al minuto per sessione |
| `password-reset` | 3 ogni 10 minuti per email + IP |
| `verification` | 6 al minuto per utente, per IP se ospite |
| `email-change` | 3 all'ora per utente |
| `identity-confirmation` | 5 al minuto per utente |
| `assistant` | 10 al minuto per utente, per IP se ospite (skill `ai-assistant`) |

- `identity-confirmation` si applica a `POST /me/confirm-identity`, `POST /me/confirm-identity/passkey-options`, `PUT /me/password` e `POST /me/password/passkey-options`: tutte accettano o preparano un secondo fattore.
- `email-change` si applica a `POST /me/email` e `DELETE /me/email-change`.
- `verification` si applica a `POST /email/verify/{id}/{hash}` e a `POST /email/verification-notification` con un solo contatore condiviso: per un utente autenticato sono 6 richieste al minuto in totale tra le due rotte. Il test del 429 usa una rotta sola.
- Oltre il limite si risponde 429 con messaggio in italiano e header `Retry-After` in secondi.
- Frontend e backend sono su origini diverse: `config/cors.php` include `Retry-After` in `exposed_headers`, altrimenti il browser non lo rende leggibile ad axios.
- Il frontend mostra «Troppi tentativi. Riprova tra N secondi», con N preso da `Retry-After` tramite `normalizeApiError`.

## Frontend

- Pagine in `features/auth`: login, verifica in due passaggi al login, registrazione, verifica email ("Controlla la tua email", "Email confermata", "Il link è scaduto"), password dimenticata, reset password, conferma e annullamento del cambio email. Testi e stati dal mockup `03-account.pdf`.
- Pagine in `features/security`: sicurezza dell'account con password (ultima modifica e cambio con secondo fattore in linea), doppia autenticazione, codici di recupero, passkey, attività recente. Il cambio email sta nel profilo in `features/account`.
- Cambio email dal profilo: "Cambia email" apre prima la conferma dell'identità, poi un `Dialog` con il campo "Nuova email" (non presente nel mockup). Con una richiesta in sospeso il profilo mostra l'avviso con "Annulla la richiesta", che chiama `DELETE /me/email-change`.
- `IdentityConfirmationProvider` gestisce il 423 intercettato da `httpClient` e mostra `ConfirmIdentityDialog`; espone anche una funzione per chiedere la conferma in modo esplicito.
- Banner di email non verificata in `StoreLayout` con reinvio, nascosto nelle pagine di `AuthLayout` (accesso e pagine dai link email), dove sarebbe un doppione.
- Gli errori che il frontend deve distinguere oltre allo status hanno un campo `code` stabile (es. `two_factor_challenge_expired`), esposto da `normalizeApiError`.
- Campi del codice con `autoComplete="one-time-code"`; campi password con `current-password` o `new-password`.
- Sotto i campi della nuova password, l'elenco dei requisiti si aggiorna mentre l'utente scrive, con le stesse regole dello schema Zod.
- I codici di recupero si mostrano una volta sola, con copia e download in un file `.txt`.
- L'attività recente mostra la prima pagina e il pulsante "Mostra altre attività" finché ci sono pagine successive.
- Eliminazione dell'account (Fase 9, posizione e testi dal Design E):
  - sezione "Elimina account" con il pulsante «Elimina l'account» in colore errore
  - il pulsante apre un `Dialog` di conferma esplicito, che elenca cosa si cancella e dice che gli ordini restano conservati per gli obblighi fiscali; il pulsante di conferma resta disabilitato finché l'utente non spunta «Ho capito che l'operazione non si può annullare»
  - la richiesta `DELETE /me` porta la descrizione `identityPrompt` («Prima di eliminare l'account, conferma che sei tu.»); il 423 lo gestisce l'interceptor
  - dopo il 204: cache di TanStack Query svuotata, navigazione alla home e Snackbar «Il tuo account è stato eliminato.»
  - un 409 `account_has_open_orders` lascia il dialog aperto con l'`Alert` del messaggio ricevuto

## Test obbligatori

Oltre ai casi standard della skill `testing`:

- login con e senza doppia autenticazione, codice riutilizzato
- challenge scaduta (`travelTo()` oltre 300 secondi) e challenge assente: 422 con `code` `two_factor_challenge_expired`; codice errato: 422 con errore sul campo `code` e senza `code` di primo livello
- credenziali errate: 422 con `code` `invalid_credentials` e senza `errors`, stessa risposta per email inesistente e password sbagliata
- eventi del login fallito: con email esistente e password sbagliata una riga `login_failed` per quell'utente; con email inesistente nessuna riga in `security_events`
- utente con sole passkey e senza TOTP: `hasSecondFactor()` false, login con password senza challenge, accesso con passkey riuscito, nessun codice di recupero generato, 423 con `requires` senza `second_factor`
- utente con TOTP e passkey: la passkey è accettata come secondo fattore al login, al reset, al cambio password e nella conferma dell'identità; `methods` contiene `passkey`
- utente con TOTP senza passkey: `methods` non contiene `passkey`
- disattivazione del TOTP: codici di recupero cancellati, passkey conservate
- ogni rotta `identity.confirmed` risponde 423 senza conferma e 200 dopo la conferma
- codice di recupero accettato una sola volta
- verifica email da ospite con link valido → 200 con l'email confermata in `data.email`, `email_verified_at` valorizzata, nessuna sessione avviata
- verifica email da ospite oltre il limite → 429 con `Retry-After`
- verifica email da autenticato con link valido → 200
- verifica email con utente già verificato → 200, `email_verified_at` invariata, nessun nuovo evento
- verifica email con firma alterata → 403; con `hash` che non corrisponde all'email attuale ma firma valida → 403; con id inesistente → 403
- link di verifica email valido dopo 23 ore e scaduto dopo 25 (`travelTo()`) → 403; link di reset e di cambio email scaduti dopo 61 minuti
- forgot password con email inesistente: stessa risposta di quella esistente
- reset con secondo fattore richiesto e mancante → 422
- cambio password con doppia autenticazione attiva e secondo fattore mancante → 422; con secondo fattore valido → 200
- `password_changed_at` valorizzata alla registrazione, al cambio e al reset
- cambio email: il vecchio indirizzo resta fino alla conferma, token scaduto, token riutilizzato, annullamento dal link (anche da ospite); nel database solo l'hash dei token (64 caratteri esadecimali), mai il token in chiaro
- `DELETE /me/email-change`: 204 con evento `email_change_cancelled`, avviso inviato all'indirizzo attuale ed email invariata, 404 senza richiesta in sospeso, 401 da ospite; `pending_email_change` in `GET /me` prima e dopo
- chiusura delle altre sessioni dopo cambio e reset password
- `UserAgentLabel` (unit), con user agent reali scritti a mano nel test: Chrome su macOS, Safari su iOS, Safari su iPadOS, Firefox su Windows, Firefox su Linux, Edge su Windows (non Chrome), Opera su Windows (non Chrome), Chrome su Android, stringa vuota e stringa sconosciuta → «Dispositivo sconosciuto»
- `POST /me/password/passkey-options` e `POST /me/confirm-identity/passkey-options`: 401 da ospite; 422 per un utente senza passkey; 200 con le opzioni per un utente con passkey (verificatore finto); per entrambe 422 anche per un utente con passkey ma senza doppia autenticazione attiva
- `GET /me/security-events`: 10 elementi per pagina con `meta`, solo gli eventi dell'utente, `device` e `passkey_name` negli eventi passkey
- `GET /me/security` con i valori attesi per un utente con e senza doppia autenticazione
- notifiche inviate (`Notification::fake()`) ed eventi di sicurezza registrati
- segreti mai presenti nelle risposte JSON
- rate limiting sulle rotte di autenticazione, su `PUT /me/password` (sesto tentativo nel minuto) e su `DELETE /me/email-change`: 429 con header `Retry-After`
- eliminazione dell'account: casi della skill `testing`, sezione "Eliminazione dell'account"
