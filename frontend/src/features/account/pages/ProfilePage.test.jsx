import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderApp, tooManyAttempts, validationError } from '@/test/authHelpers';
import { buildSecurityOverview, buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';

const PENDING_CHANGE = {
  new_email: 'giulia.nuova@esempio.it',
  expires_at: '2026-10-11T19:30:00+02:00',
};

function renderProfile(user = buildUser()) {
  return renderApp('/account/profile', { user });
}

function serveMe(user) {
  server.use(http.get(apiUrl('/api/v1/me'), () => HttpResponse.json({ data: user })));
}

function recordJson(method, path, respond) {
  const bodies = [];
  server.use(
    http[method](apiUrl(path), async ({ request }) => {
      const text = await request.text();
      bodies.push(text === '' ? null : JSON.parse(text));
      return respond();
    }),
  );

  return bodies;
}

function allowIdentityConfirmation() {
  server.use(
    http.get(apiUrl('/api/v1/me/security'), () =>
      HttpResponse.json({ data: buildSecurityOverview() }),
    ),
    http.post(apiUrl('/api/v1/me/confirm-identity'), () => HttpResponse.json({ data: {} })),
  );
}

async function confirmIdentity(user) {
  const dialog = await screen.findByRole('dialog', { name: 'Conferma la tua identità' });
  expect(dialog).toHaveTextContent("Prima di cambiare l'email, conferma che sei tu.");
  await user.type(await within(dialog).findByLabelText('Password'), 'Silicio2026');
  await user.click(within(dialog).getByRole('button', { name: 'Conferma' }));
}

describe('ProfilePage', () => {
  it('saves the name and shows a confirmation', async () => {
    const user = userEvent.setup();
    const bodies = recordJson('patch', '/api/v1/me', () =>
      HttpResponse.json({ data: buildUser({ name: 'Giulia Ferri Rossi' }) }),
    );

    renderProfile();

    const field = await screen.findByLabelText('Nome e cognome');
    expect(field).toHaveValue('Giulia Ferri');
    await user.clear(field);
    await user.type(field, 'Giulia Ferri Rossi');
    await user.click(screen.getByRole('button', { name: 'Salva le modifiche' }));

    expect(await screen.findByText('Modifiche salvate')).toBeInTheDocument();
    expect(bodies).toEqual([{ name: 'Giulia Ferri Rossi' }]);
    expect(screen.getByText('Giulia Ferri Rossi · giulia.ferri@esempio.it')).toBeInTheDocument();
  });

  it('disables the save button while the name is being sent', async () => {
    const user = userEvent.setup();
    server.use(
      http.patch(apiUrl('/api/v1/me'), async () => {
        await new Promise((resolve) => setTimeout(resolve, 50));
        return HttpResponse.json({ data: buildUser() });
      }),
    );

    renderProfile();

    await screen.findByLabelText('Nome e cognome');
    await user.click(screen.getByRole('button', { name: 'Salva le modifiche' }));

    expect(screen.getByRole('button', { name: /Salva le modifiche/ })).toBeDisabled();
    expect(await screen.findByText('Modifiche salvate')).toBeInTheDocument();
  });

  it('validates the name before sending it', async () => {
    const user = userEvent.setup();
    const bodies = recordJson('patch', '/api/v1/me', () => HttpResponse.json({ data: {} }));

    renderProfile();

    await user.clear(await screen.findByLabelText('Nome e cognome'));
    await user.click(screen.getByRole('button', { name: 'Salva le modifiche' }));

    expect(await screen.findByText('Inserisci nome e cognome.')).toBeInTheDocument();
    expect(bodies).toEqual([]);
  });

  it('shows the server validation error on the name field', async () => {
    const user = userEvent.setup();
    server.use(
      http.patch(apiUrl('/api/v1/me'), () =>
        validationError('name', 'Il nome non può superare 255 caratteri.'),
      ),
    );

    renderProfile();

    await user.type(await screen.findByLabelText('Nome e cognome'), ' Rossi');
    await user.click(screen.getByRole('button', { name: 'Salva le modifiche' }));

    expect(await screen.findByText('Il nome non può superare 255 caratteri.')).toBeInTheDocument();
  });

  it('shows whether the email is confirmed', async () => {
    renderProfile();

    expect(await screen.findByText('Confermata')).toBeInTheDocument();
    expect(screen.queryByText('Da confermare')).not.toBeInTheDocument();
  });

  it('shows an email still to be confirmed', async () => {
    renderProfile(buildUser({ email_verified_at: null }));

    const card = within(await screen.findByRole('region', { name: 'Email' }));
    expect(card.getByText('Da confermare')).toBeInTheDocument();
    expect(card.queryByText('Confermata')).not.toBeInTheDocument();
  });

  it('asks for the identity before the new email and sends the request', async () => {
    const user = userEvent.setup();
    allowIdentityConfirmation();
    const bodies = recordJson('post', '/api/v1/me/email', () =>
      HttpResponse.json({ data: PENDING_CHANGE }, { status: 201 }),
    );
    serveMe(buildUser({ pending_email_change: PENDING_CHANGE }));

    renderProfile();

    await user.click(await screen.findByRole('button', { name: 'Cambia email' }));
    await confirmIdentity(user);
    const dialog = await screen.findByRole('dialog', { name: 'Cambia email' });
    await user.type(within(dialog).getByLabelText('Nuova email'), 'giulia.nuova@esempio.it');
    await user.click(within(dialog).getByRole('button', { name: 'Invia il link' }));

    expect(
      await screen.findByText('Ti abbiamo inviato un link a giulia.nuova@esempio.it.'),
    ).toBeInTheDocument();
    expect(bodies).toEqual([{ email: 'giulia.nuova@esempio.it' }]);
    expect(await screen.findByText('Cambio email in attesa di conferma')).toBeInTheDocument();
    await waitFor(() =>
      expect(screen.queryByRole('dialog', { name: 'Cambia email' })).not.toBeInTheDocument(),
    );
  });

  it('does not open the new email dialog when the confirmation is cancelled', async () => {
    const user = userEvent.setup();
    allowIdentityConfirmation();

    renderProfile();

    await user.click(await screen.findByRole('button', { name: 'Cambia email' }));
    const dialog = await screen.findByRole('dialog', { name: 'Conferma la tua identità' });
    await user.click(within(dialog).getByRole('button', { name: 'Annulla' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(screen.queryByLabelText('Nuova email')).not.toBeInTheDocument();
  });

  it('shows the server errors in the new email dialog', async () => {
    const user = userEvent.setup();
    allowIdentityConfirmation();
    let attempts = 0;
    server.use(
      http.post(apiUrl('/api/v1/me/email'), () => {
        attempts += 1;
        return attempts === 1
          ? validationError('email', 'Questo indirizzo email è già in uso.')
          : tooManyAttempts(1800);
      }),
    );

    renderProfile();

    await user.click(await screen.findByRole('button', { name: 'Cambia email' }));
    await confirmIdentity(user);
    const dialog = await screen.findByRole('dialog', { name: 'Cambia email' });
    await user.type(within(dialog).getByLabelText('Nuova email'), 'marta.lenzi@esempio.it');
    await user.click(within(dialog).getByRole('button', { name: 'Invia il link' }));

    expect(
      await within(dialog).findByText('Questo indirizzo email è già in uso.'),
    ).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Invia il link' }));

    expect(
      await within(dialog).findByText('Troppi tentativi. Riprova tra 1800 secondi'),
    ).toBeInTheDocument();
  });

  it('validates the new email before sending it', async () => {
    const user = userEvent.setup();
    allowIdentityConfirmation();
    const bodies = recordJson('post', '/api/v1/me/email', () =>
      HttpResponse.json({ data: PENDING_CHANGE }, { status: 201 }),
    );

    renderProfile();

    await user.click(await screen.findByRole('button', { name: 'Cambia email' }));
    await confirmIdentity(user);
    const dialog = await screen.findByRole('dialog', { name: 'Cambia email' });
    await user.type(within(dialog).getByLabelText('Nuova email'), 'giulia.nuova');
    await user.click(within(dialog).getByRole('button', { name: 'Invia il link' }));

    expect(
      await within(dialog).findByText('Inserisci un indirizzo email valido.'),
    ).toBeInTheDocument();
    expect(bodies).toEqual([]);
  });

  it('shows the pending change and cancels it', async () => {
    const user = userEvent.setup();
    const bodies = recordJson(
      'delete',
      '/api/v1/me/email-change',
      () => new HttpResponse(null, { status: 204 }),
    );
    serveMe(buildUser());

    renderProfile(buildUser({ pending_email_change: PENDING_CHANGE }));

    const card = within(await screen.findByRole('region', { name: 'Email' }));
    expect(card.getByText('Cambio email in attesa di conferma')).toBeInTheDocument();
    expect(card.getByText('giulia.nuova@esempio.it')).toBeInTheDocument();
    expect(card.getByText(/continuerai a usare giulia\.ferri@esempio\.it/)).toBeInTheDocument();
    expect(card.getByText('Scadenza del link: 11 ottobre 2026, 19:30.')).toBeInTheDocument();
    expect(card.queryByRole('button', { name: 'Cambia email' })).not.toBeInTheDocument();
    await user.click(card.getByRole('button', { name: 'Annulla la richiesta' }));

    expect(await screen.findByText('Richiesta annullata')).toBeInTheDocument();
    expect(bodies).toEqual([null]);
    expect(await card.findByRole('button', { name: 'Cambia email' })).toBeInTheDocument();
  });

  it('reloads the account when the pending change no longer exists', async () => {
    const user = userEvent.setup();
    server.use(
      http.delete(apiUrl('/api/v1/me/email-change'), () =>
        HttpResponse.json({ message: 'La risorsa richiesta non esiste.' }, { status: 404 }),
      ),
    );
    let meRequests = 0;
    server.use(
      http.get(apiUrl('/api/v1/me'), () => {
        meRequests += 1;
        return HttpResponse.json({ data: buildUser() });
      }),
    );

    renderProfile(buildUser({ pending_email_change: PENDING_CHANGE }));

    const card = within(await screen.findByRole('region', { name: 'Email' }));
    await user.click(card.getByRole('button', { name: 'Annulla la richiesta' }));

    expect(await card.findByRole('button', { name: 'Cambia email' })).toBeInTheDocument();
    expect(meRequests).toBe(1);
    expect(card.queryByText('Cambio email in attesa di conferma')).not.toBeInTheDocument();
    expect(screen.queryByText('La risorsa richiesta non esiste.')).not.toBeInTheDocument();
    expect(screen.queryByText('Richiesta annullata')).not.toBeInTheDocument();
  });

  it('keeps the pending change and shows the error when the cancellation fails', async () => {
    const user = userEvent.setup();
    server.use(
      http.delete(apiUrl('/api/v1/me/email-change'), () =>
        HttpResponse.json({ message: 'Errore.' }, { status: 500 }),
      ),
    );

    renderProfile(buildUser({ pending_email_change: PENDING_CHANGE }));

    const card = within(await screen.findByRole('region', { name: 'Email' }));
    await user.click(card.getByRole('button', { name: 'Annulla la richiesta' }));

    expect(
      await screen.findByText('Si è verificato un errore imprevisto. Riprova più tardi.'),
    ).toBeInTheDocument();
    expect(card.getByText('Cambio email in attesa di conferma')).toBeInTheDocument();
    expect(card.queryByRole('button', { name: 'Cambia email' })).not.toBeInTheDocument();
  });
});
