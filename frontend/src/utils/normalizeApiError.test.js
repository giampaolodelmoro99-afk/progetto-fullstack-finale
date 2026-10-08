import { describe, expect, it } from 'vitest';
import {
  createClientError,
  normalizeApiError,
  withNormalizedError,
} from '@/utils/normalizeApiError';

function httpError(status, data, headers = {}) {
  return { response: { status, data, headers } };
}

describe('normalizeApiError', () => {
  it('maps validation errors to the first message of each field', () => {
    const error = httpError(422, {
      message: 'Il campo email è obbligatorio.',
      errors: {
        email: ['Il campo email è obbligatorio.', 'Il campo email deve essere valido.'],
        name: ['Il campo nome è obbligatorio.'],
      },
    });

    expect(normalizeApiError(error)).toEqual({
      status: 422,
      message: 'Il campo email è obbligatorio.',
      fieldErrors: {
        email: 'Il campo email è obbligatorio.',
        name: 'Il campo nome è obbligatorio.',
      },
      retryAfter: null,
      code: null,
    });
  });

  it.each([
    [402, 'Carta rifiutata.'],
    [409, 'Disponibilità insufficiente per alcuni prodotti.'],
  ])('keeps the domain message sent by the server for a %i', (status, message) => {
    expect(normalizeApiError(httpError(status, { message })).message).toBe(message);
  });

  it('keeps the code sent by the server', () => {
    const error = httpError(422, {
      message: 'Credenziali non valide',
      code: 'invalid_credentials',
    });

    expect(normalizeApiError(error)).toMatchObject({
      message: 'Credenziali non valide',
      code: 'invalid_credentials',
      fieldErrors: {},
    });
  });

  it('keeps the message of a failed refund', () => {
    const error = httpError(502, {
      message: 'Il rimborso non è riuscito: riprova più tardi.',
      code: 'refund_failed',
    });

    expect(normalizeApiError(error)).toMatchObject({
      status: 502,
      message: 'Il rimborso non è riuscito: riprova più tardi.',
      code: 'refund_failed',
    });
  });

  it('hides the message of a 502 without the refund code', () => {
    const error = httpError(502, { message: 'Bad gateway upstream' });

    expect(normalizeApiError(error).message).toBe(
      'Si è verificato un errore imprevisto. Riprova più tardi.',
    );
  });

  it.each([
    ['assistant_busy', "L'assistente è occupato, riprova tra poco."],
    ['assistant_unavailable', "L'assistente non è disponibile in questo momento."],
  ])('keeps the message of a 503 with code %s', (code, message) => {
    expect(normalizeApiError(httpError(503, { message, code }))).toMatchObject({
      status: 503,
      message,
      code,
    });
  });

  it('hides the message of a 503 without an assistant code', () => {
    expect(normalizeApiError(httpError(503, { message: 'Service Unavailable' })).message).toBe(
      'Il servizio non è disponibile in questo momento. Riprova più tardi.',
    );
  });

  it('reads the seconds to wait from the Retry-After header of a 429', () => {
    const error = httpError(429, { message: 'Too Many Attempts.' }, { 'retry-after': '45' });

    expect(normalizeApiError(error)).toMatchObject({
      status: 429,
      message: 'Troppi tentativi. Riprova tra 45 secondi',
      retryAfter: 45,
    });
  });

  it('reads Retry-After from axios headers', () => {
    const headers = { get: (name) => (name === 'retry-after' ? '12' : undefined) };
    const error = httpError(429, { message: 'Too Many Attempts.' }, headers);

    expect(normalizeApiError(error).retryAfter).toBe(12);
  });

  it.each([[{}], [{ 'retry-after': 'domani' }], [{ 'retry-after': '' }]])(
    'uses a generic wait message when Retry-After is missing or not a number',
    (headers) => {
      const error = httpError(429, { message: 'Too Many Attempts.' }, headers);

      expect(normalizeApiError(error)).toMatchObject({
        message: 'Troppi tentativi. Riprova tra poco',
        retryAfter: null,
      });
    },
  );

  it.each([
    [401, 'Unauthenticated.', 'Devi accedere per continuare.'],
    [403, 'This action is unauthorized.', 'Non hai i permessi per eseguire questa operazione.'],
    [404, 'The route api/v1/x could not be found.', 'La risorsa richiesta non esiste.'],
    [419, 'CSRF token mismatch.', 'La sessione è scaduta. Ricarica la pagina e riprova.'],
    [423, 'Identity confirmation required.', 'Per continuare devi confermare la tua identità.'],
  ])(
    'replaces the server message of a %i with a local italian text',
    (status, serverMessage, expected) => {
      expect(normalizeApiError(httpError(status, { message: serverMessage })).message).toBe(
        expected,
      );
    },
  );

  it('never shows technical details of a server error', () => {
    const error = httpError(500, {
      message: 'SQLSTATE[42S02]: Base table or view not found: 1146 Table ecommerce.x',
      exception: 'Illuminate\\Database\\QueryException',
    });

    expect(normalizeApiError(error)).toEqual({
      status: 500,
      message: 'Si è verificato un errore imprevisto. Riprova più tardi.',
      fieldErrors: {},
      retryAfter: null,
      code: null,
    });
  });

  it('returns a local network message when there is no response', () => {
    expect(normalizeApiError(new Error('Network Error'))).toEqual({
      status: null,
      message: 'Impossibile contattare il server. Controlla la connessione e riprova.',
      fieldErrors: {},
      retryAfter: null,
      code: null,
    });
  });

  it('falls back to a local text when a domain error has no message', () => {
    expect(normalizeApiError(httpError(409, null)).message).toBe(
      "L'operazione non è possibile nello stato attuale.",
    );
  });

  it('ignores field errors outside of validation responses', () => {
    const error = httpError(409, { message: 'Conflitto.', errors: { stock: ['Esaurito.'] } });

    expect(normalizeApiError(error).fieldErrors).toEqual({});
  });
});

describe('normalizeApiError with already normalized errors', () => {
  it('returns an already normalized error unchanged', () => {
    const normalized = normalizeApiError({
      response: {
        status: 422,
        data: { message: 'Credenziali non valide', code: 'invalid_credentials' },
      },
    });

    expect(normalizeApiError(normalized)).toBe(normalized);
  });

  it('marks a cancelled identity confirmation', () => {
    const error = { identityConfirmationCancelled: true, response: { status: 423, data: {} } };

    expect(normalizeApiError(error)).toMatchObject({
      status: 423,
      code: 'identity_confirmation_cancelled',
    });
  });

  it('builds client errors with the same shape', () => {
    expect(createClientError('passkey_failed', 'Riprova.')).toEqual({
      status: null,
      message: 'Riprova.',
      fieldErrors: {},
      retryAfter: null,
      code: 'passkey_failed',
    });
  });

  it('rethrows the normalized error without the original request', async () => {
    const rejected = withNormalizedError(() =>
      Promise.reject({
        config: { data: '{"password":"Silicio2026"}' },
        response: { status: 422, data: { message: 'Credenziali non valide' } },
      }),
    );

    await expect(rejected).rejects.toEqual({
      status: 422,
      message: 'Credenziali non valide',
      fieldErrors: {},
      retryAfter: null,
      code: null,
    });
    await expect(rejected).rejects.not.toHaveProperty('config');
  });
});
