---
name: mui-design-system
description: Design system MUI del progetto — organizzazione del tema, font, regole vincolanti di stile con la prop sx e i token del tema, voce e contenuti, layout, pattern dei componenti per negozio, account, sicurezza e admin, accessibilità e traduzione dei mockup di Claude Design in componenti MUI. Usala per qualsiasi lavoro visivo in frontend/.
---

# MUI — design system del progetto

Fonti:

| File | Contenuto |
|---|---|
| `design/design-system/` | design system "Casa del Silicio" di Claude Design: `theme.js` di riferimento, colori, tipografia, componenti |
| `design/design-system/voice-and-content.md` | regole di voce e contenuti, vincolanti per ogni testo dell'interfaccia |
| `design/mockups/01-negozio.pdf` | mockup del negozio: home, catalogo, prodotto, stati di caricamento e vuoto, desktop e mobile |
| `design/mockups/02-acquisto.pdf` | mockup dell'acquisto: carrello (pieno, vuoto, con righe esaurite, caricamento), checkout in tre passi con errori, email da verificare, conferma d'ordine, desktop e mobile |

## Tema

```
src/theme/
├── index.js         # createTheme che compone i moduli
├── palette.js
├── typography.js
└── components.js    # defaultProps e styleOverrides dei componenti MUI
```

- `ThemeProvider` e `CssBaseline` sono in `AppProviders`.
- `design/design-system/theme.js` è un riferimento, non si importa così com'è: si divide nei quattro moduli di `src/theme/`. Fino alla Fase 7 il tema contiene valori provvisori.
- Font self-hosted con i pacchetti `@fontsource-variable` (dipendenze da installare nella Fase 7):
  - Bodoni Moda per titoli e prezzi
  - Manrope per interfaccia e testi

  Niente Google Fonts da CDN.
- L'ombra in hover di `MuiCard` si applica solo alle card cliccabili, cioè quelle che contengono una `CardActionArea`; le card informative restano ferme.

## Regole di stile (vincolanti)

- Stile solo tramite la prop `sx`.
- Solo token del tema:
  - colori: `'primary.main'`, `'secondary.light'`, `'text.secondary'`, `'background.paper'`, `'divider'`, `'error.main'`
  - spaziature: numeri moltiplicatori (`p: 2`, `gap: 3`, `mt: 1`)
  - raggi: `borderRadius: 1` o `2` (multipli di `theme.shape.borderRadius`)
  - tipografia: `variant` di `Typography`, mai `fontSize` o `fontWeight` manuali
  - ombre: `elevation` o `boxShadow` con valori del tema
  - dimensioni relative: `width: 1` per il 100%
- Vietati: codici hex o rgb, valori in px, attributo `style`, file CSS, `makeStyles`.
- Un valore che il tema non ha si aggiunge al tema e poi si usa come token.
- Una personalizzazione ripetuta di un componente MUI va in `components.js`, non duplicata negli `sx`.
- Responsive con la sintassi a oggetto e approccio mobile-first: `{ xs: 1, md: 3 }`.

```jsx
<Card sx={{ height: 1, display: 'flex', flexDirection: 'column', borderRadius: 2 }}>
  <CardActionArea component={RouterLink} to={paths.product(product.slug)}>
    <CardMedia component="img" image={product.cover.url} alt={product.cover.alt} sx={{ aspectRatio: '4 / 5', objectFit: 'contain' }} />
    <CardContent sx={{ flexGrow: 1 }}>
      <Typography variant="overline" sx={{ color: 'text.secondary' }}>
        {product.category.name}
      </Typography>
      <Typography variant="subtitle1" component="h3">
        {product.name}
      </Typography>
      <Typography variant="h6" component="p">
        {formatPrice(product.price)}
      </Typography>
    </CardContent>
  </CardActionArea>
</Card>
```

## Voce e contenuti

Riassunto delle regole di `design/design-system/voice-and-content.md`, che resta la fonte da consultare:

- Tutti i testi dell'interfaccia sono in italiano e si dà del tu, con garbo: «Aggiungi al carrello», «Il tuo ordine è in viaggio».
- Sentence case in pulsanti, titoli e voci di menu: «Scopri la collezione», mai «Scopri La Collezione». Il maiuscolo spaziato è riservato alla variante `overline` («AUDIO», «NOVITÀ»).
- Frasi brevi e concrete: niente punti esclamativi, niente emoji, niente urgenza artificiale («Ultimi 2 pezzi» sì, «Affrettati!!» no).
- Prezzi nel formato it-IT con due decimali e simbolo dopo la cifra (349,00 €, 1.249,00 €), sempre con `formatPrice`, che usa `useGrouping: 'always'`. Mai «€349» o «349 euro».
- Unità con lo spazio: «65 W», «1 m», «24/48 ore».
- Nomi dei prodotti: un sostantivo più un nome evocativo italiano, con maiuscola (Cuffie Aria, Caricatore Brezza 65 W). Mai marchi di terzi.
- Parole fisse, che arrivano dall'API e non si riscrivono:
  - stati d'ordine: In attesa, Pagato, Spedito, Consegnato, Annullato
  - disponibilità: Disponibile, Ultimi N pezzi (Ultimo pezzo se 1), Esaurito

