# Frontend — React + MUI

Consulta sempre le skill `react-architecture`, `mui-design-system` e `testing`; per le schermate di accesso e sicurezza anche `account-security`.

- JavaScript (JSX), non TypeScript.
- API su `import.meta.env.VITE_API_URL` (http://localhost:8000), richieste con cookie (`withCredentials` e `withXSRFToken`).
- Nessun commento inline nel codice, test compresi.
- Stile solo con la prop `sx` e token del tema: nessun colore, spaziatura o dimensione hardcoded.
- Testi dell'interfaccia in italiano, codice in inglese.
- Ogni valore mostrato ha etichetta e unità (prezzi formattati in EUR, quantità con "pezzi").
- Test con Vitest, React Testing Library e MSW: `make test-frontend`.
- npm si esegue solo nel container: `make npm cmd="..."` oppure `docker compose exec node npm ...`.
- Dopo ogni modifica visibile, indica all'utente quali pagine aprire nel browser e cosa controllare.
