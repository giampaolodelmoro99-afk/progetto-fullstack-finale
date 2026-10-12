import { http, HttpResponse } from 'msw';
import { afterEach, describe, expect, it } from 'vitest';
import { streamAssistantAnswer } from '@/features/assistant/api/assistantApi';
import {
  clearXsrfCookie,
  completeAnswer,
  ndjsonResponse,
  openAnswer,
  recordAssistantMessages,
  setXsrfCookie,
} from '@/test/assistant';
import { domainError } from '@/test/authHelpers';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { isNormalizedApiError } from '@/utils/normalizeApiError';

const QUESTION = [{ role: 'user', content: 'Quanto costa la spedizione?' }];

function countCsrfRequests() {
  const counter = { count: 0 };
  server.use(
    http.get(apiUrl('/sanctum/csrf-cookie'), () => {
      counter.count += 1;
      setXsrfCookie('nuovo%3Dtoken');
      return new HttpResponse(null, { status: 204 });
    }),
  );

  return counter;
}

async function collect(signal = new AbortController().signal) {
  const events = [];

  for await (const event of streamAssistantAnswer({ messages: QUESTION, signal })) {
    events.push(event);
  }

  return events;
}

async function failure(promise) {
  try {
    await promise;
  } catch (error) {
    return error;
  }

  throw new Error('Expected a failure');
}

afterEach(() => {
  clearXsrfCookie();
});

describe('streamAssistantAnswer', () => {
  it('sends the messages with the decoded xsrf token and reads the events', async () => {
    setXsrfCookie('abc%3Ddef');
    const csrf = countCsrfRequests();
    const requests = recordAssistantMessages(() => ndjsonResponse(completeAnswer('Ciao.')));

    expect(await collect()).toEqual(completeAnswer('Ciao.'));
    expect(requests[0].body).toEqual({ messages: QUESTION });
    expect(requests[0].headers.get('X-XSRF-TOKEN')).toBe('abc=def');
    expect(requests[0].headers.get('Accept')).toBe('application/json');
    expect(csrf.count).toBe(0);
  });

  it('asks for the csrf cookie before the first request of a guest', async () => {
    const csrf = countCsrfRequests();
    const requests = recordAssistantMessages(() => ndjsonResponse(completeAnswer('Ciao.')));

    await collect();

    expect(csrf.count).toBe(1);
    expect(requests[0].headers.get('X-XSRF-TOKEN')).toBe('nuovo=token');
  });

  it('asks for a new csrf cookie after a 419 and retries once', async () => {
    setXsrfCookie('scaduto');
    const csrf = countCsrfRequests();
    const requests = recordAssistantMessages(({ call }) =>
      call === 1
        ? HttpResponse.json(
            { message: 'La sessione è scaduta. Ricarica la pagina e riprova.' },
            { status: 419 },
          )
        : ndjsonResponse(completeAnswer('Ciao.')),
    );

    expect(await collect()).toEqual(completeAnswer('Ciao.'));
    expect(csrf.count).toBe(1);
    expect(requests).toHaveLength(2);
    expect(requests[1].headers.get('X-XSRF-TOKEN')).toBe('nuovo=token');
  });

  it('fails after a second 419', async () => {
    setXsrfCookie('scaduto');
    countCsrfRequests();
    const requests = recordAssistantMessages(() =>
      HttpResponse.json(
        { message: 'La sessione è scaduta. Ricarica la pagina e riprova.' },
        { status: 419 },
      ),
    );

    const error = await failure(collect());

    expect(requests).toHaveLength(2);
    expect(error).toMatchObject({
      status: 419,
      message: 'La sessione è scaduta. Ricarica la pagina e riprova.',
    });
  });

  it('normalizes a 503 with the message of the server', async () => {
    setXsrfCookie('token');
    recordAssistantMessages(() =>
      domainError(503, 'assistant_busy', "L'assistente è occupato, riprova tra poco."),
    );

    expect(await failure(collect())).toMatchObject({
      status: 503,
      code: 'assistant_busy',
      message: "L'assistente è occupato, riprova tra poco.",
    });
  });

  it('reads Retry-After from a 429', async () => {
    setXsrfCookie('token');
    recordAssistantMessages(() =>
      HttpResponse.json(
        { message: 'Too Many Attempts.' },
        { status: 429, headers: { 'Retry-After': '40' } },
      ),
    );

    expect(await failure(collect())).toMatchObject({ status: 429, retryAfter: 40 });
  });

  it('turns a network error into the unavailable message', async () => {
    setXsrfCookie('token');
    recordAssistantMessages(() => HttpResponse.error());

    expect(await failure(collect())).toMatchObject({
      status: null,
      code: 'network',
      message: "L'assistente non è disponibile in questo momento.",
    });
  });

  it('turns a broken stream into the interrupted answer message', async () => {
    setXsrfCookie('token');
    recordAssistantMessages(() => {
      const stream = new ReadableStream({
        start(controller) {
          controller.enqueue(new TextEncoder().encode('{"type":"text","delta":"Ciao"}\n'));
          controller.error(new Error('Connessione chiusa'));
        },
      });

      return new HttpResponse(stream, { headers: { 'Content-Type': 'application/x-ndjson' } });
    });

    expect(await failure(collect())).toMatchObject({
      code: 'stream_interrupted',
      message: 'La risposta si è interrotta prima della fine.',
    });
  });

  it('ends without a normalized error when the request is aborted', async () => {
    setXsrfCookie('token');
    const answer = openAnswer();
    recordAssistantMessages(() => answer.response());
    const controller = new AbortController();
    const events = streamAssistantAnswer({ messages: QUESTION, signal: controller.signal });

    answer.send({ type: 'text', delta: 'Ciao' });
    expect((await events.next()).value).toEqual({ type: 'text', delta: 'Ciao' });

    const pending = events.next();
    controller.abort();
    const outcome = await pending.then(
      (result) => result,
      (error) => error,
    );

    expect(isNormalizedApiError(outcome)).toBe(false);
    expect(outcome.value).toBeUndefined();
  });
});
