---
name: mui-design-system
description: Design system MUI del progetto — organizzazione del tema, font, regole vincolanti di stile con la prop sx e i token del tema, voce e contenuti, layout di negozio, accesso, area account e pannello admin, pattern dei componenti per negozio, acquisto, assistente AI, account, sicurezza (compresa l'eliminazione dell'account) e admin (dashboard, tabelle, form prodotto con immagini, categorie, dettaglio ordine), dialog di conferma, accessibilità e traduzione dei mockup di Claude Design in componenti MUI. Usala per qualsiasi lavoro visivo in frontend/.
---

# MUI — design system del progetto

Fonti:

| File | Contenuto |
|---|---|
| `design/design-system/` | design system "Casa del Silicio" di Claude Design: `theme.js` di riferimento, colori, tipografia, componenti |
| `design/design-system/voice-and-content.md` | regole di voce e contenuti, vincolanti per ogni testo dell'interfaccia |
| `design/mockups/01-negozio.pdf` | mockup del negozio: home, catalogo, prodotto, stati di caricamento e vuoto, desktop e mobile |
| `design/mockups/02-acquisto.pdf` | mockup dell'acquisto: carrello (pieno, vuoto, con righe esaurite, caricamento), checkout in tre passi con errori, email da verificare, conferma d'ordine, desktop e mobile |
| `design/mockups/03-account.pdf` | mockup dell'account: accesso (errore, troppi tentativi, verifica in due passaggi), registrazione, pagine raggiunte dai link email, menu account, profilo con cambio email, indirizzi, ordini e dettaglio, sicurezza (doppia autenticazione, passkey, attività recente), dialog di conferma, desktop e mobile |
| `design/mockups/04-admin.pdf` | mockup del pannello admin: layout con Drawer, dashboard (desktop e mobile), elenco prodotti (caricamento, vuoto, eliminazione), nuovo e modifica prodotto (errori, modifiche non salvate), categorie con dialog di modifica ed eliminazione, elenco ordini (filtri, pagamenti non riusciti, vuoto, mobile), dettaglio ordine (pagato, spedito, annullamento, pagamento rifiutato) |
| `design/mockups/05-assistente-account.pdf` | Design E: assistente AI nel negozio (pulsante, pannello vuoto con suggerimenti, risposta con card, attesa, scrittura, quattro errori, domanda fuori tema, desktop e mobile, assistente non disponibile), sezione "Elimina account" in Sicurezza con il dialog di conferma e il dialog degli ordini in corso, categoria inesistente, amministratrice nel negozio (menu account e pagina prodotto) |

## Tema

```
src/theme/
├── index.js         # createTheme che compone i moduli
├── palette.js
├── typography.js
└── components.js    # defaultProps e styleOverrides dei componenti MUI
```

- `ThemeProvider` e `CssBaseline` sono in `AppProviders`.
- `design/design-system/theme.js` è un riferimento, non si importa così com'è: dalla Fase 7a è diviso nei quattro moduli di `src/theme/`, più `statusColors.js` con le mappe dei colori semantici. Colori senza ruolo MUI (es. `background.inverse`, `text.inverse`, `text.inverseAccent`, `inverseDivider` per il footer scuro) sono chiavi della palette, usate come token negli `sx`.
- Font self-hosted con i pacchetti `@fontsource-variable`, importati in `src/main.jsx` (installati nella Fase 7a; licenze nel README):
  - Bodoni Moda per titoli e prezzi
  - Manrope per interfaccia e testi

  Niente Google Fonts da CDN.
- L'ombra in hover di `MuiCard` si applica solo alle card cliccabili, cioè quelle che contengono una `CardActionArea`; le card informative restano ferme.
- Localizzazione: `createTheme(..., itIT)` con `itIT` di `@mui/material/locale` (parte di MUI, nessuna dipendenza nuova), così `TablePagination` e gli altri componenti hanno i testi italiani. "Righe per pagina" e «1–10 di 42» si impostano comunque in `components.js` (`labelRowsPerPage`, `labelDisplayedRows`) come valori attesi dai test.
- La larghezza del Drawer admin è un token del tema (es. `theme.layout.adminDrawerWidth`), non un valore negli `sx`. Lo stesso vale per le altre misure di layout della 7a: `minPageHeight` (`100vh`), `storeDrawerWidth`, `drawerMaxWidth` (`85vw`), `authCardMaxWidth`, `accountNavigationWidth`, `emptyStateTextMaxWidth` (`48ch`), `footerTaglineMaxWidth` (`32ch`) e `navigationUnderlineOffset`; negli `sx` si leggono con una funzione, es. `maxWidth: (theme) => theme.layout.drawerMaxWidth`.
- Pagina Sicurezza (Fase 9b): `theme.layout.qrCodeSize` (176 px) è il lato del QR code della doppia autenticazione; la variante tipografica `code` (monospazio, `variantMapping` `span`) è per la chiave manuale e i codici di recupero.
- Area admin (Fase 9c): `theme.layout.adminContentPadding` (`{ xs: 2, md: 4 }`, padding del contenuto, usato anche in negativo dalla barra di salvataggio per arrivare ai bordi), `theme.layout.adminSearchMaxWidth` (360 px, ricerca dei prodotti), `adminOrderSearchMaxWidth` (400 px, ricerca degli ordini) e `adminFilterMinWidth` (200 px, `Select` dei filtri), solo da `md`.
- Anche le misure del pannello dell'assistente su desktop sono token del tema: `theme.layout.assistantPanelWidth` (440 px), `theme.layout.assistantPanelMaxHeight` (760 px, altezza massima) e `theme.layout.storeHeaderHeight` (120 px, altezza dell'header del negozio su desktop: toolbar di 72 px, barra delle categorie e bordi). L'altezza del pannello è `min(assistantPanelMaxHeight, 100dvh − storeHeaderHeight − spazio del pulsante − margine)`, così il pannello non finisce mai sotto l'header sticky; se l'header cambia altezza (per esempio la barra delle categorie va su due righe) il token va aggiornato.

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
  - etichette degli eventi di sicurezza (es. Accesso effettuato, Password cambiata, Passkey aggiunta) e del dispositivo (es. «Chrome su macOS», «Dispositivo sconosciuto»)
- Nei dialog di conferma i pulsanti dicono l'azione per esteso, così non sono ambigui: «Mantieni l'ordine» e «Annulla l'ordine», mai un «Annulla» da solo accanto a un'azione che si chiama anch'essa annullamento.

## Layout

- `Container` per la larghezza massima dei contenuti.
- `Stack` per allineamenti su un solo asse.
- `Grid` per le griglie: verifica l'API di `Grid` della versione di MUI installata prima di usarla.
- Negozio, dal mockup `01-negozio.pdf`:
  - `AppBar` con logo, campo di ricerca, icona account e icona del carrello con `Badge` (numero di articoli)
  - su desktop, sotto l'AppBar, una barra di navigazione con "Novità" e le categorie ricevute da `GET /categories`; la voce attiva è sottolineata; la barra va a capo o scorre se le categorie aumentano
  - su mobile, un `Drawer` con le sezioni "Categorie" (le categorie ricevute e "Novità") e "Il tuo account" (accesso, ordini, assistenza); la ricerca va sotto l'AppBar
  - durante il caricamento delle categorie, `Skeleton` di testo al posto delle voci
  - footer scuro su quattro colonne: logo e payoff, Categorie, Assistenza, Note legali; in fondo copyright e «Prezzi IVA inclusa · Pagamenti protetti». Su mobile le colonne si impilano.
  - breadcrumb (`Breadcrumbs`) in catalogo e pagina prodotto
