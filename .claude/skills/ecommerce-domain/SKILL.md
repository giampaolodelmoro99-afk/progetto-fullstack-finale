---
name: ecommerce-domain
description: Regole di business dell'e-commerce — entità e colonne, importi in centesimi, snapshot degli ordini, gestione dello stock con lock, carrello, checkout, stati dell'ordine e transizioni, pagamenti simulati, novità e disponibilità calcolate, dati demo dei seeder e mappa delle API del negozio. Usala per modelli, migration, factory, seeder, carrello, checkout, ordini, pagamenti e ogni schermata che mostra questi dati.
---

# Dominio e-commerce — regole di business

Fonti delle decisioni sul negozio:
- mockup del Design A: `design/mockups/01-negozio.pdf`
- parole fisse per stati e disponibilità, nomi dei prodotti: `design/design-system/voice-and-content.md`

## Entità del negozio

Le tabelle dell'account e della sicurezza sono descritte nella skill `account-security`.

| Tabella | Colonne principali | Note |
|---|---|---|
| users | name, email, password, role, email_verified_at | `role`: `customer` o `admin` |
| addresses | user_id, full_name, street, city, postal_code, province, country, phone, is_default | un solo indirizzo predefinito per utente |
| categories | name, slug, description | cinque categorie fisse, nessuna sottocategoria |
| products | category_id, name, slug, sku, short_description, description, specifications (JSON), price, stock, is_active, created_at, deleted_at | una sola categoria per prodotto, `price` in centesimi, soft delete |
| product_images | product_id, path, alt, position | la prima per `position` è la copertina |
| carts | user_id (unico) | un carrello per utente |
| cart_items | cart_id, product_id, quantity | unico su (cart_id, product_id) |
| orders | user_id, number, status, subtotal, shipping_cost, total, shipping_address (JSON), paid_at, shipped_at, delivered_at, cancelled_at | importi in centesimi |
| order_items | order_id, product_id (nullable), product_name, product_sku, unit_price, quantity, line_total | snapshot al momento dell'acquisto |
| payments | order_id, provider, provider_reference, amount, status, card_last_four | mai dati completi della carta |

### Colonne dei prodotti

- `category_id`: obbligatoria, `foreignId()->constrained()` con `restrictOnDelete()`, coerente con la regola che una categoria con prodotti non si elimina.
- `short_description`: frase breve mostrata sotto il nome nella pagina del prodotto, massimo 255 caratteri.
- `description`: testo lungo della sezione "Descrizione".
- `specifications`: elenco ordinato di coppie per la scheda tecnica, cast `array`, ordine di visualizzazione uguale a quello dell'elenco:

```json
[
  { "label": "Dimensioni", "value": "18 × 18 × 34 cm" },
  { "label": "Peso", "value": "4,2 kg" },
  { "label": "Potenza", "value": "2 × 40 W" }
]
```

### Valori calcolati dal backend

Non sono colonne: li calcola il backend e li espone la `ProductResource`. Il frontend non li ricalcola.

- `is_new`: `true` se `created_at` rientra negli ultimi `config('shop.new_arrival_days')` giorni.
- `availability`: oggetto `{ "status": "...", "label": "..." }` calcolato dallo stock:

| Stock | `status` | `label` |
|---|---|---|
| 0 | `out_of_stock` | Esaurito |
| 1 | `low_stock` | Ultimo pezzo |
| da 2 a `config('shop.last_units_threshold')` | `low_stock` | Ultimi N pezzi |
| oltre la soglia | `available` | Disponibile |

## Configurazione

`config/shop.php`, importi in centesimi:

| Chiave | Valore | Uso |
|---|---|---|
| `shipping_cost` | 690 | costo fisso di spedizione |
| `free_shipping_threshold` | 9900 | subtotale da cui la spedizione è gratuita |
| `new_arrival_days` | 30 | finestra delle novità (`is_new`, filtro `new`) |
| `last_units_threshold` | 3 | soglia degli "Ultimi N pezzi" mostrata ai clienti |
| `low_stock_threshold` | 5 | soglia delle scorte basse nella dashboard admin |

