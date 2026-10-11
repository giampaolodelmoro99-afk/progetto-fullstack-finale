# Roadmap

Elenco di riferimento delle fasi del progetto. Skill, backlog e documenti citano le fasi per nome e rimandano a questo file, senza ripeterne l'elenco.

| Fase | Contenuto | Stato |
|---|---|---|
| Fase 0 | Ambiente Docker, scaffolding, infrastruttura dei test | completata |
| Fase 1 | Design system, mockup A–D, allineamento delle skill, schema ER, illustrazioni demo | completata |
| Fase 2a | Migration, modelli, enum, observer, factory e test | completata |
| Fase 2b | Seeder e dati demo | completata |
| Fase 2c | Infrastruttura dell'assistente AI: servizio Ollama, Modelfile, interfaccia con implementazione finta, prova e scelta del modello | completata |
| Fase 3a | Registrazione, login, verifica email, recupero password, rate limiting | completata |
| Fase 3b | Doppia autenticazione TOTP, codici di recupero, conferma dell'identità, cambio password ed email, notifiche, eventi di sicurezza, eliminazione dell'account con anonimizzazione | completata |
| Fase 3c | Passkey | completata |
| Fase 4, parte 1 | Catalogo pubblico, impostazioni del negozio, testi dell'assistenza (`GET /shop/support`) | completata |
| Fase 4, parte 2 | Admin del catalogo: CRUD di prodotti e categorie con immagini | completata |
| Fase 4b | Backend dell'assistente AI: endpoint, ricerca nel catalogo, streaming, limiti, test | completata |
| Fase 5a | Indirizzi del cliente e carrello: CRUD degli indirizzi con predefinito unico, carrello con `CartResource`, totali e `can_checkout` | completata |
| Fase 5b | Checkout, pagamenti, ordini del cliente, annullamento con rimborso, email, riconciliazione degli ordini rimasti in attesa | completata |
| Fase 5c | Ordini e dashboard admin | completata |
| Fase 6 | Revisione della copertura dei test e documentazione delle API | completata |
| Design E | Chat dell'assistente e sezione "Elimina account" (in Claude Design, prima della Fase 7), `design/mockups/05-assistente-account.pdf`, e allineamento delle skill | completata |
| Fase 7a | Fondamenta del frontend: tema dal design system con i font, router con layout e rotte protette, httpClient e `AuthProvider`, header, footer e Drawer del negozio, layout account e admin, componenti di stato e utility di formato, infrastruttura dei test | completata |
| Fase 7b | Autenticazione: login con secondo fattore, registrazione, password dimenticata e reset, pagine raggiunte dai link email, banner dell'email non verificata, conferma dell'identità (423) | completata |
| Fase 8a | Negozio: home, catalogo con filtri nella query string, dettaglio prodotto con aggiunta al carrello e badge, pagine Assistenza e Note legali | completata |
| Fase 8b | Carrello, checkout in tre passi, conferma d'ordine | completata |
| Fase 8c | Widget dell'assistente AI e formato Markdown leggero (garantito dal parser del widget) | completata |
| Fase 9 | Area account (profilo, indirizzi, ordini, sicurezza, eliminazione dell'account) e pannello admin | |
| Fase 10 | Rifinitura, README, verifica dei dati demo, preparazione della presentazione; test end-to-end con Playwright facoltativi | |
