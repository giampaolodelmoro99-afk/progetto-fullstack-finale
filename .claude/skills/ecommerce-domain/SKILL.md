---
name: ecommerce-domain
description: Regole di business dell'e-commerce — entità e colonne, importi in centesimi, snapshot degli ordini, gestione dello stock con lock, carrello, checkout, dati della carta, indirizzi solo in Italia, stati dell'ordine, cronologia degli stati e visibilità per il cliente, pagamenti simulati e rimborsi, annullamento, novità e disponibilità calcolate, impostazioni del negozio esposte al frontend, testi dell'assistenza, conservazione degli ordini degli account eliminati, area admin (dashboard, prodotti, immagini, categorie, ordini), dati demo dei seeder e mappa delle API. Usala per modelli, migration, factory, seeder, carrello, checkout, ordini, pagamenti, admin e ogni schermata che mostra questi dati.
---

# Dominio e-commerce — regole di business

Fonti delle decisioni sul negozio:
- mockup del Design A (negozio): `design/mockups/01-negozio.pdf`
- mockup del Design B (carrello, checkout, conferma): `design/mockups/02-acquisto.pdf`
- mockup del Design D (pannello di amministrazione): `design/mockups/04-admin.pdf`
- parole fisse per stati e disponibilità, nomi dei prodotti: `design/design-system/voice-and-content.md`

Dove un mockup e una decisione approvata divergono vale la decisione; l'elenco delle divergenze è nella skill `mui-design-system`.

## Entità del negozio

Le tabelle dell'account e della sicurezza sono descritte nella skill `account-security`. Tipi, nullable, default, chiavi, indici e comportamento in cancellazione di tutte le tabelle sono in `docs/database/er-diagram.md`, insieme alle decisioni che hanno risolto le incoerenze tra le skill.

| Tabella | Colonne principali | Note |
|---|---|---|
| users | name, email, password, role, email_verified_at, anonymized_at | `role`: `customer` o `admin`; `anonymized_at` valorizzata quando il cliente elimina l'account (skill `account-security`) |
| addresses | user_id, full_name, street, city, postal_code, province, country, phone, is_default | un solo indirizzo predefinito per utente, garantito dall'Action nella stessa transazione (MySQL non ha indici unici parziali); `country` sempre `IT` |
| categories | name, slug, description | gestite dall'admin, nessuna sottocategoria; `slug` unico, generato dal nome alla creazione e mai più modificabile |
| products | category_id, name, slug, sku, short_description, description, specifications (JSON), price, stock, is_active, created_at, updated_at, deleted_at | una sola categoria per prodotto, `slug` e `sku` unici (anche rispetto ai soft delete), `price` in centesimi, soft delete |
| product_images | product_id, path, alt, position | la prima per `position` è la copertina; indice su (product_id, position) non unico, per non bloccare i riordini |
| carts | user_id (unico) | un carrello per utente |
| cart_items | cart_id, product_id, quantity | unico su (cart_id, product_id); nessun prezzo salvato |
| orders | user_id, number, status, subtotal, shipping_cost, total, shipping_address (JSON), terms_accepted_at, paid_at, shipped_at, delivered_at, cancelled_at | importi in centesimi; le colonne `*_at` servono a filtri e statistiche; indice su (status, paid_at) |
| order_items | order_id, product_id (nullable, `nullOnDelete`), product_name, product_sku, unit_price, quantity, line_total | snapshot al momento dell'acquisto |
| order_status_changes | order_id, from_status (nullable), to_status, changed_by (nullable), reason (nullable), created_at | una riga per ogni cambio di stato; `changed_by` null = sistema; nessun `updated_at` |
| payments | order_id, provider, provider_reference (nullable), amount, status, card_last_four | una riga per tentativo di addebito, riuscito o rifiutato; relazione 1:N con gli ordini, ma nel flusso attuale ogni ordine ha un solo addebito, perché ogni tentativo crea un ordine nuovo; mai dati completi della carta |

### Colonne dei prodotti

- `category_id`: obbligatoria, `foreignId()->constrained()` con `restrictOnDelete()`, coerente con la regola che una categoria con prodotti non si elimina.
- `slug`: generato dal nome alla creazione e unico anche rispetto ai prodotti eliminati con soft delete, perché è la chiave del catalogo pubblico (`{product:slug}`). In caso di collisione si aggiunge un suffisso numerico (`-2`, `-3`…), troncando prima la base quanto serve perché lo slug resti entro 255 caratteri. Il nome del prodotto non deve essere unico.
- `sku`: obbligatorio, solo lettere maiuscole, numeri e trattini (`^[A-Z0-9-]+$`), unico anche rispetto ai prodotti eliminati con soft delete, perché lo snapshot degli ordini lo conserva e deve restare univoco.
- `short_description`: obbligatoria, frase breve mostrata sotto il nome nella pagina del prodotto, massimo 160 caratteri.
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
- `availability`: oggetto `{ "status": "...", "label": "..." }` calcolato dallo stock, per i clienti:

| Stock | `status` | `label` |
|---|---|---|
| 0 | `out_of_stock` | Esaurito |
| 1 | `low_stock` | Ultimo pezzo |
| da 2 a `config('shop.last_units_threshold')` | `low_stock` | Ultimi N pezzi |
| oltre la soglia | `available` | Disponibile |

- `max_quantity`: `min(stock, config('shop.max_quantity_per_item'))`, usato dal selettore di quantità nella pagina prodotto e nel carrello.
- `stock_status` (solo `Admin\ProductResource`): stesso formato di `availability`, ma con la soglia del magazzino `low_stock_threshold`:

| Stock | `status` | `label` |
|---|---|---|
| 0 | `out_of_stock` | Esaurito |
| 1 | `low_stock` | Ultimo pezzo |
| da 2 a `config('shop.low_stock_threshold')` | `low_stock` | Ultimi N pezzi |
| oltre la soglia | `available` | Disponibile |

## Configurazione

`config/shop.php`, importi in centesimi:

| Chiave | Valore | Uso |
|---|---|---|
| `shipping_cost` | 690 | costo fisso di spedizione |
| `free_shipping_threshold` | 9900 | subtotale da cui la spedizione è gratuita |
| `new_arrival_days` | 30 | finestra delle novità (`is_new`, filtro `new`) |
| `last_units_threshold` | 3 | soglia degli "Ultimi N pezzi" mostrata ai clienti |
| `low_stock_threshold` | 5 | soglia delle scorte basse nell'area admin |
| `max_quantity_per_item` | 10 | quantità massima di un prodotto in una riga del carrello |

`last_units_threshold` e `low_stock_threshold` sono distinte: la prima riguarda la comunicazione al cliente, la seconda il riordino in magazzino.

### Impostazioni esposte al frontend

Il frontend non scrive mai a mano importi o soglie della configurazione: li legge da due endpoint.

`GET /shop/settings` (pubblico), usato per tutti i testi sulla spedizione del negozio (rassicurazioni, pagina prodotto, carrello, checkout, pagine statiche):

```json
{ "data": { "shipping_cost": 690, "free_shipping_threshold": 9900 } }
```

`GET /admin/settings` (admin), usato da dashboard, elenco prodotti e form prodotto; contiene gli stessi valori più la soglia delle scorte basse:

```json
{ "data": { "shipping_cost": 690, "free_shipping_threshold": 9900, "low_stock_threshold": 5 } }
```

### Testi dell'assistenza

I testi della pagina `/assistenza` hanno una sola fonte nel backend, perché li usano sia la pagina sia l'assistente AI (skill `ai-assistant`). Si realizzano nella Fase 4, insieme alle impostazioni del negozio.

- I testi stanno in un file del backend (es. `resources/content/support.php`), in italiano e secondo `voice-and-content.md`. Consegna in 24/48 ore, reso entro 30 giorni e garanzia di 2 anni sono testi fissi; costo di spedizione e soglia della spedizione gratuita si inseriscono da `config/shop.php`, formattati come prezzo (6,90 €, 99,00 €), mai scritti a mano.
- `GET /shop/support` (pubblico):

```json
{
  "data": [
    { "id": "spedizioni", "title": "Spedizioni e consegne", "paragraphs": ["…"] },
    { "id": "resi", "title": "Resi e rimborsi", "paragraphs": ["…"] },
    { "id": "garanzia", "title": "Garanzia", "paragraphs": ["…"] },
    { "id": "contatti", "title": "Contatti", "paragraphs": ["…"] }
  ]
}
```

- Gli `id` coincidono con le ancore della pagina (`#spedizioni`, `#resi`, `#garanzia`, `#contatti`) e i titoli con le voci della colonna Assistenza del footer.
- L'assistente riceve le stesse sezioni come dati, a ogni richiesta, dalla stessa classe che costruisce la risposta dell'endpoint.
- Le note legali (`/note-legali`) restano testi fissi nel frontend.

## Regole

1. **Importi in centesimi**, mai float: `unsignedInteger` per `products.price`, `order_items.unit_price` e `orders.shipping_cost`; `unsignedBigInteger` per le somme `order_items.line_total`, `orders.subtotal`, `orders.total` e `payments.amount`. Il prezzo di un prodotto va da 1 a 999999 centesimi (9.999,99 €). I prezzi sono IVA inclusa: nessun calcolo delle imposte.
2. **Il server non si fida del client.** Prezzi, totali e disponibilità si ricalcolano sempre dal database; il client invia solo id e quantità.
3. **Snapshot all'acquisto.** `order_items` copia nome, SKU e prezzo; `orders.shipping_address` copia l'indirizzo. Modificare o eliminare prodotti e indirizzi non altera gli ordini esistenti.
4. **Stock.** Si verifica, si decrementa e si ripristina sempre in transazione con `lockForUpdate()` sui prodotti coinvolti. Se è insufficiente si lancia `InsufficientStockException` (409) con l'elenco dei prodotti problematici. Lo stock torna disponibile quando un ordine viene annullato, sia per pagamento rifiutato sia per annullamento dopo il pagamento.
5. **Catalogo pubblico:** solo prodotti `is_active` e non eliminati. I prodotti con stock 0 sono visibili ma non acquistabili.
6. **Carrello:** solo per utenti autenticati, con le regole della sezione "Carrello".
7. **Checkout solo con email verificata** (middleware `verified`).
8. **Spedizione:** solo in Italia. Costo fisso `shipping_cost` (6,90 €), gratuita quando il subtotale raggiunge `free_shipping_threshold` (99,00 €).
9. **Numero d'ordine** leggibile e univoco nel formato `ORD-{anno}-{id a 6 cifre}`, es. `ORD-2026-000042`. Dipende dall'id, quindi `orders.number` è nullable e unico, e `PlaceOrderAction` lo scrive subito dopo l'insert, nella stessa transazione.
10. **Ruoli:** la registrazione crea solo `customer`; l'admin nasce dal seeder.
11. **Categorie:** gestite dall'admin, senza sottocategorie. Ogni prodotto appartiene a una sola categoria. Lo slug si genera dal nome alla creazione e non si modifica più. Una categoria collegata a prodotti, **compresi quelli eliminati con soft delete**, non si elimina (409): la FK `restrictOnDelete` lo impedirebbe comunque, ma con un errore 500.
12. **Scorte basse:** prodotti attivi e non eliminati con stock minore o uguale a `low_stock_threshold`, esauriti compresi.
13. **Novità:** un prodotto è nuovo se creato negli ultimi `new_arrival_days` giorni; lo stesso criterio vale per `is_new` e per il filtro `new=1`.
14. **Disponibilità:** status ed etichetta seguono la tabella dei valori calcolati, con le parole fisse di `voice-and-content.md`.
15. **Visibilità degli ordini:** il cliente vede solo i propri ordini con `paid_at` valorizzato; l'admin li vede tutti (sezione "Ordini").
16. **Cronologia:** ogni cambio di stato, compresa la creazione dell'ordine, scrive una riga in `order_status_changes` (sezione "Cronologia degli stati").
17. **Periodo "mese corrente":** si calcola nel fuso dell'applicazione (`Europe/Rome`), dal primo giorno del mese alle 00:00 all'ultimo alle 23:59:59.
18. **Conservazione:** ordini, righe, cronologia e pagamenti non si cancellano mai, nemmeno quando il cliente elimina l'account: l'utente viene anonimizzato e i suoi ordini restano per l'obbligo di conservazione di 10 anni (motivazione nella skill `account-security`, "Eliminazione dell'account").

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
- Oltre a `data`, `links` e `meta` della paginazione, `meta.price_range` contiene `{ "min": ..., "max": ... }` in centesimi: il prezzo minimo e massimo dei prodotti attivi della categoria filtrata, o di tutto il catalogo se `category` manca. Gli altri filtri non lo influenzano, così i limiti dello slider del prezzo restano fermi mentre l'utente lo muove. Per una categoria senza prodotti attivi `min` e `max` valgono `null`.
- "Potrebbero piacerti" nel carrello vuoto usa lo stesso endpoint: `GET /products?in_stock=1&sort=newest&per_page=4`.