`last_units_threshold` e `low_stock_threshold` sono distinte: la prima riguarda la comunicazione al cliente, la seconda il riordino in magazzino.

## Regole

1. **Importi in centesimi** (`unsignedInteger`), mai float. I prezzi sono IVA inclusa: nessun calcolo delle imposte.
2. **Il server non si fida del client.** Prezzi, totali e disponibilità si ricalcolano sempre dal database; il client invia solo id e quantità.
3. **Snapshot all'acquisto.** `order_items` copia nome, SKU e prezzo; `orders.shipping_address` copia l'indirizzo. Modificare o eliminare prodotti e indirizzi non altera gli ordini esistenti.
4. **Stock.** Si verifica e si decrementa in transazione con `lockForUpdate()` sui prodotti coinvolti. Se è insufficiente si lancia `InsufficientStockException` (409) con l'elenco dei prodotti problematici. Lo stock torna disponibile quando un ordine viene annullato.
5. **Catalogo pubblico:** solo prodotti `is_active` e non eliminati. I prodotti con stock 0 sono visibili ma non acquistabili.
6. **Carrello:** solo per utenti autenticati. Quantità tra 1 e lo stock disponibile. Aggiungere un prodotto già presente somma la quantità. Le righe con prodotti diventati non disponibili vengono segnalate nella risposta con un flag.
7. **Checkout solo con email verificata** (middleware `verified`).
8. **Spedizione:** costo fisso `shipping_cost` (6,90 €), gratuita quando il subtotale raggiunge `free_shipping_threshold` (99,00 €).
9. **Numero d'ordine** leggibile e univoco nel formato `ORD-{anno}-{id a 6 cifre}`, es. `ORD-2026-000042`.
10. **Ruoli:** la registrazione crea solo `customer`; l'admin nasce dal seeder.
11. **Categorie:** cinque categorie fisse, senza sottocategorie. Ogni prodotto appartiene a una sola categoria. Una categoria con prodotti associati non si elimina (409).
12. **Scorte basse:** nella dashboard admin sono i prodotti con stock tra 1 e `low_stock_threshold`.
13. **Novità:** un prodotto è nuovo se creato negli ultimi `new_arrival_days` giorni; lo stesso criterio vale per `is_new` e per il filtro `new=1`.
14. **Disponibilità:** status ed etichetta seguono la tabella dei valori calcolati, con le parole fisse di `voice-and-content.md`.

## Catalogo

### Elenco prodotti (`GET /products`)

Parametri, validati da `IndexProductRequest`:

| Parametro | Valori | Effetto |
|---|---|---|
| `search` | testo | cerca in nome e descrizione breve |
| `category` | slug | prodotti della categoria |
| `min_price`, `max_price` | centesimi | fascia di prezzo |
| `in_stock` | `1` | solo prodotti con stock maggiore di 0 |
| `new` | `1` | solo novità (regola 13) |
| `sort` | `newest` (predefinito), `price_asc`, `price_desc` | ordinamento |
| `page`, `per_page` | interi | paginazione |

- Ordinamenti: `newest` per `created_at` decrescente; `price_asc` e `price_desc` per prezzo. A parità si ordina per `id`, così la paginazione è stabile.
- Oltre a `data`, `links` e `meta` della paginazione, `meta.price_range` contiene `{ "min": ..., "max": ... }` in centesimi: il prezzo minimo e massimo dei prodotti attivi della categoria filtrata, o di tutto il catalogo se `category` manca. Gli altri filtri non lo influenzano, così i limiti dello slider del prezzo restano fermi mentre l'utente lo muove.

### Prodotti correlati (`GET /products/{product:slug}/related`)

