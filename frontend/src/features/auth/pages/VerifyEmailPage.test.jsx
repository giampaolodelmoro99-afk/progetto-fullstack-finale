import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { recordPost, renderApp, tooManyAttempts } from '@/test/authHelpers';
import { buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';

const LINK = '/verify-email?id=7&hash=abc&expires=1790000000&signature=firma';

function recordVerification(respond) {
  const requests = [];
  server.use(
    http.post(apiUrl('/api/v1/email/verify/:id/:hash'), ({ request, params }) => {
      const url = new URL(request.url);
      requests.push({
        id: params.id,
        hash: params.hash,
        expires: url.searchParams.get('expires'),
        signature: url.searchParams.get('signature'),
      });
      return respond();
    }),
  );

  return requests;
}

function expiredLink() {
  return HttpResponse.json(
    { message: 'Il link di verifica non è valido o è scaduto.' },
    { status: 403 },
  );
}

describe('VerifyEmailPage', () => {
  it('confirms the email once from the link, even as a guest', async () => {
    const requests = recordVerification(() =>
      HttpResponse.json({ data: { email: 'paolo.greco@esempio.it' } }),
    );
    renderApp(LINK, { strict: true });

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Email confermata' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "Grazie: l'indirizzo paolo.greco@esempio.it è confermato. Ora puoi completare i tuoi acquisti.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Vai al negozio' })).toHaveAttribute('href', '/');
    expect(screen.getByRole('link', { name: 'Vai al tuo account' })).toHaveAttribute(
      'href',
      '/account/profile',
    );
    expect(requests).toEqual([{ id: '7', hash: 'abc', expires: '1790000000', signature: 'firma' }]);
  });

  it('shows the address received, not the one of the signed in user', async () => {
    recordVerification(() => HttpResponse.json({ data: { email: 'paolo.greco@esempio.it' } }));
    renderApp(LINK, { user: buildUser({ email: 'giulia.ferri@esempio.it' }) });

    expect(
      await screen.findByText(/l'indirizzo paolo\.greco@esempio\.it è confermato/),
    ).toBeInTheDocument();
  });

  it('refreshes the user after the confirmation', async () => {
    let meCalls = 0;
    server.use(
      http.get(apiUrl('/api/v1/me'), () => {
        meCalls += 1;
        return HttpResponse.json({
          data: buildUser({ email_verified_at: '2026-10-08T10:00:00+02:00' }),
        });
      }),
    );
    recordVerification(() => HttpResponse.json({ data: { email: 'giulia.ferri@esempio.it' } }));
    renderApp(LINK, { user: buildUser({ email_verified_at: null }) });

    await screen.findByRole('heading', { level: 1, name: 'Email confermata' });
    await waitFor(() => expect(meCalls).toBe(1));
  });

  it('lets a guest log in to ask for a new link when the link has expired', async () => {
    const user = userEvent.setup();
    recordVerification(expiredLink);
    const { router } = renderApp(LINK);

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Il link è scaduto' }),
    ).toBeInTheDocument();
    expect(screen.getByText(/Accedi e richiedine uno nuovo\./)).toBeInTheDocument();

    await user.click(screen.getByRole('link', { name: 'Invia un nuovo link' }));

    expect(router.state.location.pathname).toBe('/login');
    expect(router.state.location.state.from).toEqual({ pathname: '/verify-email' });
  });

  it('sends a new link to a signed in user when the link has expired', async () => {
    const user = userEvent.setup();
    recordVerification(expiredLink);
    const resends = recordPost('/api/v1/email/verification-notification', () =>
      HttpResponse.json(
        { message: 'Ti abbiamo inviato un nuovo link di conferma.' },
        { status: 202 },
      ),
    );
    renderApp(LINK, {
      user: buildUser({ email: 'paolo.greco@esempio.it', email_verified_at: null }),
    });

    expect(
      await screen.findByText(/Richiedine uno nuovo: lo invieremo a paolo\.greco@esempio\.it\./),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Invia un nuovo link' }));

    expect(
      await screen.findByText('Ti abbiamo inviato un nuovo link di conferma.'),
    ).toBeInTheDocument();
    expect(resends).toHaveLength(1);
  });

  it('treats an incomplete link as expired without calling the api', async () => {
    const requests = recordVerification(() => HttpResponse.json({ data: { email: 'a@b.it' } }));
    renderApp('/verify-email?id=7&hash=abc');

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Il link è scaduto' }),
    ).toBeInTheDocument();
    expect(requests).toEqual([]);
  });

  it('explains when too many links have been opened', async () => {
    recordVerification(() => tooManyAttempts(30));
    renderApp(LINK);

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Troppi tentativi' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "Hai aperto troppi link in poco tempo. Riapri il link dall'email tra qualche minuto.",
      ),
    ).toBeInTheDocument();
  });

  it('asks to check the inbox and sends the link again', async () => {
    const user = userEvent.setup();
    const resends = recordPost('/api/v1/email/verification-notification', () =>
      HttpResponse.json(
        { message: 'Ti abbiamo inviato un nuovo link di conferma.' },
        { status: 202 },
      ),
    );
    server.use(
      http.get(apiUrl('/api/v1/me'), () =>
        HttpResponse.json({
          data: buildUser({ email: 'paolo.greco@esempio.it', email_verified_at: null }),
        }),
      ),
    );
    renderApp('/verify-email', {
      user: buildUser({ email: 'paolo.greco@esempio.it', email_verified_at: null }),
    });

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Controlla la tua email' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        'Ti abbiamo inviato un link a paolo.greco@esempio.it. Aprilo per confermare il tuo indirizzo: il link resta valido per 24 ore.',
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Non la trovi? Controlla anche nella cartella spam.'),
    ).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Invia di nuovo il link' }));

    expect(
      await screen.findByText('Ti abbiamo inviato un nuovo link di conferma.'),
    ).toBeInTheDocument();
    expect(resends).toHaveLength(1);
  });

  it('shows the confirmation to a user whose email is already verified', async () => {
    renderApp('/verify-email', { user: buildUser({ email: 'giulia.ferri@esempio.it' }) });

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Email confermata' }),
    ).toBeInTheDocument();
    expect(screen.getByText(/giulia\.ferri@esempio\.it è confermato/)).toBeInTheDocument();
  });

  it('shows a loading state while the link is checked', async () => {
    let release;
    recordVerification(
      () =>
        new Promise((resolve) => {
          release = () => resolve(HttpResponse.json({ data: { email: 'paolo.greco@esempio.it' } }));
        }),
    );
    renderApp(LINK);

    expect(await screen.findByRole('status', { name: 'Caricamento in corso' })).toBeInTheDocument();
    release();
    expect(
      await screen.findByRole('heading', { level: 1, name: 'Email confermata' }),
    ).toBeInTheDocument();
  });

  it('retries the confirmation after an error of the server', async () => {
    const user = userEvent.setup();
    let call = 0;
    const requests = recordVerification(() => {
      call += 1;
      return call === 1
        ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
        : HttpResponse.json({ data: { email: 'paolo.greco@esempio.it' } });
    });
    renderApp(LINK);

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Si è verificato un errore imprevisto. Riprova più tardi.',
    );
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Email confermata' }),
    ).toBeInTheDocument();
    expect(requests).toHaveLength(2);
  });
});
