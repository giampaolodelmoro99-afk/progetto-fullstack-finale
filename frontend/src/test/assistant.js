import { http, HttpResponse } from 'msw';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';

const NDJSON_HEADERS = { 'Content-Type': 'application/x-ndjson' };

function encodeEvents(events) {
  return events.map((event) => `${JSON.stringify(event)}\n`).join('');
}

export function textEvents(...deltas) {
  return deltas.map((delta) => ({ type: 'text', delta }));
}

export function completeAnswer(text, products = []) {
  return [...textEvents(text), { type: 'products', data: products }, { type: 'done' }];
}

export function ndjsonResponse(events) {
  return new HttpResponse(encodeEvents(events), { status: 200, headers: NDJSON_HEADERS });
}

export function openAnswer() {
  const encoder = new TextEncoder();
  let controller;
  const stream = new ReadableStream({
    start(streamController) {
      controller = streamController;
    },
  });

  return {
    response: () => new HttpResponse(stream, { status: 200, headers: NDJSON_HEADERS }),
    send: (...events) => controller.enqueue(encoder.encode(encodeEvents(events))),
    close: () => controller.close(),
  };
}

export function recordAssistantMessages(respond) {
  const requests = [];

  server.use(
    http.post(apiUrl('/api/v1/assistant/messages'), async ({ request }) => {
      const body = await request.json();
      requests.push({ body, headers: request.headers });

      return respond({ request, body, call: requests.length });
    }),
  );

  return requests;
}

export function assistantStatus(available) {
  server.use(
    http.get(apiUrl('/api/v1/assistant/status'), () => HttpResponse.json({ data: { available } })),
  );
}

export function setXsrfCookie(value) {
  document.cookie = `XSRF-TOKEN=${value}; path=/`;
}

export function clearXsrfCookie() {
  document.cookie = 'XSRF-TOKEN=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
}
