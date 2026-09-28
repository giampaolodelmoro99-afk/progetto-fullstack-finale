# Voce e contenuti — Casa del Silicio

- Tutti i testi dell'interfaccia sono in italiano. Si dà del tu, con garbo: «Aggiungi al carrello», «Il tuo ordine è in viaggio».
- Maiuscola solo all'inizio (sentence case) in pulsanti, titoli e voci di menu: «Scopri la collezione», mai «Scopri La Collezione». Il maiuscolo spaziato è riservato allo stile overline («AUDIO», «NOVITÀ»).
- Frasi brevi e concrete: niente punti esclamativi, niente emoji, niente urgenza artificiale («Ultimi 2 pezzi» sì, «Affrettati!!» no).
- Prezzi sempre nel formato it-IT con due decimali e simbolo dopo la cifra: 349,00 €, 1.249,00 €. Si ottiene con Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', useGrouping: 'always' }): senza useGrouping: 'always' le cifre da 1.000 a 9.999 escono senza punto. Mai «€349» o «349 euro».
- Unità con lo spazio: «65 W», «1 m», «24/48 ore».
- Nomi dei prodotti della linea: un sostantivo più un nome evocativo italiano, con maiuscola: Cuffie Aria, Caricatore Brezza 65 W, Supporto Colle in alluminio, Cavo Seta USB-C. Non citare mai marchi di terzi.
- Stati d'ordine, sempre con queste parole: In attesa, Pagato, Spedito, Consegnato, Annullato.
- Disponibilità: Disponibile, Ultimi N pezzi (Ultimo pezzo se 1), Esaurito.
