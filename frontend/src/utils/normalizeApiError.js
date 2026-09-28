const NETWORK_ERROR_MESSAGE =
  'Impossibile contattare il server. Controlla la connessione e riprova.';

const LOCAL_MESSAGES = {
  401: 'Devi accedere per continuare.',
  402: 'Il pagamento non è andato a buon fine.',
  403: 'Non hai i permessi per eseguire questa operazione.',
  404: 'La risorsa richiesta non esiste.',
  409: "L'operazione non è possibile nello stato attuale.",
  419: 'La sessione è scaduta. Ricarica la pagina e riprova.',
  422: 'Alcuni dati non sono validi.',
  423: 'Per continuare devi confermare la tua identità.',
  429: 'Troppe richieste. Attendi qualche istante e riprova.',
};

const DEFAULT_MESSAGE = 'Si è verificato un errore imprevisto. Riprova più tardi.';

const STATUSES_WITH_SERVER_MESSAGE = new Set([402, 409, 422]);

function firstMessages(errors) {
  return Object.fromEntries(
    Object.entries(errors ?? {}).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : messages,
    ]),
  );
}

function resolveMessage(status, serverMessage) {
  if (STATUSES_WITH_SERVER_MESSAGE.has(status) && serverMessage) {
    return serverMessage;
  }

  return LOCAL_MESSAGES[status] ?? DEFAULT_MESSAGE;
}

export function normalizeApiError(error) {
  const response = error?.response;

  if (!response) {
    return { status: null, message: NETWORK_ERROR_MESSAGE, fieldErrors: {} };
  }

  const { status, data } = response;

  return {
    status,
    message: resolveMessage(status, data?.message),
    fieldErrors: status === 422 ? firstMessages(data?.errors) : {},
  };
}
