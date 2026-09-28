import { describe, expect, it } from 'vitest';
import { normalizeApiError } from '@/utils/normalizeApiError';

function httpError(status, data) {
  return { response: { status, data } };
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
    });
  });

  it.each([
    [402, 'Carta rifiutata.'],
    [409, 'Disponibilità insufficiente per alcuni prodotti.'],
  ])('keeps the domain message sent by the server for a %i', (status, message) => {
    expect(normalizeApiError(httpError(status, { message })).message).toBe(message);
  });

  it.each([
    [401, 'Unauthenticated.', 'Devi accedere per continuare.'],
    [403, 'This action is unauthorized.', 'Non hai i permessi per eseguire questa operazione.'],
    [404, 'The route api/v1/x could not be found.', 'La risorsa richiesta non esiste.'],
    [419, 'CSRF token mismatch.', 'La sessione è scaduta. Ricarica la pagina e riprova.'],
    [423, 'Identity confirmation required.', 'Per continuare devi confermare la tua identità.'],
    [429, 'Too Many Attempts.', 'Troppe richieste. Attendi qualche istante e riprova.'],
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
      file: '/var/www/html/vendor/laravel/framework/src/Illuminate/Database/Connection.php',
    });

    expect(normalizeApiError(error)).toEqual({
      status: 500,
      message: 'Si è verificato un errore imprevisto. Riprova più tardi.',
      fieldErrors: {},
    });
  });

  it('returns a local network message when there is no response', () => {
    expect(normalizeApiError(new Error('Network Error'))).toEqual({
      status: null,
      message: 'Impossibile contattare il server. Controlla la connessione e riprova.',
      fieldErrors: {},
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