- Menu account nell'header, dal mockup `03-account.pdf`:
  - ospite: l'`IconButton` account (`aria-label` "Accedi") porta a `/login`
  - autenticato: l'`IconButton` (`aria-label` "Il tuo account") apre un `Menu` ancorato a destra, con in testa nome (`subtitle2`) ed email (`caption`, `text.secondary`), un `Divider`, le voci Profilo, Indirizzi, I miei ordini, Sicurezza con `ListItemIcon`, un `Divider` e la voce Esci
  - admin, dal mockup `05-assistente-account.pdf`: le voci sono Profilo, Sicurezza e Amministrazione, poi il `Divider` ed Esci; l'icona del carrello non compare nell'header, né su desktop né su mobile
  - icone outlined: `PersonOutlineOutlined` (Profilo), `PlaceOutlined` (Indirizzi), `Inventory2Outlined` (I miei ordini), `ShieldOutlined` (Sicurezza), `SpaceDashboardOutlined` (Amministrazione), `Logout` (Esci)
- Area account, dal mockup `03-account.pdf`:
  - resta dentro il layout del negozio (header, navigazione e footer)
  - intestazione con `h1` "Il tuo account" e sotto «Nome · email» in `body2` `text.secondary`
  - desktop: colonna sinistra con `List` di navigazione (`ListItemButton` con le stesse icone del menu account; la voce attiva ha lo sfondo evidenziato), un `Divider` e Esci; a destra il contenuto
  - mobile: `Tabs` scorrevoli Profilo, Indirizzi, I miei ordini, Sicurezza sotto l'intestazione; Esci resta nel `Drawer`
  - ogni pagina ha un titolo `h2` con un sottotitolo in `body2` (es. "Sicurezza" / "Gestisci la password, la doppia autenticazione e i dispositivi con cui accedi."), poi le sezioni in `Card` informative con titolo `h3` in Bodoni
  - su mobile le azioni delle card (es. "Cambia password", "Genera nuovi codici") vanno a tutta larghezza sotto il testo
- Accesso e pagine raggiunte dai link email, dal mockup `03-account.pdf`: dentro il layout del negozio (header, navigazione e footer visibili), una `Card` centrata con larghezza massima contenuta, titolo `h1`, testo introduttivo, form o esito, e link secondari in fondo.
- Admin, dal mockup `04-admin.pdf`:
  - layout proprio, senza header e footer del negozio; contenuto su `background.default`, sezioni in `Card` informative su `background.paper`
  - `Drawer` permanente da `md`, temporaneo sotto `md`, con larghezza dal token del tema
  - testa del Drawer: logo «Casa del Silicio» e sotto «AMMINISTRAZIONE» in `overline`, separati dal menu con un `Divider`; su mobile un `IconButton` "Chiudi il menu" con `Close`
  - `List` di `ListItemButton` con `ListItemIcon`: Dashboard (`SpaceDashboardOutlined`), Prodotti (`Inventory2Outlined`), Categorie (`CategoryOutlined`), Ordini (`LocalMallOutlined`); la voce attiva ha lo sfondo evidenziato (`selected`)
  - in fondo al Drawer, dopo un `Divider`: "Vai al negozio" (`StorefrontOutlined`) ed "Esci" (`Logout`)
  - `AppBar` bianca con bordo inferiore: a sinistra il titolo della pagina, `Typography` `h1` in Bodoni della dimensione di un titolo di sezione (unico `h1` della pagina); a destra nome (`subtitle2`), email sotto in `caption` `text.secondary` e `Avatar` con le iniziali
  - mobile: `IconButton` "Apri il menu" con `Menu` a sinistra del titolo, a destra solo l'`Avatar`
  - titoli delle card in `h3` Bodoni; nel dettaglio ordine il numero d'ordine è `h2`

## Pattern dei componenti

### Negozio e acquisto

| Caso | Componenti MUI |
|---|---|
| Card prodotto | `Card` cliccabile con `CardActionArea`, `CardMedia` con rapporto d'aspetto fisso, `Chip` "Novità" in alto a sinistra se `is_new`, categoria in `overline`, nome, prezzo, etichetta di disponibilità sotto il prezzo solo se non è `available` |
| Hero della home | due colonne su desktop (testi e immagine), impilate su mobile; etichetta `overline`, titolo, paragrafo, prezzo, pulsante pieno |
| Categorie in evidenza | griglia di tessere, una per categoria ricevuta, con immagine, nome e descrizione su desktop; `List` con icona e freccia su mobile |
| Filtri catalogo | colonna laterale su desktop: `Chip` selezionabili per la categoria, `Slider` a due cursori per il prezzo, `Switch` "Solo disponibili", azione "Azzera filtri"; su mobile un pulsante "Filtri (n)" apre un `Drawer` con gli stessi controlli e i pulsanti "Azzera" e "Mostra N prodotti" |
| Ordinamento catalogo | `Select` "Ordina per" con Novità, Prezzo crescente, Prezzo decrescente |
| Catalogo senza risultati | card con icona di ricerca, titolo "Nessun prodotto corrisponde ai filtri", suggerimento e pulsante "Azzera filtri" |
| Dettaglio prodotto | galleria con miniature, categoria in `overline`, nome, descrizione breve, prezzo con «IVA inclusa», `Chip` di disponibilità, selettore di quantità, `Button` "Aggiungi al carrello"; sotto, "Descrizione" e "Scheda tecnica" (tabella etichetta/valore) affiancate su desktop |
| Prodotto esaurito | quantità e pulsante disabilitati, `Alert` informativo "Prodotto esaurito" che rimanda ai correlati |
| Prodotto visto da un admin | dal mockup `05-assistente-account.pdf`: niente selettore di quantità né "Aggiungi al carrello"; al loro posto un `Alert` `info` «Gli account di amministrazione non possono fare acquisti.»; prezzo, `Chip` di disponibilità e rassicurazioni restano |
| Categoria inesistente | dal mockup `05-assistente-account.pdf`: breadcrumb «Home › Categoria non trovata», senza filtri né ordinamento; `Card` informativa a tutta larghezza con contenuto centrato: icona `CategoryOutlined` in un `Avatar`, titolo `h2` in Bodoni «Questa categoria non esiste», testo `body2` `text.secondary` «Forse il link è vecchio, oppure l'indirizzo ha un errore.», `Button` pieno "Vedi tutti i prodotti" |
| Prodotti correlati | griglia di card prodotto sotto il dettaglio; nascosta se l'elenco è vuoto |
| Rassicurazioni | tre voci con icona: spedizione gratuita sopra la soglia letta dalle impostazioni del negozio (99,00 € nei dati attuali), reso entro 30 giorni, garanzia di 2 anni |
| Aggiunta al carrello | `Snackbar` "Aggiunto al carrello" con azione "Vai al carrello" e `IconButton` di chiusura |
| Riga del carrello | miniatura, categoria in `overline`, nome, prezzo "cad.", `Select` "Quantità" da 1 a `max_quantity`, totale della riga, azione "Rimuovi" con icona; righe separate da `Divider` |
| Riga del carrello non acquistabile | stessa riga dentro un riquadro con bordo `error.main`: `Chip` "Esaurito" al posto della quantità, "—" al posto del totale, `Alert` "Prodotto esaurito" (o "Prodotto non disponibile" per un prodotto disattivato o eliminato) con pulsante "Rimuovi dal carrello" |
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
| Email non verificata | `Alert` persistente in cima alla pagina (non nelle pagine di accesso e dei link email), «Conferma il tuo indirizzo email per completare gli acquisti. Ti abbiamo inviato un link a {email}.», con azione "Invia di nuovo l'email" |

### Assistente AI

Dal mockup `05-assistente-account.pdf`. I testi indicati sono vincolanti; regole di comportamento nelle skill `ai-assistant` e `react-architecture`.

