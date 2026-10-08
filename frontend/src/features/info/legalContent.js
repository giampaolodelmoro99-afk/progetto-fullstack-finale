import { legalSections } from '@/features/info/sections';

export const legalNotice =
  'Testi dimostrativi per un progetto didattico: non costituiscono condizioni contrattuali reali.';

export const legalContent = [
  {
    id: legalSections.termsOfSale,
    title: 'Condizioni di vendita',
    paragraphs: [
      "Casa del Silicio vende online i prodotti della propria linea a clienti con un account registrato. Tutti i prezzi sono in euro e comprendono l'IVA.",
      'Spediamo solo in Italia. Il costo della spedizione e la soglia oltre la quale è gratuita sono indicati nel carrello e nella pagina Assistenza prima del pagamento.',
      "Il pagamento avviene con carta al momento dell'ordine. L'ordine è confermato quando il pagamento va a buon fine e ricevi l'email di conferma.",
      "Puoi annullare un ordine dalla sezione I miei ordini finché non è spedito: ti rimborsiamo l'intero importo sulla carta usata per il pagamento.",
      'Tutti i prodotti hanno una garanzia di 2 anni dalla consegna.',
    ],
  },
  {
    id: legalSections.withdrawal,
    title: 'Diritto di recesso',
    paragraphs: [
      'Hai 30 giorni dalla consegna per restituire un prodotto senza indicarne il motivo: un periodo più ampio dei 14 giorni previsti come minimo dalla legge.',
      "Per avviare il reso scrivi ad assistenza@casadelsilicio.example o chiama il +39 050 000 0000, indicando il numero dell'ordine. Il prodotto va restituito integro, con gli accessori e la confezione originale.",
      "Ricevuto il prodotto, ti rimborsiamo l'importo pagato sulla carta usata per l'acquisto.",
    ],
  },
  {
    id: legalSections.privacy,
    title: 'Privacy',
    paragraphs: [
      "Usiamo i tuoi dati (nome, email, indirizzi di spedizione e telefono) solo per gestire l'account, gli ordini e le comunicazioni che li riguardano.",
      'Non salviamo mai i dati completi della carta: dopo il pagamento conserviamo solo le ultime 4 cifre, per mostrarti con quale carta hai pagato.',
      'Gli ordini, con i documenti di vendita, si conservano per 10 anni per obbligo fiscale.',
      "Puoi eliminare l'account dalla pagina Sicurezza quando non hai ordini in corso: cancelliamo i dati dell'account, gli indirizzi salvati, il carrello e l'accesso. Gli ordini, con l'indirizzo di spedizione indicato all'acquisto, restano per il periodo previsto dalla legge, staccati dall'account.",
      "L'assistente AI del negozio funziona su un modello eseguito sui nostri server, non su servizi esterni, e non conserva le conversazioni.",
      'Per qualsiasi richiesta sui tuoi dati scrivi ad assistenza@casadelsilicio.example.',
    ],
  },
  {
    id: legalSections.cookies,
    title: 'Cookie',
    paragraphs: [
      'Usiamo solo cookie tecnici, necessari al funzionamento del negozio: il cookie di sessione, che ti mantiene connesso, e il cookie di sicurezza contro le richieste falsificate (CSRF).',
      'Non usiamo cookie di profilazione né di terze parti, quindi non ti chiediamo alcun consenso.',
    ],
  },
];
