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
| Fase 3b | Doppia autenticazione TOTP, codici di recupero, conferma dell'identità, cambio password ed email, notifiche, eventi di sicurezza, eliminazione dell'account con anonimizzazione | |
| Fase 3c | Passkey | |
| Fase 4 | Catalogo pubblico, impostazioni del negozio, testi dell'assistenza (`GET /shop/support`), CRUD admin di prodotti e categorie con immagini | |
| Fase 4b | Backend dell'assistente AI: endpoint, ricerca nel catalogo, streaming, limiti, test | |
| Fase 5 | Carrello, checkout, ordini, pagamenti, annullamento, email, ordini e dashboard admin | |
| Fase 6 | Revisione della copertura dei test e documentazione delle API | |
| Design E | Chat dell'assistente e sezione "Elimina account" (in Claude Design, prima della Fase 7) | |
| Fase 7 | Frontend base: tema dal design system, router, layout, httpClient, autenticazione, pagine raggiunte dai link email | |
| Fase 8 | Negozio: home, catalogo, prodotto, carrello, checkout, conferma d'ordine, widget dell'assistente | |
| Fase 9 | Area account (profilo, indirizzi, ordini, sicurezza, eliminazione dell'account) e pannello admin | |
| Fase 10 | Rifinitura, README, verifica dei dati demo, preparazione della presentazione; test end-to-end con Playwright facoltativi | |
