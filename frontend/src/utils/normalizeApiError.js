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
  503: 'Il servizio non è disponibile in questo momento. Riprova più tardi.',
};

const DEFAULT_MESSAGE = 'Si è verificato un errore imprevisto. Riprova più tardi.';

const STATUSES_WITH_SERVER_MESSAGE = new Set([402, 409, 422]);

const CODES_WITH_SERVER_MESSAGE = {
  502: new Set(['refund_failed']),
  503: new Set(['assistant_busy', 'assistant_unavailable']),
};

function firstMessages(errors) {
  return Object.fromEntries(
    Object.entries(errors ?? {}).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : messages,
    ]),
  );
}

function parseRetryAfter(headers) {
  const value =
    typeof headers?.get === 'function' ? headers.get('retry-after') : headers?.['retry-after'];

  if (value === undefined || value === null || String(value).trim() === '') {
    return null;
  }

  const seconds = Number(value);

  return Number.isInteger(seconds) && seconds >= 0 ? seconds : null;
}

function usesServerMessage(status, code) {
  return STATUSES_WITH_SERVER_MESSAGE.has(status) || CODES_WITH_SERVER_MESSAGE[status]?.has(code);
}

function resolveMessage(status, code, serverMessage, retryAfter) {
  if (status === 429) {
    return retryAfter === null
      ? 'Troppi tentativi. Riprova tra poco'
      : `Troppi tentativi. Riprova tra ${retryAfter} secondi`;
  }

  if (serverMessage && usesServerMessage(status, code)) {
    return serverMessage;
  }

  return LOCAL_MESSAGES[status] ?? DEFAULT_MESSAGE;
}

export function normalizeApiError(error) {
  const response = error?.response;

  if (!response) {
    return {
      status: null,
      message: NETWORK_ERROR_MESSAGE,
      fieldErrors: {},
      retryAfter: null,
      code: null,
    };
  }

  const { status, data, headers } = response;
  const code = typeof data?.code === 'string' ? data.code : null;
  const retryAfter = status === 429 ? parseRetryAfter(headers) : null;

  return {
    status,
    message: resolveMessage(status, code, data?.message, retryAfter),
    fieldErrors: status === 422 ? firstMessages(data?.errors) : {},
    retryAfter,
    code,
  };
}
