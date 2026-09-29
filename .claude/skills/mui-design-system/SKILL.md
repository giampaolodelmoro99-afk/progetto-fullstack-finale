---
name: mui-design-system
description: Design system MUI del progetto — organizzazione del tema, font, regole vincolanti di stile con la prop sx e i token del tema, voce e contenuti, layout di negozio, accesso, area account e admin, pattern dei componenti per negozio, acquisto, account, sicurezza e admin, dialog di conferma, accessibilità e traduzione dei mockup di Claude Design in componenti MUI. Usala per qualsiasi lavoro visivo in frontend/.
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
  - etichette degli eventi di sicurezza (es. Accesso effettuato, Password cambiata, Passkey aggiunta) e del dispositivo (es. «Chrome su macOS», «Dispositivo sconosciuto»)
- Nei dialog di conferma i pulsanti dicono l'azione per esteso, così non sono ambigui: «Mantieni l'ordine» e «Annulla l'ordine», mai un «Annulla» da solo accanto a un'azione che si chiama anch'essa annullamento.

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
- Menu account nell'header, dal mockup `03-account.pdf`:
  - ospite: l'`IconButton` account (`aria-label` "Accedi") porta a `/login`
  - autenticato: l'`IconButton` (`aria-label` "Il tuo account") apre un `Menu` ancorato a destra, con in testa nome (`subtitle2`) ed email (`caption`, `text.secondary`), un `Divider`, le voci Profilo, Indirizzi, I miei ordini, Sicurezza con `ListItemIcon`, un `Divider` e la voce Esci
  - icone outlined: `PersonOutline` (Profilo), `PlaceOutlined` (Indirizzi), `Inventory2Outlined` (I miei ordini), `ShieldOutlined` (Sicurezza), `Logout` (Esci)
- Area account, dal mockup `03-account.pdf`:
  - resta dentro il layout del negozio (header, navigazione e footer)
  - intestazione con `h1` "Il tuo account" e sotto «Nome · email» in `body2` `text.secondary`
  - desktop: colonna sinistra con `List` di navigazione (`ListItemButton` con le stesse icone del menu account; la voce attiva ha lo sfondo evidenziato), un `Divider` e Esci; a destra il contenuto
  - mobile: `Tabs` scorrevoli Profilo, Indirizzi, I miei ordini, Sicurezza sotto l'intestazione; Esci resta nel `Drawer`
  - ogni pagina ha un titolo `h2` con un sottotitolo in `body2` (es. "Sicurezza" / "Gestisci la password, la doppia autenticazione e i dispositivi con cui accedi."), poi le sezioni in `Card` informative con titolo `h3` in Bodoni
  - su mobile le azioni delle card (es. "Cambia password", "Genera nuovi codici") vanno a tutta larghezza sotto il testo
- Accesso e pagine raggiunte dai link email, dal mockup `03-account.pdf`: dentro il layout del negozio (header, navigazione e footer visibili), una `Card` centrata con larghezza massima contenuta, titolo `h1`, testo introduttivo, form o esito, e link secondari in fondo.
- Admin: `Drawer` permanente su desktop e temporaneo su mobile, con `AppBar` in alto.

## Pattern dei componenti

### Negozio e acquisto

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
| Email non verificata | `Alert` persistente in cima alla pagina (non nelle pagine di accesso e dei link email), «Conferma il tuo indirizzo email per completare gli acquisti. Ti abbiamo inviato un link a {email}.», con azione "Invia di nuovo l'email" |

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
| Cambio email in sospeso | nella card Email, al posto del pulsante, `Alert` `info` con icona della busta, titolo "Cambio email in attesa di conferma", testo «Abbiamo inviato un link a **nuova email**. Finché non lo apri, continuerai a usare {email attuale}.» e azione testuale "Annulla la richiesta" |
| Nuova email | `Dialog` aperto dopo la conferma dell'identità: titolo "Cambia email", campo "Nuova email" (`type="email"`, `autoComplete="email"`), pulsanti "Annulla" e "Invia il link" |
| Indirizzi | griglia di card con nome, indirizzo, telefono, `Chip` "Predefinito", azioni "Modifica", "Elimina" e "Imposta come predefinito"; pulsante "Aggiungi un indirizzo" |
| Elenco ordini | desktop: `Table` con Numero d'ordine, Data, Articoli, Totale, Stato (`Chip`) e "Dettagli"; mobile: card impilate con numero, `Chip` dello stato, «data · N articoli» e totale |
| Nessun ordine | stato vuoto "Non hai ancora fatto ordini", testo "Quando acquisterai qualcosa, qui troverai lo stato e i dettagli di ogni ordine." e pulsante "Scopri la collezione" |
| Dettaglio ordine | link "Tutti gli ordini", titolo «Ordine {numero}» con `Chip` dello stato e «Effettuato il … alle …»; card "Stato dell'ordine" con una timeline verticale (Pagato, Spedito, Consegnato) con data per i passi compiuti e suggerimento per quelli futuri; card "Prodotti" con righe e totali; card "Indirizzo di spedizione"; card "Pagamento" con solo "Carta che termina con {card_last_four}"; pulsante "Annulla l'ordine" quando l'ordine è annullabile |
| Annullamento dell'ordine | `Dialog` "Vuoi annullare l'ordine?" con numero, importo del rimborso e ultime cifre della carta, nota sull'email di conferma, pulsanti "Mantieni l'ordine" (testuale) e "Annulla l'ordine" (pieno) |

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
| Attività recente | card "Attività recente" con `List`: icona del tipo di evento in `Avatar`, etichetta dall'API, sotto «[nome della passkey ·] dispositivo · IP {indirizzo}» in `caption`, data e ora a destra su desktop e sotto su mobile; righe separate da `Divider`; in fondo pulsante testuale "Mostra altre attività" finché ci sono altre pagine |
| Conferma dell'identità | `ConfirmIdentityDialog`: titolo "Conferma la tua identità" con `IconButton` di chiusura, descrizione dell'operazione (es. «Per disattivare la doppia autenticazione, conferma che sei tu.»), campo Password con mostra/nascondi; con la doppia autenticazione attiva, un `Divider`, "Secondo fattore" e un `ToggleButtonGroup` a tutta larghezza App / Codice di recupero / Passkey (Passkey solo se l'utente ne ha), con il campo del codice e il suo `helperText` ("Lo trovi nell'app di autenticazione.", "Ogni codice si può usare una sola volta. Te ne restano N.") oppure il pulsante "Usa la passkey" con "Il dispositivo ti chiederà l'impronta, il volto o il PIN."; pulsanti "Annulla" e "Conferma" |

Icone degli eventi di sicurezza: `Login` per accessi e uscite, `LockOutlined` per password, `KeyOutlined` per passkey, `VerifiedUserOutlined` per doppia autenticazione e codici, `MailOutline` per email, `WarningAmberOutlined` per tentativi falliti.

### Generali

| Caso | Componenti MUI |
|---|---|
| Codice di verifica | `TextField` numerico con `inputMode="numeric"`, `autoComplete="one-time-code"` e link "Usa un codice di recupero" |
| Tabelle admin | `Table` con `TablePagination` |
| Stato ordine | `Chip` con l'etichetta ricevuta dall'API e il colore della mappa sotto |
| Conferme distruttive | `Dialog` con titolo in forma di domanda, conseguenze nel testo e pulsanti che nominano l'azione per esteso |
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

| Stato di sicurezza | Colore del `Chip` |
|---|---|
| email confermata, doppia autenticazione attiva | `success` |
| email da confermare | `warning` |

## Icone

Solo `@mui/icons-material`, nella variante outlined dove esiste.

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
