import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { domainError, recordPost, renderApp, validationError } from '@/test/authHelpers';
import { buildUser } from '@/test/factories';

describe('ConfirmEmailChangePage', () => {
  it('confirms the new address once', async () => {
    const bodies = recordPost('/api/v1/email-change/confirm', () =>
      HttpResponse.json({ data: { email: 'giulia.nuova@esempio.it' } }),
    );
    renderApp('/confirm-email-change?token=token-di-conferma', { strict: true });

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Email aggiornata' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "D'ora in poi accederai con giulia.nuova@esempio.it e riceverai lì le email sui tuoi ordini.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Vai al tuo account' })).toHaveAttribute(
      'href',
      '/account/profile',
    );
    expect(bodies).toEqual([{ token: 'token-di-conferma' }]);
  });

  it('shows the expired link for an invalid token', async () => {
    recordPost('/api/v1/email-change/confirm', () =>
      domainError(422, 'invalid_email_change_token', 'Il link non è valido o è scaduto.'),
    );
    renderApp('/confirm-email-change?token=vecchio', { user: buildUser() });

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Il link è scaduto' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        'Per sicurezza il link resta valido 60 minuti e si può usare una sola volta. Puoi chiedere un nuovo cambio dal tuo profilo.',
      ),
    ).toBeInTheDocument();
  });

  it('shows the expired link without a token', async () => {
    const bodies = recordPost('/api/v1/email-change/confirm', () =>
      validationError('token', 'Il token è obbligatorio.'),
    );
    renderApp('/confirm-email-change');

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Il link è scaduto' }),
    ).toBeInTheDocument();
    expect(bodies).toEqual([]);
  });

  it('explains that the address has been taken in the meantime', async () => {
    recordPost('/api/v1/email-change/confirm', () =>
      domainError(409, 'email_change_unavailable', 'Questo indirizzo email è già in uso.'),
    );
    renderApp('/confirm-email-change?token=token-di-conferma');

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Indirizzo non disponibile' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        'Questo indirizzo email è già in uso. Puoi chiedere il cambio verso un altro indirizzo dal tuo profilo.',
      ),
    ).toBeInTheDocument();
  });
});

describe('CancelEmailChangePage', () => {
  it('cancels the request once, even as a guest', async () => {
    const bodies = recordPost(
      '/api/v1/email-change/cancel',
      () => new HttpResponse(null, { status: 204 }),
    );
    renderApp('/cancel-email-change?token=token-di-annullamento', { strict: true });

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Richiesta annullata' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        'Se non hai chiesto tu la modifica, ti consigliamo di cambiare la password.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Cambia la password' })).toHaveAttribute(
      'href',
      '/account/security',
    );
    expect(screen.getByRole('link', { name: 'Torna al negozio' })).toHaveAttribute('href', '/');
    expect(bodies).toEqual([{ token: 'token-di-annullamento' }]);
  });

  it('suggests to contact the support when the link no longer works', async () => {
    recordPost('/api/v1/email-change/cancel', () =>
      domainError(422, 'invalid_email_change_token', 'Il link non è valido o è scaduto.'),
    );
    renderApp('/cancel-email-change?token=usato');

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Il link è scaduto' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: "Contatta l'assistenza" })).toHaveAttribute(
      'href',
      '/assistenza#contatti',
    );
  });
});

describe.each([
  [
    'ConfirmEmailChangePage',
    '/api/v1/email-change/confirm',
    '/confirm-email-change?token=token',
    () => HttpResponse.json({ data: { email: 'giulia.nuova@esempio.it' } }),
    'Email aggiornata',
  ],
  [
    'CancelEmailChangePage',
    '/api/v1/email-change/cancel',
    '/cancel-email-change?token=token',
    () => new HttpResponse(null, { status: 204 }),
    'Richiesta annullata',
  ],
])('%s states', (page, path, route, success, title) => {
  it('shows a loading state while the link is checked', async () => {
    let release;
    recordPost(
      path,
      () =>
        new Promise((resolve) => {
          release = () => resolve(success());
        }),
    );
    renderApp(route);

    expect(await screen.findByRole('status', { name: 'Caricamento in corso' })).toBeInTheDocument();
    release();
    expect(await screen.findByRole('heading', { level: 1, name: title })).toBeInTheDocument();
  });

  it('retries after an error of the server', async () => {
    const user = userEvent.setup();
    const bodies = recordPost(path, ({ call }) =>
      call === 1 ? HttpResponse.json({ message: 'Errore.' }, { status: 500 }) : success(),
    );
    renderApp(route);

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Si è verificato un errore imprevisto. Riprova più tardi.',
    );
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(await screen.findByRole('heading', { level: 1, name: title })).toBeInTheDocument();
    expect(bodies).toEqual([{ token: 'token' }, { token: 'token' }]);
  });
});
