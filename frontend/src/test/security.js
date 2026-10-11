import { screen, within } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { expect } from 'vitest';
import { renderApp } from '@/test/authHelpers';
import { buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';

export function renderSecurityPage(user = buildUser()) {
  server.use(http.get(apiUrl('/api/v1/me'), () => HttpResponse.json({ data: user })));

  return renderApp('/account/security', { user });
}

export function serveOverview(overview) {
  server.use(http.get(apiUrl('/api/v1/me/security'), () => HttpResponse.json({ data: overview })));
}

export function servePasskeys(passkeys) {
  server.use(http.get(apiUrl('/api/v1/me/passkeys'), () => HttpResponse.json({ data: passkeys })));
}

export function recordRequest(method, path, respond) {
  const bodies = [];
  server.use(
    http[method](apiUrl(path), async ({ request }) => {
      const text = await request.text();
      bodies.push(text === '' ? null : JSON.parse(text));
      return respond({ call: bodies.length });
    }),
  );

  return bodies;
}

export function identityRequired() {
  return HttpResponse.json(
    { message: 'Conferma la tua identità per continuare.', requires: ['password'] },
    { status: 423 },
  );
}

export function protectedEndpoint(method, path, respond) {
  const calls = [];
  let confirmed = false;

  server.use(
    http.post(apiUrl('/api/v1/me/confirm-identity'), () => {
      confirmed = true;
      return HttpResponse.json({ data: {} });
    }),
    http[method](apiUrl(path), async ({ request }) => {
      const text = await request.text();
      calls.push(text === '' ? null : JSON.parse(text));

      return confirmed ? respond({ call: calls.length }) : identityRequired();
    }),
  );

  return calls;
}

export async function confirmIdentityWith(user, prompt, { code = null } = {}) {
  const dialog = await screen.findByRole('dialog', { name: 'Conferma la tua identità' });
  expect(dialog).toHaveTextContent(prompt);
  await user.type(await within(dialog).findByLabelText('Password'), 'Silicio2026');

  if (code) {
    await user.type(within(dialog).getByLabelText('Codice a 6 cifre'), code);
  }

  await user.click(within(dialog).getByRole('button', { name: 'Conferma' }));
}

export async function cancelIdentityConfirmation(user) {
  const dialog = await screen.findByRole('dialog', { name: 'Conferma la tua identità' });
  await user.click(within(dialog).getByRole('button', { name: 'Annulla' }));
}