- Fino a 4 prodotti attivi della stessa categoria, con stock maggiore di 0, escluso il prodotto corrente.
- Ordinati per novità (`created_at` decrescente).
- Se ce ne sono meno di 4 se ne restituiscono meno: non si completa con altre categorie.

## Stati dell'ordine

Enum `OrderStatus` con valori ed etichette fisse (`voice-and-content.md`):

| Valore | Etichetta |
|---|---|
| `pending` | In attesa |
| `paid` | Pagato |
| `shipped` | Spedito |
| `delivered` | Consegnato |
| `cancelled` | Annullato |

L'etichetta arriva dall'API (`{ "value": "paid", "label": "Pagato" }`); il frontend decide solo il colore del chip. Le transizioni consentite sono definite nell'enum con `canTransitionTo()`.

| Da | A | Chi |
|---|---|---|
| pending | paid | sistema, a pagamento riuscito |
| pending | cancelled | sistema, a pagamento fallito |
| paid | shipped | admin |
| paid | cancelled | admin o cliente (rimborso e ripristino dello stock) |
| shipped | delivered | admin |

`delivered` e `cancelled` sono stati finali. Una transizione non consentita lancia `InvalidOrderTransitionException` (422). Ogni transizione valorizza il timestamp corrispondente e invia una notifica email al cliente.

## Checkout (`PlaceOrderAction`)

1. Verifica che il carrello non sia vuoto e che l'indirizzo scelto appartenga all'utente.
2. Transazione: lock dei prodotti, controllo della disponibilità, creazione dell'ordine `pending` con righe e snapshot, decremento dello stock.
3. Fuori dalla transazione: addebito tramite `PaymentGateway`.
4. Pagamento riuscito: ordine `paid`, record in `payments`, svuotamento del carrello, evento `OrderPlaced` che invia l'email di conferma.
5. Pagamento fallito: ordine `cancelled`, ripristino dello stock, carrello intatto, `PaymentFailedException` (402) con messaggio chiaro.

## Pagamenti

- Interfaccia `App\Contracts\PaymentGateway` con `charge(Order $order, array $paymentData): PaymentResult` e `refund(Payment $payment): PaymentResult`.
- `FakePaymentGateway` è il driver predefinito (`PAYMENT_DRIVER=fake`): approva ogni carta con formato valido, tranne quelle che terminano con `0002`, che vengono rifiutate per poter mostrare il caso di errore nella demo.
- Numero completo, scadenza e CVC non vengono mai salvati né scritti nei log; si conservano solo le ultime quattro cifre.
- Un driver Stripe in modalità test è un'estensione opzionale.

## Dati demo

Il negozio è **Casa del Silicio**: elettronica e accessori tech della propria linea.

### Categorie (fisse)

| Slug | Nome | Descrizione |
|---|---|---|
| `audio` | Audio | Cuffie, auricolari, diffusori |
| `ricarica-ed-energia` | Ricarica ed energia | Caricatori, cavi, batterie |
| `scrivania` | Scrivania | Supporti, tastiere, accessori |
| `indossabili` | Indossabili | Orologi e cinturini |
| `casa-smart` | Casa smart | Luci e clima |

### Prodotti

- Da 40 a 60 prodotti realistici, distribuiti su tutte le categorie, con alcuni esauriti, alcuni negli ultimi pezzi (da 1 a 3), alcuni sotto la soglia admin e alcuni disattivati.
- Nomi secondo `voice-and-content.md`: un sostantivo più un nome evocativo italiano, con maiuscola, nessun marchio di terzi. Riferimenti dal mockup: Cuffie Aria, Cuffie Aria Studio, Auricolari Sussurro, Diffusore Eco, Diffusore Eco Mini, Radio Onda, Caricatore Brezza 65 W, Cavo Seta USB-C 1 m, Supporto Colle in alluminio, Orologio Meridiana, Lampada Faro.
- Unità con lo spazio (65 W, 1 m).
- Ogni prodotto ha `short_description`, `description` su più paragrafi e da 4 a 8 voci in `specifications`.
- Alcuni prodotti hanno un `created_at` negli ultimi 30 giorni, calcolato in modo deterministico rispetto alla data del seeding, così la sezione Novità è popolata. Gli altri sono distribuiti sui mesi precedenti.
- Il prodotto dell'hero della home, `cuffie-aria`, esiste sempre, è attivo, disponibile e tra le novità.

