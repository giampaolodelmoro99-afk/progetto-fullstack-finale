---
name: mui-design-system
description: Design system MUI del progetto — organizzazione del tema, regole vincolanti di stile con la prop sx e i token del tema, layout, pattern dei componenti per negozio, account, sicurezza e admin, accessibilità e traduzione dei mockup di Claude Design in componenti MUI. Usala per qualsiasi lavoro visivo in frontend/.
---

# MUI — design system del progetto

## Tema

```
src/theme/
├── index.js         # createTheme che compone i moduli
├── palette.js
├── typography.js
└── components.js    # defaultProps e styleOverrides dei componenti MUI
```

- `ThemeProvider` e `CssBaseline` sono in `AppProviders`.
- Colori, font, raggi e ombre derivano dai token definiti con Claude Design, esportati in `design/`. Finché la fase di design non è conclusa, il tema contiene valori provvisori.

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
    <CardMedia component="img" image={product.coverUrl} alt={product.name} sx={{ aspectRatio: '4 / 3', objectFit: 'cover' }} />
  </CardActionArea>
  <CardContent sx={{ flexGrow: 1 }}>
    <Typography variant="subtitle1" component="h3">
      {product.name}
    </Typography>
    <Typography variant="h6" sx={{ color: 'primary.main', mt: 1 }}>
      {formatPrice(product.price)}
    </Typography>
  </CardContent>
</Card>
```

## Layout

- `Container` per la larghezza massima dei contenuti.
- `Stack` per allineamenti su un solo asse.
- `Grid` per le griglie: verifica l'API di `Grid` della versione di MUI installata prima di usarla.
- Negozio: `AppBar` con logo, ricerca, icona del carrello con `Badge` (numero di articoli) e menu account; `Drawer` per la navigazione su mobile; footer.
- Account: menu laterale (`List` di navigazione) su desktop, `Tabs` scorrevoli su mobile.
- Auth: card centrata con larghezza massima contenuta.
- Admin: `Drawer` permanente su desktop e temporaneo su mobile, con `AppBar` in alto.

## Pattern dei componenti

| Caso | Componenti MUI |
|---|---|
| Card prodotto | `Card`, `CardActionArea`, `CardMedia` con rapporto d'aspetto fisso, `CardContent` |
| Filtri catalogo | `TextField`, `Select`, `Slider` per il prezzo, `Switch` per "solo disponibili", `Pagination` |
| Dettaglio prodotto | galleria con miniature, `Chip` di disponibilità, selettore di quantità, `Button` "Aggiungi al carrello" |
| Checkout | `Stepper` orizzontale su desktop e verticale su mobile |
| Email non verificata | `Alert` persistente con azione "Invia di nuovo l'email" |
| Codice di verifica | `TextField` numerico con `inputMode="numeric"`, `autoComplete="one-time-code"` e link "Usa un codice di recupero" |
| Attivazione doppia autenticazione | `Stepper` in tre passi: QR code e chiave manuale, verifica del codice, codici di recupero |
| Codici di recupero | griglia di codici in `Typography` monospazio, con pulsanti "Copia" e "Scarica" e `Alert` che ricorda che non verranno più mostrati |
| Elenco passkey | `List` con nome, data di creazione e ultimo utilizzo, `IconButton` per la rimozione |
| Conferma dell'identità | `ConfirmIdentityDialog`: password e, se attivo, secondo fattore, con scelta del metodo |
| Attività di sicurezza | `List` o `Table` con icona del tipo di evento, data, dispositivo e IP |
| Tabelle admin | `Table` con `TablePagination` |
| Stato ordine | `Chip` con colore semantico (vedi sotto) |
| Conferme distruttive | `Dialog` |
| Esito di un'azione | `Snackbar` con `Alert` |
| Caricamento dati | `Skeleton` con la forma del contenuto |
| Azione in corso | `CircularProgress` dentro il pulsante |
| Errore di caricamento | `Alert` con severità `error` e azione "Riprova" |
| Stato vuoto | icona, `Typography` e `Button` d'azione |

Colori semantici degli stati d'ordine: `pending` → `warning`, `paid` → `info`, `shipped` → `primary`, `delivered` → `success`, `cancelled` → `default`.

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

## Da Claude Design al codice

1. Estrai i token del design (colori, font, raggi, spaziature, ombre) e mappali nei moduli del tema.
2. Associa ogni elemento del mockup a un componente MUI esistente.
3. Non copiare CSS o markup HTML dell'export: il design è un riferimento visivo, l'implementazione è MUI.
4. Se un elemento non ha un equivalente MUI, componilo con componenti MUI e `sx`, e segnalalo nel piano.
