---
name: testing
description: Strategia e convenzioni dei test del progetto — Pest nel backend (feature, unit, architettura, copertura con PCOV) e Vitest con React Testing Library, user-event e MSW nel frontend. Definisce cosa testare, dove mettere i test, utility condivise e comandi. Usala ogni volta che scrivi o modifichi codice, perché i test fanno parte dello stesso task.
---

# Test

## Regole generali

- I test si scrivono nello stesso task del codice che verificano, mai rimandati a dopo.
- Un task è concluso solo con `make test` e `make lint` verdi.
- Descrizioni dei test in inglese, chiare e comportamentali: `it('rejects checkout when stock is insufficient')`.
- Nessun commento inline nei test.
- Ogni test crea i propri dati e non dipende dall'ordine di esecuzione.
- I valori attesi si scrivono a mano nel test o in un helper di test (es. `frontendOrigin()` restituisce `'http://localhost:5173'`), mai ricavati dal codice o dalla configurazione che il test verifica: altrimenti il test confronta il sistema con sé stesso. Quando un valore viene dalla configurazione, un test dedicato controlla che la configurazione contenga esattamente il valore atteso.

## Comandi

| Comando | Effetto |
|---|---|
| `make test` | backend e frontend |
| `make test-backend` | Pest |
| `make test-frontend` | Vitest in modalità singola esecuzione |
| `make coverage-backend` | Pest con copertura tramite PCOV |
| `make coverage-frontend` | Vitest con `@vitest/coverage-v8` |

Obiettivo di copertura: almeno 80% nel backend e 70% nel frontend, con Action, Policy e hook critici vicini al 100%.

## Backend — Pest

### Struttura

```
tests/
├── Pest.php                     # configurazione e helper
├── Arch/ArchitectureTest.php
├── Feature/Api/V1/{Area}/       # un file per endpoint o flusso
└── Unit/                        # enum, calcoli, servizi isolati
```

### Configurazione

- Database MySQL `ecommerce_testing`, configurato in `.env.testing` e `phpunit.xml`.
- `RefreshDatabase` per tutti i test in `Feature`.
- Helper in `Pest.php`: `actingAsCustomer()`, `actingAsAdmin()`, `actingAsVerifiedCustomer()`, `confirmIdentity()` che imposta la conferma dell'identità in sessione.
- Fake a seconda del caso: `Notification::fake()`, `Mail::fake()`, `Event::fake()`, `Storage::fake('public')`.
- Nell'ambiente di test `uncompromised()` è disattivato.
- Il TOTP si testa generando codici validi con la stessa libreria usata dal codice.
- Il verificatore delle passkey e il `PaymentGateway` si sostituiscono con implementazioni finte tramite il container.

### Cosa testare per ogni endpoint

| Caso | Atteso |
|---|---|
| Richiesta corretta | status corretto e struttura JSON (`assertJsonStructure`, `assertJsonPath`) |
| Dati non validi | 422 con `errors` sui campi giusti; usa i `dataset` di Pest per coprire più casi |
| Utente non autenticato | 401 |
| Utente non autorizzato | 403 (cliente su rotta admin, cliente su risorsa di un altro) |
| Risorsa inesistente | 404 dove applicabile |
| Effetti collaterali | database (`assertDatabaseHas`), notifiche, eventi, file |

### Catalogo

- Filtri di `GET /products` (`category`, `min_price`, `max_price`, `in_stock`, `new`) con un dataset di casi, e 422 per valori non validi (es. `sort=popular`).
- Ordinamenti `newest` (predefinito), `price_asc` e `price_desc`, compreso l'ordine stabile a parità di valore.
- `meta.price_range` calcolato sulla categoria e non influenzato da prezzo, disponibilità e novità.
- `is_new` ai bordi della finestra: creato 29 giorni fa sì, 31 giorni fa no (con `travelTo()`).
- Prodotti correlati: massimo 4, stessa categoria, esclusi il prodotto corrente, gli esauriti e i disattivati; meno di 4 quando la categoria non ne ha abbastanza.
- Prodotti disattivati o eliminati: assenti dall'elenco e 404 sul dettaglio.

### Test di flusso

- Checkout completo, stock insufficiente, pagamento rifiutato con ripristino dello stock e carrello intatto.
- Annullamento dell'ordine con ripristino dello stock.
- Transizioni di stato consentite e vietate.
- Flussi di sicurezza elencati nella skill `account-security`.

### Unit test