## Layout

- `Container` per la larghezza massima dei contenuti.
- `Stack` per allineamenti su un solo asse.
- `Grid` per le griglie: verifica l'API di `Grid` della versione di MUI installata prima di usarla.
- Negozio, dal mockup `01-negozio.pdf`:
  - `AppBar` con logo, campo di ricerca, icona account e icona del carrello con `Badge` (numero di articoli)
  - su desktop, sotto l'AppBar, una barra di navigazione con "Novità" e le cinque categorie; la voce attiva è sottolineata
  - su mobile, un `Drawer` con le sezioni "Categorie" (le cinque categorie e "Novità") e "Il tuo account" (accesso, ordini, assistenza); la ricerca va sotto l'AppBar
  - footer scuro su quattro colonne: logo e payoff, Categorie, Assistenza, Note legali; in fondo copyright e «Prezzi IVA inclusa · Pagamenti protetti». Su mobile le colonne si impilano.
  - breadcrumb (`Breadcrumbs`) in catalogo e pagina prodotto
- Account: menu laterale (`List` di navigazione) su desktop, `Tabs` scorrevoli su mobile.
- Auth: card centrata con larghezza massima contenuta.
- Admin: `Drawer` permanente su desktop e temporaneo su mobile, con `AppBar` in alto.

## Pattern dei componenti

| Caso | Componenti MUI |
|---|---|
| Card prodotto | `Card` cliccabile con `CardActionArea`, `CardMedia` con rapporto d'aspetto fisso, `Chip` "Novità" in alto a sinistra se `is_new`, categoria in `overline`, nome, prezzo, etichetta di disponibilità sotto il prezzo solo se non è `available` |
| Hero della home | due colonne su desktop (testi e immagine), impilate su mobile; etichetta `overline`, titolo, paragrafo, prezzo, pulsante pieno |
| Categorie in evidenza | griglia di cinque tessere con immagine, nome e descrizione su desktop; `List` con icona e freccia su mobile |
| Filtri catalogo | colonna laterale su desktop: `Chip` selezionabili per la categoria, `Slider` a due cursori per il prezzo, `Switch` "Solo disponibili", azione "Azzera filtri"; su mobile un pulsante "Filtri (n)" apre un `Drawer` con gli stessi controlli e i pulsanti "Azzera" e "Mostra N prodotti" |
| Ordinamento catalogo | `Select` "Ordina per" con Novità, Prezzo crescente, Prezzo decrescente |
| Catalogo senza risultati | card con icona di ricerca, titolo "Nessun prodotto corrisponde ai filtri", suggerimento e pulsante "Azzera filtri" |
| Dettaglio prodotto | galleria con miniature, categoria in `overline`, nome, descrizione breve, prezzo con «IVA inclusa», `Chip` di disponibilità, selettore di quantità, `Button` "Aggiungi al carrello"; sotto, "Descrizione" e "Scheda tecnica" (tabella etichetta/valore) affiancate su desktop |
| Prodotto esaurito | quantità e pulsante disabilitati, `Alert` informativo "Prodotto esaurito" che rimanda ai correlati |
| Prodotti correlati | griglia di card prodotto sotto il dettaglio; nascosta se l'elenco è vuoto |
| Rassicurazioni | tre voci con icona: spedizione gratuita sopra 99,00 €, reso entro 30 giorni, garanzia di 2 anni |
| Aggiunta al carrello | `Snackbar` "Aggiunto al carrello" con azione "Vai al carrello" e `IconButton` di chiusura |
| Riga del carrello | miniatura, categoria in `overline`, nome, prezzo "cad.", `Select` "Quantità" da 1 a `max_quantity`, totale della riga, azione "Rimuovi" con icona; righe separate da `Divider` |
| Riga del carrello non acquistabile | stessa riga dentro un riquadro con bordo `error.main`: `Chip` "Esaurito" al posto della quantità, "—" al posto del totale, `Alert` "Prodotto esaurito" con pulsante "Rimuovi dal carrello" |
| Riepilogo del carrello | `Card` con subtotale, spedizione ("Gratuita" o importo, con "Standard, consegna in 24/48 ore"), totale con «IVA inclusa», `LinearProgress` determinata "Ti mancano … per la spedizione gratuita" quando serve, `Alert` del motivo e pulsante "Procedi al checkout" disabilitato quando il checkout è bloccato |
| Barra fissa del carrello e del checkout su mobile | in fondo allo schermo, «Totale, IVA inclusa» con l'importo e il pulsante principale del passo |
| Carrello vuoto | card con icona, "Il tuo carrello è vuoto", "Scopri il negozio"; sotto, "Potrebbero piacerti" con quattro card prodotto |
| Checkout | desktop: `Stepper` orizzontale Indirizzo, Pagamento, Riepilogo, con il riepilogo dell'ordine in una colonna a destra; mobile: nome del passo, "Passo N di 3", `LinearProgress` determinata e "Poi: …", con il riepilogo dell'ordine in un pannello richiudibile ("Riepilogo ordine", N articoli, totale) |
| Scelta dell'indirizzo | card selezionabili con `Radio`, bordo marcato sulla selezionata, `Chip` "Predefinito"; pulsante "Aggiungi un indirizzo" che apre il form in linea |
| Form dell'indirizzo | nome e cognome, via e numero civico, città, CAP e provincia sulla stessa riga su desktop, "Paese" con valore "Italia" non modificabile (`disabled` o `readOnly`), telefono, `Checkbox` "Imposta come predefinito", pulsanti "Annulla" e "Salva indirizzo" |
| Dati della carta | intestatario, numero con icona della carta, scadenza con `helperText` "MM/AA", CVC con `helperText` "3 cifre sul retro"; nota informativa "Ambiente dimostrativo: nessun addebito reale." |
| Controlla e paga | sezioni "Indirizzo di spedizione" e "Pagamento" con link "Modifica", elenco prodotti, totali, `Checkbox` delle condizioni di vendita con link, pulsante "Paga {totale}" con icona del lucchetto |
| Errori del checkout | `Alert` `error` "Pagamento non riuscito" con azione "Modifica la carta"; `Alert` `warning` "Disponibilità cambiata" con azione "Torna al carrello" e l'etichetta di disponibilità sulla riga coinvolta |
| Conferma d'ordine | icona di successo, "Grazie per il tuo ordine", card con numero d'ordine, data, `Chip` dello stato, pagamento e indirizzo; card "Riepilogo dell'ordine"; pulsanti "Vedi il tuo ordine" e "Continua lo shopping" |
| Email non verificata | `Alert` persistente con azione "Invia di nuovo l'email" |
| Codice di verifica | `TextField` numerico con `inputMode="numeric"`, `autoComplete="one-time-code"` e link "Usa un codice di recupero" |
| Attivazione doppia autenticazione | `Stepper` in tre passi: QR code e chiave manuale, verifica del codice, codici di recupero |
| Codici di recupero | griglia di codici in `Typography` monospazio, con pulsanti "Copia" e "Scarica" e `Alert` che ricorda che non verranno più mostrati |
| Elenco passkey | `List` con nome, data di creazione e ultimo utilizzo, `IconButton` per la rimozione |
| Conferma dell'identità | `ConfirmIdentityDialog`: password e, se attivo, secondo fattore, con scelta del metodo |
| Attività di sicurezza | `List` o `Table` con icona del tipo di evento, data, dispositivo e IP |
| Tabelle admin | `Table` con `TablePagination` |
| Stato ordine | `Chip` con l'etichetta ricevuta dall'API e il colore della mappa sotto |
| Conferme distruttive | `Dialog` |
| Esito di un'azione | `Snackbar` con `Alert` |
| Caricamento dati | `Skeleton` con la forma del contenuto |
| Azione in corso | `CircularProgress` dentro il pulsante |
| Errore di caricamento | `Alert` con severità `error` e azione "Riprova" |
| Stato vuoto | icona, `Typography` e `Button` d'azione |