| Caso | Componenti MUI |
|---|---|
| Pulsante | `Button` fisso in basso a destra, sopra il contenuto (`position: 'fixed'`, `zIndex: 'fab'` del tema; il pannello desktop usa lo stesso livello, sotto `modal` e `snackbar`), in ogni pagina di `StoreLayout`, mai per un admin. Sotto `md` non compare in `/cart` e `/checkout`, dove c'è la barra fissa del carrello e del checkout. Chiuso: outlined su `background.paper` con `ChatBubbleOutline` e «Chiedi all'assistente». Aperto, solo su desktop: pieno scuro con `ExpandMore` e «Chiudi l'assistente», nella stessa posizione. Non disponibile: disabilitato, dentro un `Tooltip` sempre visibile «Assistente non disponibile» (con uno `span` intorno al pulsante disabilitato, che porta anche `aria-describedby` verso il testo del tooltip, perché un pulsante disabilitato non riceve il focus) |
| Pannello su desktop | da `md`: `Paper` fluttuante e **non modale** sopra il pulsante, ancorato in basso a destra, larghezza `theme.layout.assistantPanelWidth` (440 px), altezza `min(theme.layout.assistantPanelMaxHeight, 100dvh − theme.layout.storeHeaderHeight − spazio del pulsante − margine)`, sempre sotto l'header, bordo `divider` ed `elevation`; la pagina sotto resta usabile. Testata e campo restano sempre visibili, scorre solo l'area dei messaggi. Testata: titolo «Assistente di Casa del Silicio» su una riga (titolo `h3` con la variante `h6` in Bodoni, la dimensione di un titolo di card del mockup) e `IconButton` «Chiudi l'assistente» con `Close`; sotto, `Button` testuale «Nuova conversazione» con `Add`, disabilitato a conversazione vuota; `Divider`. Area dei messaggi su `background.default`, che scorre; in fondo il form e la nota |
| Pannello su mobile | sotto `md`: a schermo intero (`Dialog` `fullScreen`), stessa testata, stesso contenuto; il pulsante «Chiudi l'assistente» non c'è, si chiude con la X |
| Conversazione vuota | titolo «Ciao, come posso aiutarti?» (variante `h5` in Bodoni, poco più grande del titolo del pannello come nel mockup), sotto «Chiedimi dei prodotti, della spedizione o dei resi.» in `body2` `text.secondary`; quattro suggerimenti come `Button` outlined a tutta larghezza, testo a sinistra e `ArrowForward` a destra: «Cerco cuffie per viaggiare», «Un regalo sotto i 100 €», «Quanto costa la spedizione?», «Come funzionano i resi?». Un clic invia subito il testo del suggerimento |
| Messaggio dell'utente | bolla a destra su `text.primary` con testo `background.paper`, `borderRadius: 1`, larghezza massima in percentuale del pannello (es. `maxWidth: '85%'`); prima del testo «Tu» solo per gli screen reader |
| Messaggio dell'assistente | etichetta «Assistente» in `caption` `text.secondary` sopra una bolla a sinistra su `background.paper` con bordo `divider`; testo in `body2` con il Markdown leggero (grassetto in `strong`, elenchi puntati e numerati in `List` compatte) |
| Card prodotto citato | sotto la bolla, una per prodotto dell'evento `products`: `Card` orizzontale compatta con miniatura quadrata a sinistra, nome in `subtitle2`, prezzo in Bodoni con accanto l'etichetta di disponibilità con icona (colori della mappa "Disponibilità", mostrata sempre, anche per `available`), in basso "Vedi prodotto" (`Button` testuale verso la pagina prodotto) e "Aggiungi al carrello" (`Button` outlined) |
| Attesa | prima del primo frammento, al posto della bolla: riquadro su `background.paper` con `SearchOutlined` e «Sto preparando la risposta…» in `body2`, `LinearProgress` indeterminata, «Di solito servono 15–20 secondi.» in `caption` `text.secondary` |
| Scrittura | il testo cresce nella bolla con un cursore verticale in fondo; durante attesa e scrittura, sotto la bolla, `Button` outlined «Interrompi» con `StopCircleOutlined`, nella stessa posizione nei due stati |
| Risposta interrotta | dopo «Interrompi»: la bolla con il testo arrivato fino a quel momento, senza card, e sotto «Risposta interrotta.» in `caption` `text.secondary`; se non era arrivato testo, solo «Risposta interrotta.», senza bolla |
| Errori | `Alert` a tutta larghezza al posto della risposta (o sotto il testo parziale), con azione `Button` testuale «Riprova» con `Refresh`: «L'assistente è occupato, riprova tra poco.» (`warning`, `WarningAmberOutlined`); «L'assistente non è disponibile in questo momento.» (`error`, `ErrorOutline`); «La risposta si è interrotta prima della fine.» (`error`, `ErrorOutline`, sotto la bolla con il testo parziale); «Hai fatto troppe domande, riprova tra N secondi.» (`warning`, `ScheduleOutlined`, N fisso, «Riprova» disabilitato finché il tempo non scade; senza `Retry-After` «…riprova tra poco.» con «Riprova» attivo) |
| Domanda fuori tema | la bolla con la frase fissa «Posso aiutarti solo con i prodotti e i servizi di Casa del Silicio.» e sotto di nuovo i quattro suggerimenti |
| Campo della domanda | `TextField` outlined «La tua domanda», placeholder «Scrivi una domanda», contatore «N/500» in `caption` a destra sotto il campo; accanto `IconButton` pieno «Invia» con `Send`, disabilitato con il campo vuoto. Durante attesa e scrittura campo e invio disabilitati con l'helper «L'assistente sta rispondendo.»; dopo un 429 disabilitati con l'helper «Potrai scrivere tra N secondi.», con N che scende |
| Nota | sotto il form, in `caption` `text.secondary`: «Le risposte sono generate da un'AI: prezzi e disponibilità sono quelli delle schede.» |

### Accesso e pagine dai link email

| Caso | Componenti MUI |
|---|---|
| Login | card "Accedi" con sottotitolo, campi Email e Password (con mostra/nascondi), link "Password dimenticata?" allineato a destra, pulsante pieno "Accedi" a tutta larghezza, `Divider` con "oppure", pulsante outlined "Accedi con una passkey" (nascosto senza WebAuthn), in fondo "Non hai un account? Registrati" |
| Credenziali errate | `Alert` `error` sopra i campi con titolo "Credenziali non valide" e testo "Controlla l'email e la password e riprova." |
| Troppi tentativi | `Alert` `warning` sopra i campi con titolo "Troppi tentativi. Riprova tra N secondi" e testo "Nel frattempo puoi scegliere una nuova password o accedere con una passkey." |
| Verifica in due passaggi | card "Verifica in due passaggi" con tre modalità: codice dell'app ("Codice a 6 cifre", `helperText` "Il codice cambia ogni 30 secondi."), codice di recupero ("Codice di recupero", `helperText` "Ogni codice si può usare una sola volta."), passkey; pulsante "Verifica" e link per passare alle altre modalità ("Usa un codice di recupero", "Usa il codice dell'app", "Usa una passkey"), in fondo "Torna all'accesso". Codice errato: `error` sul campo con "Codice non valido" |
| Requisiti della password | sotto il campo della nuova password, elenco di tre righe ("Almeno 10 caratteri", "Lettere maiuscole e minuscole", "Almeno un numero") con icona di spunta `success.main` quando il requisito è soddisfatto e icona neutra altrimenti; ogni riga ha un testo per gli screen reader («requisito soddisfatto» o «requisito non ancora soddisfatto») |
| Registrazione | card "Crea il tuo account": Nome e cognome, Email, Password con i requisiti, Conferma password, nota "Creando l'account confermi di aver letto l'informativa sulla privacy." con link, pulsante "Crea account", "Hai già un account? Accedi" |
| Reset password | card "Scegli una nuova password" con «Per l'account **email**.», Nuova password con i requisiti, Conferma la nuova password; con la doppia autenticazione attiva, sotto un `Divider`, la sezione "Verifica in due passaggi" con il codice e i link "Usa un codice di recupero" e "Usa una passkey"; pulsante "Salva la nuova password" |
| Pagina di esito | card con titolo, testo e uno o due pulsanti: "Controlla la tua email" (verifica: "Invia di nuovo il link", nota sulla cartella spam, "Torna al negozio"; password dimenticata: "Torna all'accesso"), "Email confermata" ("Vai al negozio", "Vai al tuo account"), "Il link è scaduto" ("Invia un nuovo link" oppure "Richiedi un nuovo link" e "Torna all'accesso"), "Email aggiornata" ("Vai al tuo account"), "Richiesta annullata" ("Cambia la password", "Torna al negozio") |

