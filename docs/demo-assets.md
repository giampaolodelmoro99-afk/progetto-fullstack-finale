# Illustrazioni demo

Le immagini dei prodotti e delle categorie dei dati demo sono illustrazioni vettoriali scritte a mano in `design/illustrations/`, convertite in WebP 800×1000 e già presenti nella repository: `docker compose up` non le rigenera.

| Sorgenti | WebP generati |
|---|---|
| `design/illustrations/products/*.svg` (96 file) | `backend/database/seeders/assets/products/` |
| `design/illustrations/categories/*.svg` (6 categorie) | `frontend/src/assets/categories/` |
| `design/illustrations/fallback.svg` (ripiego per categorie e prodotti senza immagini) | `frontend/src/assets/fallback.webp` |

## Prodotti

Le immagini dei prodotti seguono lo schema `{type}-{finish}-{view}`: 16 tipi, due finiture (`graphite`, `ivory`) e tre viste (`front` per la copertina, `angle` per i tre quarti, `detail` per il particolare), per esempio `headphones-ivory-angle.webp`. Tipi: `headphones`, `earbuds`, `speaker`, `radio`, `charger`, `cable`, `power-bank`, `wireless-charger`, `stand`, `keyboard`, `hub`, `mouse`, `watch`, `strap`, `lamp`, `sensor`.

## Categorie

Le immagini delle categorie hanno nomi in inglese; la corrispondenza con gli slug, usata da `getCategoryImage()` in `frontend/src/features/catalog/categoryImages.js`, è questa:

| Slug della categoria | Immagine |
|---|---|
| `audio` | `audio.webp` |
| `ricarica-ed-energia` | `charging.webp` |
| `scrivania` | `desk.webp` |
| `indossabili` | `wearables.webp` |
| `casa-smart` | `smart-home.webp` |
| `viaggio` | `travel.webp` |

Uno slug senza immagine (per esempio una categoria creata dall'admin) usa `fallback.webp`.

## Rigenerare le immagini

Dopo aver modificato un SVG, dalla radice della repository:

```sh
sh scripts/render-illustrations.sh
```

Lo script usa un container Alpine 3.20 usa e getta, fissato per digest così la conversione dà sempre gli stessi file, con `rsvg-convert` e `cwebp` installati al momento: serve solo Docker, e non si aggiunge nessuna dipendenza al progetto. Controlla prima che Docker sia disponibile e che le sorgenti esistano, converte tutto in una cartella temporanea del container e stampa il peso di ogni file. Sostituisce i WebP esistenti solo se tutte le conversioni riescono e nessun file supera i 60 KB; altrimenti esce con errore e lascia intatti i file già presenti. In ogni caso i file generati appartengono al tuo utente.

Per controllare il risultato apri `design/illustrations/preview.html` nel browser: mostra tutte le immagini generate in griglia.