### Colori semantici

Il frontend possiede solo le mappe dei colori; le etichette arrivano dall'API.

| Stato d'ordine | Colore del `Chip` |
|---|---|
| `pending` | `warning` |
| `paid` | `info` |
| `shipped` | `secondary` |
| `delivered` | `success` |
| `cancelled` | `error` |

| Disponibilità | Colore dell'etichetta |
|---|---|
| `available` | `success.main` |
| `low_stock` | `warning.main` |
| `out_of_stock` | `text.secondary` |

## Icone

Solo `@mui/icons-material`.

## Accessibilità

- Ogni `IconButton` ha un `aria-label` in italiano.
- Ogni immagine ha un `alt` descrittivo; il QR code ha un `alt` e accanto la chiave testuale per l'inserimento manuale.
- Ogni campo ha una `label`; gli errori stanno in `helperText`.
- Campi password con `autoComplete` corretto (`current-password`, `new-password`) e pulsante per mostrare o nascondere il testo.
- Un solo `h1` per pagina (`component="h1"`) e gerarchia dei titoli coerente.
- Contrasto della palette conforme a WCAG AA.
- Il focus resta sempre visibile; i dialog spostano il focus sul primo campo.
- Il colore non è mai l'unica informazione: stati e disponibilità hanno sempre anche l'etichetta testuale.

## Da Claude Design al codice

1. Estrai i token del design (colori, font, raggi, spaziature, ombre) e mappali nei moduli del tema.
2. Associa ogni elemento del mockup a un componente MUI esistente.
3. Non copiare CSS o markup HTML dell'export: il design è un riferimento visivo, l'implementazione è MUI.
4. Se un elemento non ha un equivalente MUI, componilo con componenti MUI e `sx`, e segnalalo nel piano.
5. Dove il mockup e una decisione approvata divergono, vale la decisione: per esempio l'ordinamento predefinito è "Novità" (il mockup mostra "Rilevanza"), i correlati sono della stessa categoria e il campo "Paese" dell'indirizzo non è modificabile (il mockup mostra un select).