### Area account

| Caso | Componenti MUI |
|---|---|
| Dati personali | card "Dati personali" con "Nome e cognome" e pulsante "Salva le modifiche" |
| Email del profilo | card "Email": indirizzo, `Chip` piccolo "Confermata" (`success`, icona di spunta) oppure "Da confermare" (`warning`), testo "La usi per accedere e per ricevere le email sui tuoi ordini.", pulsante outlined "Cambia email" a destra |
| Cambio email in sospeso | nella card Email, al posto del pulsante, `Alert` `info` con icona della busta (`MailOutlined`), titolo "Cambio email in attesa di conferma", testo «Abbiamo inviato un link a **nuova email**. Finché non lo apri, continuerai a usare {email attuale}.», sotto «Scadenza del link: {data e ora}» in `caption` da `expires_at`, e azione testuale "Annulla la richiesta" |
| Nuova email | `Dialog` aperto dopo la conferma dell'identità: titolo "Cambia email", campo "Nuova email" (`type="email"`, `autoComplete="email"`), pulsanti "Annulla" e "Invia il link" |
| Indirizzi | titolo "Indirizzi" con «Gli indirizzi salvati ti vengono proposti al checkout.»; griglia di card (2 colonne da `md`, 1 su mobile) con nome, via, «CAP città (PR), Italia», telefono; il predefinito ha il bordo `primary.main` spesso 2 e il `Chip` pieno `primary` "Predefinito"; sotto un `Divider` le azioni testuali "Modifica" (`EditOutlined`), "Elimina" (`DeleteOutlined`) e, solo sui non predefiniti, "Imposta come predefinito", con `aria-label` che cita via e città; pulsante outlined "Aggiungi un indirizzo" con `Add` sotto la griglia |
| Nuovo e modifica indirizzo | `Dialog` "Nuovo indirizzo" o "Modifica indirizzo" (a schermo intero sotto `md`) con il form dell'indirizzo del checkout, precompilato in modifica; nella modifica del predefinito la `Checkbox` "Imposta come predefinito" non compare. Snackbar «Indirizzo salvato», «Indirizzo aggiornato», «Indirizzo predefinito aggiornato» |
| Eliminazione di un indirizzo | `Dialog` «Vuoi eliminare questo indirizzo?», «{nome}, {via}, {CAP città (PR), Italia}. Gli ordini già effettuati non cambiano.» e, per il predefinito, «Diventerà predefinito l'indirizzo aggiunto più di recente.»; pulsanti "Mantieni l'indirizzo" (testuale) ed "Elimina l'indirizzo" (pieno, `error`); errore in un `Alert` nel dialog; Snackbar «Indirizzo eliminato» |
| Nessun indirizzo | stato vuoto in una `Card` con `PlaceOutlined`, «Non hai ancora salvato indirizzi», «Aggiungine uno: te lo proporremo al checkout.» e "Aggiungi un indirizzo" |
| Elenco ordini | titolo "I miei ordini" con «Qui trovi lo stato e i dettagli di ogni ordine.»; desktop: `Table` in una `Card` con Numero d'ordine, Data, Articoli, Totale (Bodoni, a destra), Stato (`OrderStatusChip`) e "Dettagli" con `ChevronRight`; mobile: card cliccabili impilate con numero, `Chip` dello stato, «data · N articoli», totale e `ChevronRight`; sotto l'elenco `Pagination` (`shape="rounded"`) solo con più di una pagina |
| Nessun ordine | stato vuoto in una `Card` con `ShoppingBagOutlined`, "Non hai ancora fatto ordini", testo "Quando acquisterai qualcosa, qui troverai lo stato e i dettagli di ogni ordine." e pulsante "Scopri la collezione" |
| Dettaglio ordine | link "Tutti gli ordini" con `ArrowBack`, titolo «Ordine {numero}» con `Chip` dello stato e «Effettuato il … alle …»; due colonne da `md` (8 e 4). Sinistra: card "Prodotti" con miniatura, nome, «Quantità N · {prezzo} cad.», totale della riga e totali. Destra: card "Stato dell'ordine" con una timeline verticale (Pagato, Spedito, Consegnato): passo compiuto con cerchio pieno scuro e `Check`, data e ora e descrizione da `status_history`; passo futuro con cerchio vuoto e il suggerimento («Ti scriveremo quando parte», «Consegna in 24/48 ore dalla spedizione»); per un ordine annullato i passi compiuti e la riga Annullato con `Close` in `error.main`, senza passi futuri; con `can_cancel`, sotto un `Divider`, «Puoi annullare l'ordine finché non viene spedito.» e `Button` outlined "Annulla l'ordine" a tutta larghezza. Poi card "Indirizzo di spedizione" e card "Pagamento" con `CreditCardOutlined` e solo "Carta che termina con {card_last_four}" |
| Annullamento dell'ordine | `Dialog` "Vuoi annullare l'ordine?" con «L'ordine {numero} non verrà spedito e riceverai il rimborso di **{totale}** sulla carta che termina con {cifre}.», nota «Ti invieremo una conferma via email.», pulsanti "Mantieni l'ordine" (testuale) e "Annulla l'ordine" (pieno, `error`); errori (502 `refund_failed`, 422) in un `Alert` nel dialog; Snackbar «Ordine annullato: riceverai il rimborso sulla tua carta.» |

### Sicurezza

