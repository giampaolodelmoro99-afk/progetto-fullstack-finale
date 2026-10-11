import { describe, expect, it } from 'vitest';
import { toAssistantError } from '@/features/assistant/assistantErrors';
import { createClientError, normalizeApiError } from '@/utils/normalizeApiError';

function apiError(status, data = {}, headers = {}) {
  return normalizeApiError({ response: { status, data, headers } });
}

describe('toAssistantError', () => {
  it.each([
    [
      '429 with Retry-After',
      apiError(429, { message: 'Too Many Attempts.' }, { 'retry-after': '40' }),
      {
        kind: 'rate_limited',
        message: 'Hai fatto troppe domande, riprova tra 40 secondi.',
        retryAfter: 40,
      },
    ],
    [
      '429 without Retry-After',
      apiError(429, { message: 'Too Many Attempts.' }),
      {
        kind: 'rate_limited',
        message: 'Hai fatto troppe domande, riprova tra poco.',
        retryAfter: null,
      },
    ],
    [
      '422',
      apiError(422, {
        message: 'Il campo è obbligatorio.',
        errors: { messages: ['Obbligatorio.'] },
      }),
      {
        kind: 'invalid',
        message: 'Si è verificato un errore imprevisto. Riprova più tardi.',
        retryAfter: null,
      },
    ],
    [
      '503 busy',
      apiError(503, {
        message: "L'assistente è occupato, riprova tra poco.",
        code: 'assistant_busy',
      }),
      { kind: 'busy', message: "L'assistente è occupato, riprova tra poco.", retryAfter: null },
    ],
    [
      '503 unavailable',
      apiError(503, {
        message: "L'assistente non è disponibile in questo momento.",
        code: 'assistant_unavailable',
      }),
      {
        kind: 'failed',
        message: "L'assistente non è disponibile in questo momento.",
        retryAfter: null,
      },
    ],
    [
      '500',
      apiError(500, { message: 'Server Error' }),
      {
        kind: 'failed',
        message: 'Si è verificato un errore imprevisto. Riprova più tardi.',
        retryAfter: null,
      },
    ],
    [
      'network error',
      createClientError('network', "L'assistente non è disponibile in questo momento."),
      {
        kind: 'failed',
        message: "L'assistente non è disponibile in questo momento.",
        retryAfter: null,
      },
    ],
    [
      'an error that is not normalized',
      new SyntaxError('Unexpected token'),
      {
        kind: 'interrupted',
        message: 'La risposta si è interrotta prima della fine.',
        retryAfter: null,
      },
    ],
  ])('maps %s', (_, error, expected) => {
    expect(toAssistantError(error)).toEqual(expected);
  });
});
