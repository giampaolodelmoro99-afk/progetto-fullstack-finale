---
name: ecommerce-domain
description: Regole di business dell'e-commerce — entità e colonne, importi in centesimi, snapshot degli ordini, gestione dello stock con lock, carrello, checkout, stati dell'ordine e transizioni, pagamenti simulati, dati demo dei seeder e mappa delle API del negozio. Usala per modelli, migration, factory, seeder, carrello, checkout, ordini, pagamenti e ogni schermata che mostra questi dati.
---

# Dominio e-commerce — regole di business

## Entità del negozio

Le tabelle dell'account e della sicurezza sono descritte nella skill `account-security`.

| Tabella | Colonne principali | Note |
|---|---|---|
| users | name, email, password, role, email_verified_at | `role`: `customer` o `admin` |
| addresses | user_id, full_name, street, city, postal_code, province, country, phone, is_default | un solo indirizzo predefinito per utente |
| categories | parent_id, name, slug, description | massimo un livello di sottocategorie |
| category_product | category_id, product_id | molti-a-molti |
| products | name, slug, sku, description, price, stock, is_active, deleted_at | `price` in centesimi, soft delete |
| product_images | product_id, path, alt, position | la prima per `position` è la copertina |
| carts | user_id (unico) | un carrello per utente |
| cart_items | cart_id, product_id, quantity | unico su (cart_id, product_id) |
| orders | user_id, number, status, subtotal, shipping_cost, total, shipping_address (JSON), paid_at, shipped_at, delivered_at, cancelled_at | importi in centesimi |
| order_items | order_id, product_id (nullable), product_name, product_sku, unit_price, quantity, line_total | snapshot al momento dell'acquisto |
| payments | order_id, provider, provider_reference, amount, status, card_last_four | mai dati completi della carta |

## Regole

1. **Importi in centesimi** (`unsignedInteger`), mai float. I prezzi sono IVA inclusa: nessun calcolo delle imposte.
2. **Il server non si fida del client.** Prezzi, totali e disponibilità si ricalcolano sempre dal database; il client invia solo id e quantità.
3. **Snapshot all'acquisto.** `order_items` copia nome, SKU e prezzo; `orders.shipping_address` copia l'indirizzo. Modificare o eliminare prodotti e indirizzi non altera gli ordini esistenti.
4. **Stock.** Si verifica e si decrementa in transazione con `lockForUpdate()` sui prodotti coinvolti. Se è insufficiente si lancia `InsufficientStockException` (409) con l'elenco dei prodotti problematici. Lo stock torna disponibile quando un ordine viene annullato.
5. **Catalogo pubblico:** solo prodotti `is_active` e non eliminati. I prodotti con stock 0 sono visibili ma non acquistabili.
6. **Carrello:** solo per utenti autenticati. Quantità tra 1 e lo stock disponibile. Aggiungere un prodotto già presente somma la quantità. Le righe con prodotti diventati non disponibili vengono segnalate nella risposta con un flag.
7. **Checkout solo con email verificata** (middleware `verified`).
8. **Spedizione:** costo fisso e soglia di gratuità in `config/shop.php`, in centesimi.
9. **Numero d'ordine** leggibile e univoco nel formato `ORD-{anno}-{id a 6 cifre}`, es. `ORD-2026-000042`.
10. **Ruoli:** la registrazione crea solo `customer`; l'admin nasce dal seeder.
11. **Categorie:** una categoria con prodotti associati non si elimina (409).
12. **Scorte basse:** soglia in `config/shop.php`, usata nella dashboard admin.

## Stati dell'ordine

Enum `OrderStatus` con valori `pending`, `paid`, `shipped`, `delivered`, `cancelled`. Le transizioni consentite sono definite nell'enum con `canTransitionTo()`.

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

- Tema del negozio, categorie e immagini si decidono nella Fase 1 con Claude Design.
- Utenti fissi, documentati nel README con le credenziali:
  - un admin, senza doppia autenticazione
  - un cliente demo con email verificata, indirizzi e ordini in tutti gli stati
  - un cliente demo con doppia autenticazione attiva e segreto TOTP fisso, riportato nel README solo per la demo, così si può aggiungere all'app di autenticazione durante la presentazione
  - un cliente con email non verificata
- Da 15 a 30 clienti casuali.
- Da 40 a 60 prodotti realistici, distribuiti sulle categorie, con alcuni esauriti, alcuni con scorte basse e alcuni disattivati.
- Ordini distribuiti sugli ultimi 6 mesi, così fatturato e grafici della dashboard hanno valori sensati.

## Mappa delle API del negozio

Prefisso comune: `/api/v1`. Autenticazione, account e sicurezza sono nella skill `account-security`.

**Pubbliche**

| Metodo | Percorso | Descrizione |
|---|---|---|
| GET | /health | stato del servizio |
| GET | /categories | albero delle categorie |
| GET | /categories/{category:slug} | dettaglio categoria |
| GET | /products | elenco con `search`, `category`, `min_price`, `max_price`, `in_stock`, `sort`, `page`, `per_page` |
| GET | /products/{product:slug} | dettaglio prodotto |

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
| GET | /admin/dashboard | fatturato del mese, ordini per stato, prodotti con scorte basse, ultimi ordini |
| GET, POST, PATCH, DELETE | /admin/products | gestione prodotti (`apiResource`) |
| POST | /admin/products/{product}/images | caricamento immagini |
| DELETE | /admin/products/{product}/images/{image} | rimozione immagine |
| GET, POST, PATCH, DELETE | /admin/categories | gestione categorie (`apiResource`) |
| GET | /admin/orders | elenco ordini con filtro per stato |
| GET | /admin/orders/{order} | dettaglio ordine |
| PATCH | /admin/orders/{order}/status | cambio di stato |