| Caso | Componenti MUI |
|---|---|
| Card Password | titolo "Password", "Ultima modifica: {data}" da `password_changed_at`, pulsante outlined "Cambia password" che apre il form nella card: Password attuale, Nuova password con i requisiti, Conferma la nuova password e, con la doppia autenticazione attiva, la sezione "Verifica in due passaggi" ("La doppia autenticazione è attiva: inserisci anche il codice dell'app.", codice e link "Usa un codice di recupero" e "Usa una passkey"); pulsanti "Annulla" e "Salva la nuova password" |
| Doppia autenticazione non attiva | titolo "Doppia autenticazione" con "Disattivata", testo che spiega il secondo passaggio (codice dall'app o passkey, chiesto anche per recuperare la password e per le modifiche importanti), pulsante "Attiva" |
| Attivazione doppia autenticazione | nella stessa card, titolo "Attiva la doppia autenticazione" e `Stepper` in tre passi (Scansiona il codice QR, Verifica il codice, Salva i codici di recupero): QR code con la chiave manuale in `Typography` monospazio e `IconButton` di copia, poi "Annulla" e "Continua"; codice a 6 cifre con "Indietro" e "Verifica"; codici di recupero con "Ho salvato i codici" |
| Doppia autenticazione attiva | titolo con `Chip` "Attiva" (`success`), "Attiva dal {data}"; righe con icona in `Avatar`: "App di autenticazione" ("Configurata il {data}") e "Codici di recupero" ("Codici di recupero rimasti: N di M", pulsante outlined "Genera nuovi codici"); sotto un `Divider`, azione testuale "Disattiva" |
| Codici di recupero | titolo "Salva questi codici in un posto sicuro: non li mostreremo più", testo sull'uso, griglia di codici in `Typography` monospazio, pulsanti "Copia" e "Scarica" |
| Passkey | card "Passkey" con il testo "Con una passkey accedi con l'impronta, il volto o il PIN del dispositivo, senza digitare la password.", seguito da "Vale anche come secondo fattore." solo con la doppia autenticazione attiva; `List` con icona della chiave in `Avatar`, nome, «Creata il … · Ultimo utilizzo …» e azioni testuali "Rinomina" e "Rimuovi"; vuoto: "Non hai ancora aggiunto passkey."; pulsante outlined "Aggiungi una passkey" con icona `Add`; nota con icona informativa "Ti consigliamo di registrarne almeno due, su dispositivi diversi: se ne perdi uno potrai comunque accedere." |
| Rinomina passkey | `Dialog` con campo "Nome della passkey", pulsanti "Annulla" e "Salva" |
| Aggiunta di una passkey | "Aggiungi una passkey" apre prima un `Dialog` "Aggiungi una passkey" con «Dai un nome alla passkey, poi il dispositivo ti chiederà l'impronta, il volto o il PIN.», campo obbligatorio "Nome della passkey" (helper «Ad esempio iPhone di Giulia») e pulsanti "Annulla" e "Continua"; poi la conferma dell'identità se serve e la cerimonia del browser. Errori della registrazione in un `Alert` nel dialog; Snackbar «Passkey aggiunta» |
| Conferme prima della conferma dell'identità | "Genera nuovi codici", "Disattiva" e "Rimuovi" aprono prima un `Dialog` di conferma, poi la richiesta (il 423 apre `ConfirmIdentityDialog` solo se la conferma manca o è scaduta): «Vuoi generare nuovi codici di recupero?» / «I codici che hai ora smetteranno di funzionare.» / "Mantieni i codici attuali" e "Genera nuovi codici"; «Vuoi disattivare la doppia autenticazione?» / «All'accesso ti chiederemo solo la password o una passkey. I codici di recupero smetteranno di funzionare e le altre sessioni verranno chiuse.» / "Mantieni la doppia autenticazione" e "Disattiva la doppia autenticazione" (pieno, `error`); «Vuoi rimuovere la passkey «{nome}»?» / «Non potrai più usarla per accedere.» / "Mantieni la passkey" e "Rimuovi la passkey" (pieno, `error`). Errori in un `Alert` nel dialog; una conferma dell'identità annullata lascia il dialog aperto senza errori |
| Attività recente | card "Attività recente" con `List`: icona del tipo di evento in `Avatar`, etichetta dall'API, sotto «[nome della passkey ·] dispositivo · IP {indirizzo}» in `caption`, data e ora a destra su desktop e sotto su mobile; righe separate da `Divider`; in fondo pulsante testuale "Mostra altre attività" finché ci sono altre pagine |
| Conferma dell'identità | `ConfirmIdentityDialog`: titolo "Conferma la tua identità" con `IconButton` di chiusura, descrizione dell'operazione (es. «Per disattivare la doppia autenticazione, conferma che sei tu.»), campo Password con mostra/nascondi; con la doppia autenticazione attiva, un `Divider`, "Secondo fattore" e un `ToggleButtonGroup` a tutta larghezza App / Codice di recupero / Passkey (Passkey solo se l'utente ne ha), con il campo del codice e il suo `helperText` ("Lo trovi nell'app di autenticazione.", "Ogni codice si può usare una sola volta. Te ne restano N.") oppure il pulsante "Usa la passkey" con "Il dispositivo ti chiederà l'impronta, il volto o il PIN."; pulsanti "Annulla" e "Conferma" |
| Sezione Elimina account | dal mockup `05-assistente-account.pdf`: in fondo alla pagina Sicurezza, dopo un `Divider` che la separa dalle altre card; `Card` con titolo `h3` «Elimina account», testo «Cancelli i tuoi dati, gli indirizzi e l'accesso. L'operazione non si può annullare.» e a destra `Button` outlined `error` «Elimina l'account» con `DeleteOutlined` (su mobile a tutta larghezza sotto il testo). Non compare per un admin |
| Conferma dell'eliminazione | `Dialog` «Vuoi eliminare il tuo account?» (titolo in Bodoni): «Cosa viene cancellato» in `subtitle2` con un elenco di righe con icona, «I tuoi dati personali» (`PersonOutlineOutlined`), «Gli indirizzi salvati» (`PlaceOutlined`), «Il carrello» (`ShoppingBagOutlined`), «L'accesso: password, passkey e doppia autenticazione» (`KeyOutlined`); «Cosa conserviamo» in `subtitle2` con un `Alert` `info` dal titolo «Gli ordini, per 10 anni» e testo «È un obbligo di legge: i documenti di vendita vanno conservati.»; `Checkbox` «Ho capito che l'operazione non si può annullare»; pulsanti «Annulla» (testuale) ed «Elimina l'account» (pieno, `error`, disabilitato finché la checkbox non è spuntata, con `CircularProgress` durante l'invio). Su mobile il dialog non è a schermo intero |
| Ordini in corso | dopo un 409 `account_has_open_orders` il dialog cambia contenuto: titolo «Per ora non puoi eliminare l'account», `Alert` `warning` con il messaggio ricevuto dall'API («Hai ordini in corso: potrai eliminare l'account dopo la consegna, oppure annullandoli.»), pulsanti «Chiudi» (testuale) e «I miei ordini» (pieno, con `ArrowForward`) |

Icone degli eventi di sicurezza: `Login` per accessi e uscite, `LockOutlined` per password, `KeyOutlined` per passkey, `VerifiedUserOutlined` per doppia autenticazione e codici, `MailOutlined` per email, `WarningAmberOutlined` per tentativi falliti.

### Generali

| Caso | Componenti MUI |
|---|---|
| Codice di verifica | `TextField` numerico con `inputMode="numeric"`, `autoComplete="one-time-code"` e link "Usa un codice di recupero" |
| Tabelle admin | `Table` dentro una `Card` informativa, intestazioni in `body2` `text.secondary`, importi e numeri allineati a destra, `TablePagination` in fondo con 10, 25 e 50 righe |
| Stato ordine | `OrderStatusChip` di `components/data-display`: `Chip` piccolo con `variant="soft"`, l'etichetta ricevuta dall'API e il colore della mappa sotto; lo usano conferma d'ordine, ordini del cliente e admin |
| Conferme distruttive | `Dialog` con titolo in forma di domanda, conseguenze nel testo e pulsanti che nominano l'azione per esteso |
| Esito di un'azione | `Snackbar` con `Alert` |
| Caricamento dati | `Skeleton` con la forma del contenuto |
| Azione in corso | `CircularProgress` dentro il pulsante |
| Errore di caricamento | `Alert` con severità `error` e azione "Riprova" |
| Stato vuoto | icona, `Typography` e `Button` d'azione |

### Admin

Dal mockup `04-admin.pdf`. I testi indicati sono vincolanti.

| Caso | Componenti MUI |
|---|---|
| Card indicatore | `Card` con etichetta in `body2`, valore grande (il fatturato in Bodoni come i prezzi, i conteggi in `h4`) e didascalia in `caption` `text.secondary`; griglia di 2 colonne su mobile e 4 da `md` |
| Indicatori della dashboard | "Fatturato del mese" («{Mese anno} · solo ordini pagati, esclusi gli annullati»), "Ordini del mese" («{Mese anno} · tutti gli stati»), "Ordini da spedire" («Pagati, in attesa di spedizione» con `ChevronRight`, card cliccabile verso gli ordini pagati), "Prodotti con scorte basse" (valore in `warning.main` con `WarningAmberOutlined`, «N pezzi o meno, esauriti compresi» con N dalle impostazioni admin); su mobile le didascalie accorciate del mockup («Pagati, da spedire», «N pezzi o meno») e il titolo "Scorte basse" |
| Ordini per stato | `Card` "Ordini per stato" con «{Mese anno}» in `body2` `text.secondary`; desktop: riquadri cliccabili con `Chip` dello stato e conteggio a destra; mobile: `List` con `Chip`, conteggio e `ChevronRight`; ogni voce porta agli ordini filtrati per stato e mese di pagamento. Solo Pagato, Spedito, Consegnato e Annullato |
| Ultimi ordini | `Card` con titolo e azione "Vedi tutti" con `ArrowForward`; desktop: `Table` con Ordine (link sottolineato), Cliente, Data, Totale, Stato (`Chip`); mobile: `List` con numero, `Chip`, «Nome · data» in `caption` e totale; vuoto: «Non ci sono ancora ordini pagati.» |
| Scorte basse | `Card` con titolo e «N pezzi o meno» a destra; `List` con miniatura quadrata, nome, etichetta di `stock_status` colorata e pulsante testuale "Modifica"; vuoto: «Nessun prodotto sotto la soglia.» |
| Barra dei filtri | `Stack` orizzontale da `md`, impilato su mobile: `TextField` di ricerca con `SearchOutlined` all'inizio e `IconButton` "Cancella la ricerca" con `Close` quando è pieno, `Select` con label; a destra il pulsante principale (`Button` pieno con `Add`) o il conteggio dei risultati |
| Elenco prodotti | filtri "Cerca per nome o SKU" (placeholder «Es. Cuffie Aria o CDS-AUD-001»), "Categoria" (Tutte + categorie), "Stato" (Tutti, Attivo, Disattivato), pulsante "Nuovo prodotto"; colonne: miniatura, Nome (`subtitle2`), SKU (`text.secondary`), Categoria, Prezzo, Stock, Stato, Azioni. Stock sotto soglia con `WarningAmberOutlined` e numero in `warning.main`, esaurito con `ErrorOutline` e «Esaurito» in `error.main`, entrambi con testo per gli screen reader («, scorte basse»). `Chip` "Attivo" o "Disattivato". `IconButton` «Modifica {nome}» (`EditOutlined`) ed «Elimina {nome}» (`DeleteOutlined`). Su mobile, card impilate come l'elenco ordini (il mockup non le mostra) |
| Componenti condivisi dell'admin | in `features/admin/components`: `AdminSectionCard` (card con titolo `h3` e contenuto a destra), `AdminTableHead`, `AdminTableSkeleton`, `AdminEmptyState`, `SearchField`, `FilterSelect`, `ListPagination` (`TablePagination` su desktop, "Precedenti" / «1–10 di 48» / "Successivi" su mobile), `ConfirmDialog`, `UnsavedChangesDialog`, `BackLink`, `OrderNumberLink`, `StockStatusLabel` |
| Caricamento di una tabella admin | intestazioni visibili e 10 righe di `Skeleton` con la forma delle celle (rettangolo per la miniatura, cerchi per le azioni) |
| Tabella admin senza risultati | nella `Card` della tabella, sotto le intestazioni: icona in `Avatar` (`SearchOutlined` per i prodotti, `LocalMallOutlined` per gli ordini), titolo `h3` "Nessun prodotto trovato" o "Nessun ordine trovato", testo «Nessun prodotto corrisponde a «{ricerca}». Controlla il nome o lo SKU, oppure azzera i filtri.» o «Nessun ordine corrisponde a «{ricerca}». Controlla il numero d'ordine o l'email, oppure azzera i filtri.» (senza ricerca: «Nessun prodotto corrisponde ai filtri.», «Nessun ordine corrisponde ai filtri.»), `Button` outlined "Azzera filtri" |
| Eliminazione di un prodotto | `Dialog` «Eliminare «{nome}»?», «Il prodotto sparisce dal catalogo e dal negozio. Gli ordini già ricevuti che lo contengono restano invariati. L'operazione non si può annullare.», pulsanti "Mantieni il prodotto" (testuale) ed "Elimina il prodotto" (pieno, `error`) |
| Form prodotto | link "Tutti i prodotti" con `ArrowBack` e, in modifica, «Ultima modifica il {data} alle {ora}» a destra; due colonne da `md` (8 e 4), impilate su mobile. Sinistra: card "Informazioni" (Nome *, SKU * con helper «Lettere maiuscole, numeri e trattini», Categoria * `Select`, Descrizione breve * con helper «Compare sotto il nome nella scheda prodotto.» e contatore «N/160» a destra, Descrizione lunga multilinea), card "Scheda tecnica", card "Immagini". Destra: card "Prezzo e disponibilità" (Prezzo * con «€» come `InputAdornment` finale, `inputMode="decimal"` e helper «IVA inclusa»; Stock * con «pezzi» come adornment e helper «Con N pezzi o meno il prodotto entra tra le scorte basse.»; `Divider`; `Switch` "Visibile nel negozio" con helper «Il prodotto compare nel negozio e si può acquistare.» o «Il prodotto resta in archivio e non compare nel negozio.»). Nuovo prodotto: stock 0 e visibilità disattivata |
| Scheda tecnica | testo «Le righe compaiono nella scheda prodotto nell'ordine indicato qui.»; intestazioni Etichetta e Valore; per riga due `TextField` e gli `IconButton` «Sposta su» (`ArrowUpward`), «Sposta giù» (`ArrowDownward`), disabilitati agli estremi, ed «Elimina la riga» (`DeleteOutlined`); pulsante testuale "Aggiungi una riga" con `Add` |
| Immagini del prodotto | testo «La prima immagine è la copertina: compare nelle card del negozio e apre la galleria.»; area di caricamento con bordo tratteggiato `divider` (evidenziata con `primary.main` durante il trascinamento), `CloudUploadOutlined`, «Trascina qui le immagini», «JPG, PNG o WebP fino a 5 MB · proporzione 4:5 su fondo neutro», `Button` outlined "Scegli i file"; griglia di card da 3 colonne: immagine 4:5, `Chip` "Copertina" con `Star` sulla prima (bordo marcato), `TextField` "Testo alternativo", `IconButton` «Sposta prima» (`ArrowBack`) e «Sposta dopo» (`ArrowForward`), disabilitati agli estremi, ed «Elimina l'immagine»; senza immagini: «Non hai ancora aggiunto immagini. Senza immagini il prodotto compare nel negozio con un segnaposto.»; nel nuovo prodotto, al posto dell'area: «Potrai aggiungere le immagini dopo il primo salvataggio.»; eliminazione con `Dialog` «Eliminare l'immagine?», «L'immagine sparisce subito dal prodotto e dal negozio.», "Mantieni l'immagine" ed "Elimina l'immagine" |
| Errori del form prodotto | `Alert` `error` in cima con titolo «Il prodotto non è stato salvato» e testo «Correggi i N campi evidenziati e salva di nuovo.» («Correggi il campo evidenziato e salva di nuovo.» se è uno); errori sui campi in `helperText` |
| Barra di salvataggio | barra fissa in fondo all'area del contenuto, bianca con bordo superiore: a sinistra «Hai modifiche non salvate» in `body2` `text.secondary` quando il form è modificato, a destra "Annulla" (outlined) e "Salva" (pieno, con `CircularProgress` durante l'invio) |
| Modifiche non salvate | `Dialog` «Uscire senza salvare?», «Le modifiche che non hai salvato andranno perse.», pulsanti "Resta sulla pagina" (testuale) ed "Esci senza salvare" (pieno) |
| Elenco categorie | a sinistra «N categorie · una categoria con prodotti non si può eliminare», a destra "Nuova categoria"; `Table` con Nome (`subtitle2`), Slug (`text.secondary`), Descrizione, Prodotti (a destra), Azioni (`IconButton` «Modifica {nome}» ed «Elimina {nome}»); senza paginazione. Elimina disabilitato dentro un `Tooltip` (con uno `span` intorno al pulsante disabilitato): «Contiene prodotti: spostali prima di eliminarla», oppure «È collegata a prodotti eliminati: non si può eliminare» quando `products_count` è 0 ma `can_delete` è false; senza categorie, «Non ci sono ancora categorie.» in `body2` `text.secondary` |
| Nuova e modifica categoria | `Dialog` "Nuova categoria" o "Modifica categoria": Nome *, Descrizione multilinea, pulsanti "Annulla" e "Salva". In "Nuova categoria" l'helper del nome è «L'indirizzo della categoria si crea dal nome e non si potrà modificare.»; in "Modifica categoria" c'è il campo Slug `readOnly` con helper «Compare nell'indirizzo del catalogo: /products?category={slug}. Non si può modificare.» |
| Eliminazione di una categoria | `Dialog` «Eliminare la categoria «{nome}»?», «La categoria non contiene prodotti. Sparisce dal menu del negozio e dai filtri del catalogo. L'operazione non si può annullare.», pulsanti "Mantieni la categoria" ed "Elimina la categoria" (pieno, `error`) |
| Elenco ordini | filtri "Stato" (Tutti e i cinque stati), "Cerca per numero d'ordine o email" (placeholder «Es. ORD-2026-000042»), `Switch` "Solo pagamenti non riusciti", «N ordini» a destra («1 ordine» al singolare); con `paid_month` attivo, tra i filtri e la tabella un `Chip` outlined «Pagati a {Mese anno}» (es. «Pagati a Settembre 2026», da `formatMonthYear`) con `onDelete` e «Rimuovi il filtro del mese» come `titleAccess` dell'icona di rimozione (`SvgIcon` ignora un `aria-label` perché si nasconde con `aria-hidden`) (il `Chip` resta nominato dal suo testo); colonne Ordine (link sottolineato), Cliente (nome e sotto l'email in `caption`), Data, Articoli, Totale, Stato, `IconButton` «Dettagli dell'ordine {numero}» con `ChevronRight` (il numero rende unico il nome di ogni riga, come negli ordini del cliente). Per un tentativo fallito, sotto il `Chip` "Annullato", «Pagamento non riuscito» in `caption` `error.main` con `ErrorOutline`. Mobile: filtri impilati, card con numero e `Chip`, «Nome · email», «data · N articoli» («1 articolo» al singolare) e totale, riga del pagamento non riuscito; paginazione con "Precedenti", «1–10 di 48» e "Successivi" |
| Dettaglio ordine admin | link "Tutti gli ordini" con `ArrowBack`; numero in `h2` Bodoni con `Chip` dello stato; «Effettuato il {data} alle {ora} · N articoli» («1 articolo» al singolare). Due colonne da `md` (8 e 4). Sinistra: card "Prodotti" con «Prezzi al momento dell'acquisto», tabella Prodotto (miniatura, nome, SKU), Prezzo unitario, Quantità, Totale, poi Subtotale, "Spedizione standard" ("Gratuita" o importo), Totale con «IVA inclusa» e importo in Bodoni; card "Cronologia degli stati". Destra: card "Azioni", "Cliente" (nome, email come link `mailto:`, telefono), "Indirizzo di spedizione" (con «Spedizione standard, consegna in 24/48 ore»), "Pagamento" |
| Cronologia degli stati | elenco verticale con icona in un cerchio collegata da una linea: `Check` su fondo scuro per i passi, `Close` in `error.main` per la riga Annullato; etichetta dello stato in `subtitle2`, descrizione in `caption` `text.secondary`, data e ora a destra |
| Azioni sull'ordine | stato Pagato: "Segna come spedito" (pieno, `LocalShippingOutlined`) e "Annulla l'ordine" (outlined, `error`), nota «Dopo la spedizione l'ordine non si può più annullare.»; Spedito: "Segna come consegnato" (pieno, `CheckCircleOutline`), nota «Un ordine spedito non si può annullare. Consegnato è lo stato finale.»; altrimenti «Nessuna azione disponibile per questo ordine.» |
| Annullamento dall'admin | `Dialog` «Annullare l'ordine {numero}?»: riga con `CreditCardOutlined` «**Rimborso di {totale}** sulla carta che termina con {cifre}.», riga con `Inventory2Outlined` «**Scorte ripristinate:** {Prodotto} +{quantità}, …», testo «L'ordine passa ad Annullato. L'operazione non si può annullare.», pulsanti "Mantieni l'ordine" e "Annulla l'ordine" (pieno, `error`); se il rimborso fallisce, `Alert` `error` nel dialog con il messaggio ricevuto |
| Pagamento dell'ordine | card "Pagamento": `CreditCardOutlined` con «Carta che termina con {cifre}», poi righe etichetta/valore Esito (con icona e colore dell'esito), Importo, Data e, per un pagamento rifiutato, Motivo (il `reason` della riga di cronologia Annullato); senza nessun pagamento, «Nessun pagamento registrato.» in `body2` `text.secondary` |
| Pagamento rifiutato | `Alert` `error` sotto l'intestazione, titolo «Pagamento non riuscito», testo «{motivo} (carta che termina con {cifre}). Questo tentativo non è visibile al cliente: nel suo account l'ordine non compare. Le scorte sono state ripristinate e non c'è niente da spedire né da rimborsare.»; `Chip` "Annullato"; nessuna azione |

### Colori semantici

Il frontend possiede solo le mappe dei colori; le etichette arrivano dall'API.

**Chip di stato con fondo tenue:** la variante personalizzata `soft` di `MuiChip`, definita una sola volta in `src/theme/components.js` per `secondary`, `info`, `success`, `warning` ed `error`, usa fondo `X.light` e testo (e icona) `X.main`; quando `X.main` non raggiunge il contrasto 4,5:1 sul fondo (calcolato con `getContrastRatio` alla creazione del tema) il testo è `X.dark`. Con la palette attuale vale solo per `secondary` (Spedito), che usa `secondary.dark`. La usano i chip di stato d'ordine e quelli di stato del profilo («Confermata», «Da confermare»); mai colori del chip negli `sx`. `src/theme/index.test.jsx` verifica il contrasto di ogni stato d'ordine e di disponibilità.

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

| Stato del prodotto (admin) | Colore del `Chip` |
|---|---|
| attivo | `success` |
| disattivato | `default` |

| Scorte (admin, `stock_status`) | Colore dell'etichetta e dell'icona |
|---|---|
| `available` | `text.primary`, senza icona |
| `low_stock` | `warning.main` |
| `out_of_stock` | `error.main` |

| Esito del pagamento | Colore e icona |
|---|---|
| `succeeded` | `success.main`, `CheckCircleOutline` |
| `failed` | `error.main`, `ErrorOutline` |
| `refunded` | `text.secondary`, `Replay` |

| Stato di sicurezza | Colore del `Chip` |
|---|---|
| email confermata, doppia autenticazione attiva | `success` |
| email da confermare | `warning` |

## Icone

Solo `@mui/icons-material`, nella variante outlined dove esiste. Con la versione installata (MUI 9) `DeleteOutline`, `MailOutline`, `PersonOutline`, `ErrorOutline` e `CheckCircleOutline` non esistono come moduli: si usano `DeleteOutlined`, `MailOutlined`, `PersonOutlineOutlined`, `ErrorOutlineOutlined` e `CheckCircleOutlineOutlined` (importati con il nome del mockup, es. `import ErrorOutline from '@mui/icons-material/ErrorOutlineOutlined'`).

## Accessibilità

- Ogni `IconButton` ha un `aria-label` in italiano.
- Ogni immagine ha un `alt` descrittivo; il QR code ha un `alt` e accanto la chiave testuale per l'inserimento manuale.
- Ogni campo ha una `label`; gli errori stanno in `helperText`.
- Campi password con `autoComplete` corretto (`current-password`, `new-password`) e pulsante per mostrare o nascondere il testo.
- I requisiti della password non si comunicano solo con il colore dell'icona: ogni riga ha il testo per gli screen reader.
- Un solo `h1` per pagina (`component="h1"`) e gerarchia dei titoli coerente.
- Contrasto della palette conforme a WCAG AA.
- Il focus resta sempre visibile; i dialog spostano il focus sul primo campo.
- Il colore non è mai l'unica informazione: stati e disponibilità hanno sempre anche l'etichetta testuale.
- Assistente: il pannello ha `role="dialog"` con `aria-labelledby` sul titolo (su desktop senza `aria-modal`, perché non è modale); all'apertura il focus va sul campo della domanda, alla chiusura torna al pulsante; Esc chiude il pannello. L'area dei messaggi **non** è una regione live e ha `aria-busy="true"` durante attesa e scrittura, così i frammenti dello streaming non vengono letti; una regione separata `aria-live="polite"`, nascosta alla vista, riceve solo il testo della risposta completa, «Risposta interrotta.» e i messaggi d'errore. L'helper «Potrai scrivere tra N secondi.» non sta in una regione live, altrimenti verrebbe annunciato ogni secondo. Focus durante la conversazione: all'invio va su «Interrompi»; quando il campo si riattiva (fine della risposta, interruzione, fine del conto alla rovescia del 429) torna sul campo; dopo un errore va sull'azione «Riprova» (o «Nuova conversazione» dopo un 422), sul campo se l'azione è disabilitata, e sull'`Alert` (`tabIndex={-1}`) se sono disabilitati entrambi, come durante il blocco di un 429; dopo «Nuova conversazione» nella testata va sul campo, o sul titolo del pannello (`tabIndex={-1}`) se il campo è bloccato da un 429. Il focus si sposta solo se è già dentro il pannello (o sul `body`), mai mentre l'utente sta usando la pagina sotto il pannello non modale. Il pulsante «Chiedi all'assistente» ha `aria-expanded`.

## Da Claude Design al codice

1. Estrai i token del design (colori, font, raggi, spaziature, ombre) e mappali nei moduli del tema.
2. Associa ogni elemento del mockup a un componente MUI esistente.
3. Non copiare CSS o markup HTML dell'export: il design è un riferimento visivo, l'implementazione è MUI.
4. Se un elemento non ha un equivalente MUI, componilo con componenti MUI e `sx`, e segnalalo nel piano.
5. Dove il mockup e una decisione approvata divergono, vale la decisione:
   - l'ordinamento predefinito è "Novità" (il mockup mostra "Rilevanza")
   - i correlati sono della stessa categoria
   - il campo "Paese" dell'indirizzo non è modificabile (il mockup mostra un select)
   - nel dettaglio ordine il pagamento mostra solo "Carta che termina con …": intestatario e scadenza non vengono salvati (il mockup mostra «Giulia Ferri · scadenza 08/29»)
   - il cambio email usa, dopo la conferma dell'identità, un `Dialog` con il campo "Nuova email", che il mockup non mostra
   - l'attività recente ha il pulsante "Mostra altre attività", che il mockup non mostra
   - nella card Passkey la frase "Vale anche come secondo fattore." compare solo con la doppia autenticazione attiva (il mockup la mostra con la doppia autenticazione disattivata)
   - la descrizione della conferma prima del cambio email è «Prima di cambiare l'email, conferma che sei tu.» (il mockup cita solo la password, che non basta con la doppia autenticazione attiva)
   - un pagamento rifiutato porta l'ordine ad Annullato con ripristino delle scorte: `Chip` "Annullato" e `Alert` "Pagamento non riuscito" con il motivo (il mockup `04-admin.pdf` mostra "In attesa" e la riga di cronologia «lo stato resta In attesa»)
   - nella dashboard "Ordini per stato" non ha il riquadro "In attesa", perché conta solo ordini pagati almeno una volta, e i conteggi sommano a "Ordini del mese" (il mockup mostra "In attesa 3")
   - l'indirizzo del catalogo di una categoria è `/products?category={slug}` e lo slug si genera dal nome e non si modifica: in modifica è in sola lettura, in creazione non compare (il mockup mostra `/categorie/scrivania` e un campo slug modificabile)
   - nell'`AppBar` admin sotto il nome c'è l'email (il mockup scrive «Amministratrice», un'etichetta del ruolo non conosce il genere della persona)
   - la descrizione breve del prodotto è obbligatoria e ha l'asterisco (il mockup non lo mostra)
   - nel nuovo prodotto le immagini si aggiungono solo dopo il primo salvataggio (il mockup mostra l'area di caricamento anche nel nuovo prodotto)
   - il numero di pezzi delle scorte basse nei testi della dashboard e del form arriva dalle impostazioni admin, non è scritto nel frontend
   - l'amministratrice demo è Marta Lenzi (marta.lenzi@esempio.it), dai dati demo della skill `ecommerce-domain` (il mockup `05-assistente-account.pdf` scrive «Marta Bianchi»)
   - l'attesa dell'assistente dice «Sto preparando la risposta…» (il mockup scrive «Sto cercando nel catalogo…», ma l'indicatore compare anche per le domande su spedizione e resi, dove non si cerca nel catalogo, e il frontend non sa se il modello usa un tool)
   - le card dei prodotti citati dall'assistente sono un componente compatto della feature, che riusa etichetta e colori della disponibilità e l'aggiunta al carrello del catalogo
   - per un admin il pulsante dell'assistente non compare e la sezione «Elimina account» è nascosta (il mockup non mostra la pagina Sicurezza dell'admin; l'API risponde 403)
   - i suggerimenti dopo una domanda fuori tema compaiono solo quando la risposta completa, senza marcatori e spazi iniziali, inizia con la frase fissa del rifiuto (anche se il modello aggiunge un invito) (regola nella skill `react-architecture`)
   - il catalogo del mockup `05-assistente-account.pdf` mostra ancora «Rilevanza»: vale «Novità»
   - l'elenco «I miei ordini» non mostra mai «In attesa» (il mockup `03-account.pdf` sì): il cliente vede solo ordini pagati almeno una volta
   - i chip di stato hanno fondo tenue e testo scuro come nel mockup, ma «Spedito» usa `secondary.dark` per il contrasto (sezione "Colori semantici")
   - aggiunta e modifica di un indirizzo, e la sua eliminazione, si fanno in un `Dialog`, che il mockup non mostra
   - "Genera nuovi codici", "Disattiva" e "Rimuovi" (passkey) chiedono prima un `Dialog` di conferma esplicito, che il mockup non mostra: entro 10 minuti da un'altra conferma dell'identità il 423 non arriva e l'azione partirebbe con un solo clic
   - il nome di una nuova passkey si chiede in un `Dialog` prima della cerimonia del browser (il mockup non mostra come si sceglie il nome)
