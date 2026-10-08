import { http, HttpResponse } from 'msw';
import { routes } from '@/app/router/router';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { renderWithProviders } from '@/test/renderWithProviders';

export const PASSKEY_RESPONSE = {
  id: 'cred-1',
  rawId: 'cred-1',
  type: 'public-key',
  response: { clientDataJSON: 'a', authenticatorData: 'b', signature: 'c', userHandle: 'd' },
  clientExtensionResults: {},
};

export function renderApp(route, { user = null, strict = false } = {}) {
  return renderWithProviders(null, { routes, route, user, strict });
}

export function recordPost(path, respond) {
  const bodies = [];
  server.use(
    http.post(apiUrl(path), async ({ request }) => {
      const text = await request.text();
      bodies.push(text === '' ? null : JSON.parse(text));
      return respond({ request, call: bodies.length });
    }),
  );

  return bodies;
}

export function validationError(field, message) {
  return HttpResponse.json({ message, errors: { [field]: [message] } }, { status: 422 });
}

export function domainError(status, code, message) {
  return HttpResponse.json({ message, code }, { status });
}

export function tooManyAttempts(seconds) {
  return HttpResponse.json(
    { message: `Troppi tentativi. Riprova tra ${seconds} secondi.` },
    { status: 429, headers: seconds === null ? {} : { 'Retry-After': String(seconds) } },
  );
}