### Categorie (`GET /categories`)

- Tutte le categorie, anche quelle senza prodotti (es. "Viaggio" nei dati demo), ordinate per `id`: le nuove compaiono in fondo.
- Menu, footer e categorie in evidenza del negozio le leggono da qui, non da un elenco fisso.
- L'indirizzo del catalogo di una categoria è `/products?category={slug}`.

### Prodotti correlati (`GET /products/{product:slug}/related`)

- Fino a 4 prodotti attivi della stessa categoria, con stock maggiore di 0, escluso il prodotto corrente.
- Ordinati per novità (`created_at` decrescente).
- Se ce ne sono meno di 4 se ne restituiscono meno: non si completa con altre categorie.

## Carrello

### Regole

- **Riga acquistabile:** prodotto attivo, non eliminato e con stock maggiore di 0.
- **Prezzi attuali:** il carrello mostra e somma sempre il prezzo attuale del prodotto. `cart_items` non salva prezzi; lo snapshot avviene solo alla creazione dell'ordine.
- **Conteggio articoli** (titolo "Il tuo carrello" e badge nell'AppBar): somma delle quantità di tutte le righe, righe non acquistabili comprese.
- **Totali:** subtotale, spedizione e totale si calcolano solo sulle righe acquistabili. Una riga acquistabile con quantità superiore allo stock resta nei totali con la sua quantità, ma blocca il checkout.
- **Quantità:** tra 1 e `max_quantity` del prodotto. Aggiungere un prodotto già presente somma la quantità; se la somma supera `max_quantity` la richiesta risponde 422 con un messaggio che indica il massimo, senza ridurre la quantità in silenzio.
- **Checkout bloccato** quando il carrello è vuoto, quando contiene righe non acquistabili o righe con quantità superiore allo stock.

### Forma della `CartResource`

```json
{
  "items": [
    {
      "id": 7,
      "quantity": 2,
      "max_quantity": 1,
      "exceeds_stock": true,
      "is_purchasable": true,
      "unit_price": 5900,
      "line_total": 11800,
      "product": {
        "name": "Caricatore Brezza 65 W",
        "slug": "caricatore-brezza-65-w",
        "category": { "name": "Ricarica ed energia", "slug": "ricarica-ed-energia" },
        "cover": { "url": "...", "alt": "..." },
        "availability": { "status": "low_stock", "label": "Ultimo pezzo" }
      }
    }
  ],
  "summary": {
    "items_count": 5,
    "subtotal": 49600,
    "shipping_cost": 0,
    "total": 49600,
    "free_shipping_threshold": 9900,
    "free_shipping_remaining": 0
  },
  "can_checkout": false
}
```

- `exceeds_stock`: `quantity` maggiore dello stock attuale.
- `is_purchasable`: vedi "Riga acquistabile"; per le righe non acquistabili `line_total` è `null`.
- `free_shipping_remaining`: quanto manca alla spedizione gratuita, 0 se raggiunta; alimenta la barra "Ti mancano … per la spedizione gratuita".
- `can_checkout`: calcolato dal backend; il frontend lo usa per disabilitare "Procedi al checkout" e "Paga", ma il backend ricontrolla sempre al checkout.

## Indirizzi

- Spedizione solo in Italia: `country` vale sempre `IT`, lo imposta il server e non si legge dalla richiesta. Nel form il campo "Paese" mostra "Italia" e non è modificabile.
- Validazione (`StoreAddressRequest` e `UpdateAddressRequest`):

| Campo | Regole |
|---|---|
| `full_name` | obbligatorio, massimo 255 caratteri |
| `street` | obbligatorio, massimo 255 caratteri |
| `city` | obbligatorio, massimo 100 caratteri |
| `postal_code` | obbligatorio, esattamente 5 cifre (`^\d{5}$`) |
| `province` | obbligatoria, sigla di 2 lettere maiuscole (`^[A-Z]{2}$`) |
| `phone` | obbligatorio, massimo 20 caratteri, solo cifre, spazi e un `+` iniziale (`^\+?[0-9 ]+$`) |
| `is_default` | booleano facoltativo |

## Checkout (`POST /checkout`)

### Richiesta (`PlaceOrderRequest`)

```json
{
  "address_id": 12,
  "payment": {
    "holder": "Giulia Ferri",
    "number": "4242 4242 4242 4242",
    "expiry": "08/29",
    "cvc": "123"
  },
  "terms_accepted": true
}
```

| Campo | Regole |
|---|---|
| `address_id` | obbligatorio, indirizzo esistente dell'utente |
| `payment.holder` | obbligatorio, massimo 255 caratteri |
| `payment.number` | obbligatorio; spazi ammessi e rimossi prima della validazione; da 13 a 19 cifre; controllo di Luhn |
| `payment.expiry` | obbligatoria, formato `MM/AA`, mese tra 01 e 12, non passata (il mese corrente è valido) |
| `payment.cvc` | obbligatorio, esattamente 3 cifre |
| `terms_accepted` | regola `accepted` |

### Flusso (`PlaceOrderAction`)

1. Verifica che il carrello non sia vuoto e che l'indirizzo scelto appartenga all'utente.
2. Verifica che il carrello sia acquistabile: nessuna riga non acquistabile e nessuna quantità oltre lo stock. Altrimenti `InsufficientStockException` (409) con l'elenco `[{ "product": "...", "requested": 2, "available": 1 }]`; prodotti esauriti, disattivati o eliminati hanno `available: 0`.
3. Transazione: lock dei prodotti, stesso controllo della disponibilità sotto lock, creazione dell'ordine `pending` con righe, snapshot dell'indirizzo e `terms_accepted_at`, riga di cronologia `null → pending`, decremento dello stock.
4. Fuori dalla transazione: addebito tramite `PaymentGateway`.
5. Pagamento riuscito, in `DB::transaction()` con `lockForUpdate()` sull'ordine: riga in `payments` con `status` `succeeded` e le sole ultime 4 cifre, transizione `pending → paid` (valorizza `paid_at`), svuotamento del carrello. Dopo il commit, evento `OrderPlaced` che invia l'email di conferma: è l'unica email del pagamento riuscito. Risposta 201 con la `OrderResource`.
6. Pagamento rifiutato, in `DB::transaction()` con `lockForUpdate()` su ordine e prodotti: riga in `payments` con `status` `failed`, `card_last_four` e importo; transizione `pending → cancelled` senza autore e con `reason` uguale al motivo restituito dal gateway (es. «Carta rifiutata dalla banca»); ripristino dello stock di tutte le righe con `product_id` valorizzato, compresi i prodotti eliminati con soft delete nel frattempo (`withTrashed()`, anche nel lock). Poi: carrello intatto; `PaymentFailedException` (402) con messaggio chiaro. L'ordine resta nel database come tentativo fallito, con stato Annullato e senza `paid_at`: lo vede solo l'admin. Nessuna email.

### Dati della carta

- Arrivano solo con `POST /checkout`, non si salvano e non si scrivono mai in log, eccezioni o eventi.
- Nel database si conservano solo le ultime 4 cifre in `payments.card_last_four`.
- `payment.number` e `payment.cvc` sono in `dontFlash` (skill `laravel-api`).
- Nel frontend vivono solo nello stato in memoria del checkout (skill `react-architecture`).

## Ordini

### Visibilità

- **Cliente:** vede solo i propri ordini con `paid_at` valorizzato, sia in `GET /orders` sia in `GET /orders/{order}`. Per gli altri (ordini di altri utenti, tentativi di pagamento falliti, ordini `pending` in corso) `GET /orders/{order}` risponde **404**: la Policy usa `Response::denyAsNotFound()`, così non si rivela l'esistenza dell'ordine. L'elenco usa uno scope `visibleToCustomer` (`paid_at` non nullo).
- Un ordine annullato **dopo** il pagamento ha `paid_at` ed è visibile al cliente con stato Annullato.
- **Admin:** vede tutti gli ordini, compresi i tentativi falliti, e può filtrarli con `failed_payment=1` (stato `cancelled` e `paid_at` nullo).

### Stati dell'ordine

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
| pending | cancelled | sistema, a pagamento rifiutato |
| paid | shipped | admin |
| paid | cancelled | admin o cliente (rimborso e ripristino dello stock) |
| shipped | delivered | admin |

`delivered` e `cancelled` sono stati finali. Una transizione non consentita lancia `InvalidOrderTransitionException` (422). Ogni transizione passa da `ChangeOrderStatusAction` (skill `laravel-api`), che valorizza il timestamp corrispondente e scrive la riga di cronologia; l'unica riga scritta altrove è quella della creazione (`null → pending`), in `PlaceOrderAction`.

`canTransitionTo()` dice solo se una coppia di stati è ammessa, non chi può farla: le transizioni del sistema (`pending → paid`, `pending → cancelled`) le esegue solo `PlaceOrderAction`. Cliente e admin agiscono solo sugli ordini `paid` o `shipped` secondo la colonna "Chi".

Notifiche email al cliente, elenco chiuso:

| Transizione | Email |
|---|---|
| pending → paid | nessuna notifica di transizione: la conferma d'ordine arriva da `OrderPlaced` |
| pending → cancelled | nessuna: il cliente ha già la risposta 402 |
| paid → shipped | ordine spedito |
| shipped → delivered | ordine consegnato |
| paid → cancelled | ordine annullato con rimborso |

### Cronologia degli stati

- Ogni riga di `order_status_changes` registra `from_status` (null per la creazione), `to_status`, l'autore in `changed_by` (null = sistema) e un `reason` facoltativo, oggi usato solo per il motivo del pagamento rifiutato.
- Le colonne `*_at` di `orders` restano e si valorizzano insieme alla riga: servono a filtri e statistiche, la cronologia serve a mostrare la storia.
- Ordine delle righe: `created_at` crescente, poi `id`.
- La descrizione la compone il backend. La variante del cliente non contiene mai l'autore né il `reason`; quella admin aggiunge «da {nome}» quando c'è un autore.

| Transizione | Autore | Descrizione (cliente e admin) | Aggiunta nella variante admin |
|---|---|---|---|
| — → pending | sistema | Ordine ricevuto, in attesa del pagamento | — |
| pending → paid | sistema | Pagamento con carta riuscito | — |
| pending → cancelled | sistema | Pagamento non riuscito: {reason} | — (riga visibile solo all'admin) |
| paid → shipped | admin | Segnato come spedito | « da {nome}» |
| shipped → delivered | admin | Segnato come consegnato | « da {nome}» |
| paid → cancelled | admin o cliente | Annullato e rimborsato | « da {nome}» |

### Annullamento di un ordine pagato (`CancelOrderAction`)

Una sola Action, usata sia da `POST /orders/{order}/cancel` (cliente) sia da `PATCH /admin/orders/{order}/status` con `cancelled` (admin), riceve l'ordine e l'autore ed esegue i passi in quest'ordine:

1. Controlla che l'ordine sia `paid` e abbia un pagamento `succeeded`; altrimenti `InvalidOrderTransitionException` (422) prima di qualunque effetto, senza chiamare il gateway. Un ordine `pending`, `shipped`, `delivered` o già `cancelled` non si annulla da qui.
2. Rimborso del pagamento `succeeded` tramite `PaymentGateway::refund()`, **fuori dalla transazione** perché è una chiamata a un servizio esterno. Il gateway riceve l'id del pagamento come chiave di idempotenza, così una seconda richiesta di rimborso dello stesso pagamento non rimborsa due volte.
3. Se il rimborso fallisce: `RefundFailedException` (502) e **nessun cambiamento**. Stato dell'ordine, scorte e pagamento restano invariati e non si scrive nessuna riga di cronologia.
4. Se il rimborso riesce, in una transazione con `lockForUpdate()` su ordine, pagamento e prodotti: si ricontrolla sotto lock che l'ordine sia ancora `paid` e il pagamento ancora `succeeded` (se nel frattempo un'altra richiesta l'ha annullato, si esce senza effetti e si restituisce l'ordine attuale); poi ripristino delle scorte di tutte le righe con `product_id` valorizzato, compresi i prodotti eliminati con soft delete (`withTrashed()`), pagamento a `refunded`, transizione `paid → cancelled` tramite `ChangeOrderStatusAction` con l'autore (cliente o admin). La notifica al cliente parte dopo il commit.

Limite residuo, accettato: con due richieste concorrenti entrambe chiamano il gateway prima del lock; la chiave di idempotenza evita il doppio rimborso, il ricontrollo sotto lock evita la doppia transizione.

### Forma della `OrderResource` (cliente)

```json
{
  "id": 42,
  "number": "ORD-2026-000042",
  "status": { "value": "paid", "label": "Pagato" },
  "created_at": "2026-09-28T18:41:00+02:00",
  "paid_at": "2026-09-28T18:42:00+02:00",
  "subtotal": 49600,
  "shipping_cost": 0,
  "total": 49600,
  "shipping_address": { "full_name": "Giulia Ferri", "street": "Via San Martino 42", "postal_code": "56125", "city": "Pisa", "province": "PI", "country": "IT", "phone": "+39 333 123 4567" },
  "payment": { "card_last_four": "4242" },
  "items": [
    { "product_name": "Cuffie Aria", "product_sku": "CDS-AUD-001", "unit_price": 34900, "quantity": 1, "line_total": 34900, "cover": { "url": "...", "alt": "..." } }
  ],
  "status_history": [
    { "status": { "value": "pending", "label": "In attesa" }, "description": "Ordine ricevuto, in attesa del pagamento", "created_at": "2026-09-28T18:41:00+02:00" },
    { "status": { "value": "paid", "label": "Pagato" }, "description": "Pagamento con carta riuscito", "created_at": "2026-09-28T18:42:00+02:00" }
  ]
}
```

- `items[].cover`: copertina attuale del prodotto se non è eliminato (né con soft delete né fisicamente), altrimenti `null`; il frontend mostra un'immagine di ripiego. Nome, SKU e prezzi vengono sempre dallo snapshot.
- `payment` compare solo se esiste un pagamento `succeeded` o `refunded`; mai altri dati della carta.
- `status_history`: senza `changed_by` e senza `reason`.
- `can_cancel`: calcolato dal backend solo sullo stato (`status` `paid`), usato dal frontend per mostrare "Annulla l'ordine". Un ordine `paid` ha sempre un pagamento `succeeded`; `CancelOrderAction` lo ricontrolla comunque.

`POST /orders/{order}/cancel` è autorizzato da `OrderPolicy::cancel` con lo stesso criterio di `view`: ordini di altri utenti e ordini senza `paid_at` → 404 con `denyAsNotFound()`; ordine proprio non annullabile → 422.

## Pagamenti

- Interfaccia `App\Contracts\PaymentGateway` con `charge(Order $order, array $paymentData): PaymentResult` e `refund(Payment $payment): PaymentResult`. `PaymentResult` contiene l'esito, il riferimento del provider e, se l'esito è negativo, il motivo in italiano.
- Enum `PaymentStatus`:

| Valore | Etichetta |
|---|---|
| `succeeded` | Riuscito |
| `failed` | Non riuscito |
| `refunded` | Rimborsato |

- `FakePaymentGateway` è il driver predefinito (`PAYMENT_DRIVER=fake`): approva ogni carta con formato valido, tranne quelle che terminano con `0002`, che vengono rifiutate con il motivo «Carta rifiutata dalla banca» per poter mostrare il caso di errore nella demo. Il rimborso riesce sempre; i test del rimborso fallito sostituiscono il gateway tramite il container.
- Carte di test, documentate nel README:

| Numero | Esito |
|---|---|
| 4242 4242 4242 4242 | approvata |
| 4000 0000 0000 0002 | rifiutata |

  Entrambe superano il controllo di Luhn, quindi il rifiuto arriva dal gateway (402) e non dalla validazione (422).
- Numero completo, scadenza e CVC non vengono mai salvati né scritti nei log; si conservano solo le ultime quattro cifre.
- Un driver Stripe in modalità test è un'estensione opzionale.

## Area admin

Riferimento visivo: `design/mockups/04-admin.pdf`. Tutte le rotte hanno prefisso `/admin` e richiedono il ruolo `admin`.

### Clienti eliminati

Gli ordini di un cliente che ha eliminato l'account (`users.anonymized_at` valorizzata) restano nell'area admin con tutti i loro dati, compreso lo snapshot dell'indirizzo:

- `customer` vale `{ "name": "Cliente eliminato", "email": null }` nell'elenco ordini, nel dettaglio e in `latest_orders` della dashboard. Il valore si calcola in un solo punto (un metodo del modello `User` o una Resource dedicata del cliente), non in ogni Resource.
- Nella cronologia admin, una transizione fatta da quel cliente (annullamento) mostra «da Cliente eliminato».
- La ricerca per email dell'elenco ordini non trova gli account anonimizzati: la loro email è fittizia.
- Il frontend mostra `email` null senza la riga dell'email.
- I due testi sono voluti: «Utente eliminato» è il valore salvato in `users.name`, neutro rispetto al ruolo; «Cliente eliminato» è l'etichetta che l'area admin mostra al posto del cliente. Le Resource admin usano sempre l'etichetta, mai il nome salvato.

### Dashboard (`GET /admin/dashboard`)

Indicatori ed elenchi contano solo gli ordini **pagati almeno una volta** (`paid_at` valorizzato): i tentativi falliti e gli ordini `pending` non entrano mai.

| Dato | Definizione |
|---|---|
| `revenue_month` | somma di `total` degli ordini con `paid_at` nel mese corrente, esclusi quelli ora `cancelled` |
| `orders_month` | numero di ordini con `paid_at` nel mese corrente, in qualunque stato attuale |
| `orders_to_ship` | numero di ordini in stato `paid`, senza limite di periodo |
| `low_stock_count` | numero di prodotti con scorte basse (regola 12) |
| `orders_by_status` | ordini con `paid_at` nel mese corrente raggruppati per stato attuale (`paid`, `shipped`, `delivered`, `cancelled`); la somma è uguale a `orders_month`. Non c'è `pending`: un ordine con `paid_at` non è mai in attesa. Il link di ogni stato apre l'elenco ordini con `status` e `paid_month` del mese mostrato, così i risultati coincidono con il conteggio |
| `latest_orders` | tra gli ordini con `paid_at` valorizzato, i 5 più recenti per `created_at` decrescente, poi `id` |
| `low_stock_products` | al massimo 5 prodotti con scorte basse, per stock crescente e poi nome, con `stock_status` e copertina |

```json
{
  "data": {
    "period": { "month": "2026-09" },
    "revenue_month": 874200,
    "orders_month": 24,
    "orders_to_ship": 6,
    "low_stock_count": 4,
    "orders_by_status": [
      { "status": { "value": "paid", "label": "Pagato" }, "count": 6 },
      { "status": { "value": "shipped", "label": "Spedito" }, "count": 5 },
      { "status": { "value": "delivered", "label": "Consegnato" }, "count": 11 },
      { "status": { "value": "cancelled", "label": "Annullato" }, "count": 2 }
    ],
    "latest_orders": [
      { "id": 47, "number": "ORD-2026-000047", "customer": { "name": "Marco Bellini" }, "created_at": "...", "total": 69800, "status": { "value": "paid", "label": "Pagato" } }
    ],
    "low_stock_products": [
      { "id": 31, "name": "Radio Onda Portatile", "stock_status": { "status": "out_of_stock", "label": "Esaurito" }, "cover": { "url": "...", "alt": "..." } }
    ]
  }
}
```

### Prodotti

`GET /admin/products`, filtri validati da `Admin\IndexProductRequest`:

| Parametro | Valori | Effetto |
|---|---|---|
| `search` | testo | cerca in nome e SKU |
| `category` | slug | prodotti della categoria |
| `status` | `active`, `inactive` | prodotti visibili o disattivati nel negozio |
| `page`, `per_page` | `per_page` tra 10, 25 e 50 | paginazione |

- Comprende i prodotti disattivati, non quelli eliminati. Ordine per `sku`, poi `id`, come nel mockup.
- `Admin\ProductResource`: `id`, `name`, `slug`, `sku`, `category` (`id`, `name`, `slug`), `short_description`, `description`, `specifications`, `price`, `stock`, `stock_status`, `is_active`, `cover`, `images` (`id`, `url`, `alt`, `position`, solo nel dettaglio), `updated_at`.

Validazione di `StoreProductRequest` e `UpdateProductRequest`:

| Campo | Regole |
|---|---|
| `category_id` | obbligatorio, categoria esistente |
| `name` | obbligatorio, massimo 255 caratteri |
| `sku` | obbligatorio, massimo 64 caratteri, `^[A-Z0-9-]+$` («Usa solo lettere maiuscole, numeri e trattini»), unico tra tutti i prodotti compresi gli eliminati, con il messaggio «Questo SKU è già in uso» (nell'update si ignora il prodotto stesso) |
| `short_description` | obbligatoria, massimo 160 caratteri |
| `description` | facoltativa, massimo 5000 caratteri |
| `specifications` | presente, array di al massimo 20 coppie `label` (massimo 64) e `value` (massimo 255), entrambe obbligatorie |
| `price` | obbligatorio, intero in centesimi, da 1 a 999999 (9.999,99 €, coerente con le 4 cifre intere del form); il frontend converte l'importo in euro scritto con la virgola |
| `stock` | obbligatorio, intero, da 0 a 999999 |
| `is_active` | obbligatorio, booleano |
| `images` | solo nell'update, facoltativo: array con **tutte** le immagini del prodotto nell'ordine voluto, ognuna con `id` (immagine di questo prodotto) e `alt` (obbligatorio, massimo 255). Id mancanti, duplicati o di un altro prodotto → 422 |

- Slug del prodotto: sezione "Colonne dei prodotti".
- Eliminazione: soft delete. Il prodotto sparisce da catalogo e negozio; gli ordini che lo contengono restano invariati grazie allo snapshot.

### Immagini dei prodotti

- `POST /admin/products/{product}/images`, multipart con `images[]`: da 1 a 10 file per richiesta, ognuno JPG, PNG o WebP (`mimes:jpg,jpeg,png,webp`) e al massimo 5 MB (`max:5120`). Le immagini si accodano dopo l'ultima `position`; `alt` iniziale uguale al nome del prodotto. Risposta 201 con l'elenco aggiornato delle immagini.
- `DELETE /admin/products/{product}/images/{image}`: elimina subito il file e la riga, e ricompatta le posizioni. 204.
- Ordine e testi alternativi si salvano con `PATCH /admin/products/{product}` tramite `images`.
- Le immagini si aggiungono solo a un prodotto già salvato: il form "Nuovo prodotto" non le accetta.

### Categorie

- `GET /admin/categories`: tutte, ordinate per `id`, con `Admin\CategoryResource`: `id`, `name`, `slug`, `description`, `products_count` (prodotti non eliminati, attivi e disattivati) e `can_delete` (false se esistono prodotti collegati, compresi gli eliminati). Entrambi i valori si caricano nella query con `withCount` (il secondo su `products` con `withTrashed()`), senza una query per riga.
- Nell'update, il controllo dello slug derivato ignora la categoria stessa: rinominare "Casa smart" in "Casa Smart" è valido.
- `POST /admin/categories` e `PATCH /admin/categories/{category}`: `name` obbligatorio, massimo 50 caratteri, unico, e lo slug che ne deriva non deve essere già usato da un'altra categoria (422 su `name`, «Esiste già una categoria con questo nome»); `description` facoltativa, massimo 255 caratteri. `slug` non fa parte delle regole: si genera alla creazione e un `PATCH` che lo contiene lo ignora.
- `DELETE /admin/categories/{category}`: 204, oppure 409 se `can_delete` è false.

### Ordini

`GET /admin/orders`, filtri validati da `Admin\IndexOrderRequest`:

| Parametro | Valori | Effetto |
|---|---|---|
| `search` | testo | numero d'ordine o email del cliente che contengono il testo |
| `status` | valore di `OrderStatus` | ordini nello stato |
| `failed_payment` | `1` | solo i tentativi falliti (`cancelled` e `paid_at` nullo) |
| `paid_month` | `AAAA-MM` (`date_format:Y-m`, es. `2026-09`) | solo ordini con `paid_at` in quel mese, con i limiti calcolati nel fuso dell'applicazione (regola 17), come nella dashboard |
| `page`, `per_page` | `per_page` tra 10, 25 e 50 | paginazione |

- Filtri combinati in AND: `paid_month` insieme a `failed_payment=1` non restituisce nulla, perché i tentativi falliti non hanno `paid_at`. Il criterio del mese di pagamento è uno scope (`paidInMonth`) condiviso da `GetDashboardStatsAction` e dall'elenco, così dashboard ed elenco non possono divergere. Ordine per `created_at` decrescente, poi `id`. `meta.total` alimenta «N ordini».
- Riga dell'elenco: `id`, `number`, `customer` (`name`, `email`), `created_at`, `items_count` (somma delle quantità, caricata con `withSum('items', 'quantity')`), `total`, `status`, `failed_payment` (booleano, stesso nome del filtro).

`GET /admin/orders/{order}` restituisce `Admin\OrderResource`: i campi della `OrderResource` del cliente, più:
- `customer`: `name` ed `email` dell'utente, oppure «Cliente eliminato» ed `email` null per un account anonimizzato (sezione "Clienti eliminati"); il telefono è quello dello snapshot dell'indirizzo
- `items_count`
- `failed_payment`
- `allowed_transitions`: array di stringhe con i valori verso cui l'admin può portare l'ordine adesso (`paid` → `["shipped", "cancelled"]`, `shipped` → `["delivered"]`, altrimenti `[]`)
- `payment`: `card_last_four`, `status` (`{ value, label }`), `amount` e `created_at` del pagamento più recente dell'ordine (nel flusso attuale l'unico), anche se `failed`. Il motivo del rifiuto non è nel pagamento: sia l'`Alert` sia la riga "Motivo" della card Pagamento leggono il `reason` della riga di cronologia `cancelled`
- `status_history`: con `changed_by` (`{ id, name }` oppure `null` per il sistema), `reason` e la descrizione nella variante admin

`PATCH /admin/orders/{order}/status` con `{ "status": "shipped" }`: la Form Request accetta solo `shipped`, `delivered` e `cancelled` (`Rule::in`), quindi `paid` e `pending` → 422. `shipped` e `delivered` passano da `ChangeOrderStatusAction` con l'admin come autore, `cancelled` da `CancelOrderAction`. Un valore ammesso ma non consentito dallo stato attuale (es. `delivered` su un ordine `paid`, `cancelled` su un ordine `pending`) → 422 da `InvalidOrderTransitionException`. Risposta con `Admin\OrderResource`.

## Dati demo

Il negozio è **Casa del Silicio**: elettronica e accessori tech della propria linea.

### Categorie

| Slug | Nome | Descrizione |
|---|---|---|
| `audio` | Audio | Cuffie, auricolari, diffusori |
| `ricarica-ed-energia` | Ricarica ed energia | Caricatori, cavi, batterie |
| `scrivania` | Scrivania | Supporti, tastiere, accessori |
| `indossabili` | Indossabili | Orologi e cinturini |
| `casa-smart` | Casa smart | Luci e clima |
| `viaggio` | Viaggio | Custodie e adattatori per chi viaggia |

"Viaggio" non ha prodotti: mostra la categoria vuota nel negozio e l'eliminazione consentita nell'admin.

### Prodotti

- Da 40 a 60 prodotti realistici, distribuiti sulle prime cinque categorie, con alcuni esauriti, alcuni negli ultimi pezzi (da 1 a 3), alcuni sotto la soglia admin e alcuni disattivati.
- SKU nel formato `CDS-{AREA}-{NNN}` (es. `CDS-AUD-001`, `CDS-RIC-010`), conforme a `^[A-Z0-9-]+$`.
- Nomi secondo `voice-and-content.md`: un sostantivo più un nome evocativo italiano, con maiuscola, nessun marchio di terzi. Riferimenti dai mockup: Cuffie Aria, Cuffie Aria Studio, Auricolari Sussurro, Auricolari Sussurro Sport, Diffusore Eco, Diffusore Eco Mini, Radio Onda, Radio Onda Portatile, Caricatore Brezza 65 W, Cavo Seta USB-C 1 m, Supporto Colle in alluminio, Orologio Meridiana, Lampada Faro.
- Unità con lo spazio (65 W, 1 m).
- Ogni prodotto ha `short_description` (massimo 160 caratteri), `description` su più paragrafi e da 4 a 8 voci in `specifications`.
- Alcuni prodotti hanno un `created_at` negli ultimi 30 giorni, calcolato in modo deterministico rispetto alla data del seeding, così la sezione Novità è popolata. Gli altri sono distribuiti sui mesi precedenti.
- Il prodotto dell'hero della home, `cuffie-aria`, esiste sempre, è attivo, disponibile e tra le novità.

### Utenti e ordini

- Utenti fissi, documentati nel README con le credenziali:
  - l'admin **Marta Lenzi**, senza doppia autenticazione
  - un cliente demo con email verificata, indirizzi italiani validi e ordini in tutti gli stati
  - un cliente demo con doppia autenticazione attiva e segreto TOTP fisso, riportato nel README solo per la demo, così si può aggiungere all'app di autenticazione durante la presentazione
  - un cliente con email non verificata
  - dalla Fase 3b, quando esiste `anonymized_at`: un cliente anonimizzato con un ordine consegnato, per mostrare «Cliente eliminato» nell'area admin
- Da 15 a 30 clienti casuali.
- Ordini distribuiti sugli ultimi 6 mesi, con ordini pagati anche nel mese corrente, così fatturato e dashboard hanno valori sensati, compresi alcuni tentativi di pagamento falliti visibili solo all'admin.
- Ogni ordine ha la cronologia coerente con il suo stato e i suoi timestamp, con Marta Lenzi come autrice delle transizioni dell'admin, e la riga in `payments` (`succeeded`, `failed` o `refunded`).
- Il carrello del cliente demo contiene almeno una riga con un prodotto esaurito, per mostrare il blocco del checkout.

## Mappa delle API del negozio

Prefisso comune: `/api/v1`. Autenticazione, account, sicurezza ed eliminazione dell'account (`DELETE /me`) sono nella skill `account-security`; le rotte dell'assistente AI nella skill `ai-assistant`.

**Pubbliche**

| Metodo | Percorso | Descrizione |
|---|---|---|
| GET | /health | stato del servizio |
| GET | /shop/settings | `shipping_cost` e `free_shipping_threshold` per i testi del negozio |
| GET | /shop/support | sezioni della pagina Assistenza, usate anche dall'assistente AI |
| GET | /assistant/status | disponibilità dell'assistente AI (skill `ai-assistant`) |
| POST | /assistant/messages | risposta dell'assistente AI in streaming, limiter `assistant` (skill `ai-assistant`) |
| GET | /categories | elenco delle categorie con descrizione, ordinate per `id` |
| GET | /categories/{category:slug} | dettaglio categoria |
| GET | /products | elenco con `search`, `category`, `min_price`, `max_price`, `in_stock`, `new`, `sort`, `page`, `per_page`; `meta.price_range` per lo slider |
| GET | /products/{product:slug} | dettaglio prodotto con descrizione, scheda tecnica, immagini e `max_quantity` |
| GET | /products/{product:slug}/related | fino a 4 prodotti correlati della stessa categoria, disponibili |

**Cliente autenticato**

| Metodo | Percorso | Descrizione |
|---|---|---|
| GET, POST, PATCH, DELETE | /addresses | gestione indirizzi (`apiResource`), solo in Italia |
| GET | /cart | carrello con righe, `summary` e `can_checkout` |
| POST | /cart/items | aggiunta prodotto (`product_id`, `quantity`); 422 oltre `max_quantity` |
| PATCH | /cart/items/{cartItem} | modifica quantità, tra 1 e `max_quantity` |
| DELETE | /cart/items/{cartItem} | rimozione riga |
| DELETE | /cart | svuotamento carrello |
| POST | /checkout | creazione ordine e pagamento (email verificata); 201, 409 per disponibilità, 402 per pagamento rifiutato, 422 per dati non validi |
| GET | /orders | storico degli ordini pagati (`paid_at` valorizzato) |
| GET | /orders/{order} | dettaglio di un ordine pagato con `status_history`; 404 per ordini non pagati o di altri utenti |
| POST | /orders/{order}/cancel | annullamento da parte del cliente con `CancelOrderAction`; 422 se non annullabile, 502 se il rimborso fallisce |

**Admin** (prefisso `/admin`, ruolo `admin`)

| Metodo | Percorso | Descrizione |
|---|---|---|
| GET | /admin/settings | impostazioni del negozio più `low_stock_threshold` |
| GET | /admin/dashboard | indicatori, ordini per stato, ultimi ordini e scorte basse (sezione "Dashboard") |
| GET, POST, PATCH, DELETE | /admin/products | gestione prodotti (`apiResource`) con filtri `search`, `category`, `status`; `images` nel `PATCH` per ordine e testi alternativi |
| POST | /admin/products/{product}/images | caricamento di una o più immagini |
| DELETE | /admin/products/{product}/images/{image} | rimozione immagine |
| GET, POST, PATCH, DELETE | /admin/categories | gestione categorie (`apiResource`), slug generato e non modificabile, 409 se collegata a prodotti |
| GET | /admin/orders | tutti gli ordini, compresi i tentativi falliti, con `search`, `status`, `failed_payment` e `paid_month` |
| GET | /admin/orders/{order} | dettaglio ordine con cliente, pagamento, cronologia e `allowed_transitions` |
| PATCH | /admin/orders/{order}/status | cambio di stato (`shipped`, `delivered`, `cancelled`); 422 per transizione non consentita, 502 se il rimborso fallisce |

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

Il dettaglio aggiunge `short_description`, `description`, `specifications`, `images` e `max_quantity`.
