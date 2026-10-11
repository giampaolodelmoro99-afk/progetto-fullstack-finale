export const MAX_MESSAGE_LENGTH = 500;

export const MAX_HISTORY_MESSAGES = 10;

export const MAX_ASSISTANT_MESSAGE_LENGTH = 4000;

export const REFUSAL_MESSAGE =
  'Posso aiutarti solo con i prodotti e i servizi di Casa del Silicio.';

export const SUGGESTIONS = [
  'Cerco cuffie per viaggiare',
  'Un regalo sotto i 100 €',
  'Quanto costa la spedizione?',
  'Come funzionano i resi?',
];

export const UNAVAILABLE_MESSAGE = "L'assistente non è disponibile in questo momento.";

export const INTERRUPTED_ANSWER_MESSAGE = 'La risposta si è interrotta prima della fine.';

export const UNEXPECTED_ERROR_MESSAGE = 'Si è verificato un errore imprevisto. Riprova più tardi.';

export const STOPPED_MESSAGE = 'Risposta interrotta.';

export function tooManyQuestionsMessage(retryAfter) {
  return retryAfter === null
    ? 'Hai fatto troppe domande, riprova tra poco.'
    : `Hai fatto troppe domande, riprova tra ${retryAfter} secondi.`;
}
