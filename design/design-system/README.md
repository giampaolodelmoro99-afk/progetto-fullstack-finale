# Design system — Casa del Silicio

Riferimento visivo e tecnico generato con Claude Design (palette Oro champagne).

| File | Contenuto |
|---|---|
| `theme.js` | tema MUI di riferimento generato da Claude Design |
| `01-overview.png` | logo e payoff |
| `02-colors-*.png` | token colore con ruolo MUI e contrasti WCAG |
| `03-typography-*.png` | scala tipografica (Bodoni Moda per titoli e prezzi, Manrope per interfaccia e testi) e file dei font |
| `04-components-*.png` | logo, pulsanti, campi, select, checkbox, switch, chip degli stati d'ordine, badge del carrello, alert, card prodotto, app bar, palette e scala tipografica applicate |
| `voice-and-content.md` | regole di voce e contenuti: tono, sentence case, formati di prezzi e unità, nomi dei prodotti, parole fisse per stati d'ordine e disponibilità |

`theme.js` è un riferimento, non va importato così com'è: nella Fase 7 viene diviso in `frontend/src/theme/` secondo la skill `mui-design-system`. I punti di integrazione sono in `docs/backlog.md`.