- `OrderStatus::canTransitionTo()` con un dataset di tutte le coppie, ed etichette esatte di `label()` (In attesa, Pagato, Spedito, Consegnato, Annullato).
- Disponibilità del prodotto con un dataset di stock 0, 1, 2, 3 e 4 (soglia 3): Esaurito, Ultimo pezzo, Ultimi 2 pezzi, Ultimi 3 pezzi, Disponibile. I valori attesi sono scritti nel test, non letti dalla configurazione.
- Calcolo di subtotale, spedizione e totale.
- Generazione del numero d'ordine.
- Verificatori del secondo fattore.

### Test di architettura

In `tests/Arch/ArchitectureTest.php`, con `arch()` di Pest:

- nessun uso di `dd`, `dump`, `ray`, `var_dump` nel codice applicativo
- controller `final` e con suffisso `Controller`
- Action `final`, con suffisso `Action` e un solo metodo pubblico `execute`
- Form Request estendono `FormRequest`, Resource estendono `JsonResource`
- enum in `App\Enums`, interfacce in `App\Contracts`
- i controller non usano direttamente `DB`
- preset di sicurezza di Pest, se disponibile nella versione installata

## Frontend — Vitest, React Testing Library, MSW

### Strumenti

- **Vitest** con ambiente `jsdom`, configurato nel blocco `test` di `vite.config.js`.
- **React Testing Library** e **@testing-library/user-event** per interagire con i componenti come farebbe una persona.
- **@testing-library/jest-dom** per le asserzioni sul DOM.
- **MSW** per intercettare le chiamate HTTP: i test non dipendono dal backend acceso.

### Struttura

```
src/
├── test/
│   ├── setup.js                 # jest-dom, avvio e reset del server MSW
│   ├── renderWithProviders.jsx  # tema, QueryClient nuovo per ogni test, router in memoria, AuthProvider
│   ├── factories.js             # buildUser, buildProduct, buildCart, buildOrder
│   └── mocks/
│       ├── server.js
│       └── handlers.js          # risposte di successo predefinite
└── features/cart/components/CartItemRow.test.jsx
```

- I test stanno accanto al file testato, con suffisso `.test.jsx` o `.test.js`.
- `setup.js` avvia MSW con `onUnhandledRequest: 'error'`: una chiamata non prevista fa fallire il test.
- Ogni test personalizza le risposte con `server.use(...)` per i casi di errore.
- Le factory producono oggetti con la stessa forma delle API Resource del backend.
- `renderWithProviders(ui, { route, user })` accetta la rotta iniziale e l'utente autenticato (o nessuno).
- `QueryClient` dei test con `retry: false`.

### Come si scrive un test

- Query in ordine di preferenza: `getByRole`, `getByLabelText`, `getByText`. `getByTestId` solo come ultima scelta, motivata.
- Interazioni con `const user = userEvent.setup()` e `await user.click(...)`, `await user.type(...)`.
- Contenuti asincroni con `findBy...` o `waitFor`.
- Si verifica ciò che vede l'utente, non lo stato interno dei componenti.
- Le passkey si testano simulando `@simplewebauthn/browser` con `vi.mock`.

### Cosa testare

| Area | Casi |
|---|---|
| Utility | `formatPrice` (349,00 €, 1.249,00 € con il punto delle migliaia, 0,00 €), `formatDate`, `normalizeApiError` |
| httpClient | ripetizione dopo 419, gestione del 423 con conferma e ripetizione |
| Rotte | redirect al login con ritorno alla pagina di partenza, blocco dell'area admin, pagine ospite |
| Form | validazione Zod, errori 422 del server mostrati sui campi, pulsante disabilitato durante l'invio |
| Viste con dati | stati di caricamento, errore con "Riprova", vuoto, contenuto |
| Catalogo | filtri e ordinamento (Novità, Prezzo crescente, Prezzo decrescente) che aggiornano la query string e i risultati, ritorno a pagina 1, limiti dello slider da `meta.price_range`, chip "Novità" ed etichetta di disponibilità ricevuti dall'API, correlati nascosti se vuoti |
| Home e pagine statiche | hero con i dati del prodotto e con errore o 404, immagine di ripiego per una categoria senza immagine, link del footer verso le ancore di `/assistenza` e `/note-legali` |
| Carrello | aggiunta, modifica quantità, rimozione, totali formattati |
| Checkout | avanzamento degli step, email non verificata, pagamento rifiutato, successo |
| Sicurezza | login con secondo fattore, attivazione TOTP con codici di recupero, conferma dell'identità |
| Admin | tabella prodotti, form prodotto, cambio di stato dell'ordine |

Fuori perimetro: test end-to-end con Playwright, extra facoltativo della Fase 10.