### Utenti e ordini

- Utenti fissi, documentati nel README con le credenziali:
  - un admin, senza doppia autenticazione
  - un cliente demo con email verificata, indirizzi e ordini in tutti gli stati
  - un cliente demo con doppia autenticazione attiva e segreto TOTP fisso, riportato nel README solo per la demo, così si può aggiungere all'app di autenticazione durante la presentazione
  - un cliente con email non verificata
- Da 15 a 30 clienti casuali.
- Ordini distribuiti sugli ultimi 6 mesi, così fatturato e grafici della dashboard hanno valori sensati.

## Mappa delle API del negozio

Prefisso comune: `/api/v1`. Autenticazione, account e sicurezza sono nella skill `account-security`.

**Pubbliche**

| Metodo | Percorso | Descrizione |
|---|---|---|
| GET | /health | stato del servizio |
| GET | /categories | elenco delle cinque categorie con descrizione |
| GET | /categories/{category:slug} | dettaglio categoria |
| GET | /products | elenco con `search`, `category`, `min_price`, `max_price`, `in_stock`, `new`, `sort`, `page`, `per_page`; `meta.price_range` per lo slider |
| GET | /products/{product:slug} | dettaglio prodotto con descrizione, scheda tecnica e immagini |
| GET | /products/{product:slug}/related | fino a 4 prodotti correlati della stessa categoria, disponibili |

**Cliente autenticato**

| Metodo | Percorso | Descrizione |
|---|---|---|
| GET, POST, PATCH, DELETE | /addresses | gestione indirizzi (`apiResource`) |
| GET | /cart | carrello con totali |
| POST | /cart/items | aggiunta prodotto |
| PATCH | /cart/items/{cartItem} | modifica quantità |
| DELETE | /cart/items/{cartItem} | rimozione riga |
| DELETE | /cart | svuotamento carrello |
| POST | /checkout | creazione ordine e pagamento (richiede email verificata) |
| GET | /orders | storico ordini |
| GET | /orders/{order} | dettaglio ordine |
| POST | /orders/{order}/cancel | annullamento da parte del cliente |

**Admin** (prefisso `/admin`, ruolo `admin`)

| Metodo | Percorso | Descrizione |
|---|---|---|
| GET | /admin/dashboard | fatturato del mese, ordini per stato, prodotti con scorte basse (`low_stock_threshold`), ultimi ordini |
| GET, POST, PATCH, DELETE | /admin/products | gestione prodotti (`apiResource`), con `category_id`, `short_description` e `specifications` |
| POST | /admin/products/{product}/images | caricamento immagini |
| DELETE | /admin/products/{product}/images/{image} | rimozione immagine |
| GET, POST, PATCH, DELETE | /admin/categories | gestione categorie (`apiResource`) |
| GET | /admin/orders | elenco ordini con filtro per stato |
| GET | /admin/orders/{order} | dettaglio ordine |
| PATCH | /admin/orders/{order}/status | cambio di stato |

### Forma della `ProductResource`

Elenco e correlati:

```json
{
  "id": 12,
  "name": "Diffusore Eco",
  "slug": "diffusore-eco",
  "price": 124900,
  "is_new": false,
  "availability": { "status": "low_stock", "label": "Ultimi 2 pezzi" },
  "category": { "name": "Audio", "slug": "audio" },
  "cover": { "url": "...", "alt": "..." }
}
```

Il dettaglio aggiunge `short_description`, `description`, `specifications` e `images`.
