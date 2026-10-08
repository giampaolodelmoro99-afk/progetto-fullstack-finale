import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { recordPost, renderApp } from '@/test/authHelpers';
import { buildUser } from '@/test/factories';

const UNVERIFIED = { email: 'paolo.greco@esempio.it', email_verified_at: null };
const BANNER_TEXT =
  'Conferma il tuo indirizzo email per completare gli acquisti. Ti abbiamo inviato un link a paolo.greco@esempio.it.';

describe('EmailVerificationBanner', () => {
  it('asks an unverified user to confirm the email in the shop', async () => {
    renderApp('/', { user: buildUser(UNVERIFIED) });

    expect(await screen.findByText(BANNER_TEXT)).toBeInTheDocument();
  });

  it('is not shown to verified users and guests', async () => {
    const { unmount } = renderApp('/', { user: buildUser() });
    await screen.findByRole('heading', { level: 1, name: 'Suono, in silenzio' });
    expect(screen.queryByText(/Conferma il tuo indirizzo email/)).not.toBeInTheDocument();
    unmount();

    renderApp('/');
    await screen.findByRole('heading', { level: 1, name: 'Suono, in silenzio' });
    expect(screen.queryByText(/Conferma il tuo indirizzo email/)).not.toBeInTheDocument();
  });

  it.each([
    ['/verify-email', 'Controlla la tua email'],
    ['/reset-password', 'Il link è scaduto'],
    ['/confirm-email-change', 'Il link è scaduto'],
  ])('is hidden in the access page %s', async (route, title) => {
    renderApp(route, { user: buildUser(UNVERIFIED) });

    await screen.findByRole('heading', { level: 1, name: title });
    expect(screen.queryByText(BANNER_TEXT)).not.toBeInTheDocument();
  });

  it('sends the email again', async () => {
    const user = userEvent.setup();
    const resends = recordPost('/api/v1/email/verification-notification', () =>
      HttpResponse.json(
        { message: 'Ti abbiamo inviato un nuovo link di conferma.' },
        { status: 202 },
      ),
    );
    renderApp('/', { user: buildUser(UNVERIFIED) });

    await user.click(await screen.findByRole('button', { name: "Invia di nuovo l'email" }));

    expect(
      await screen.findByText('Ti abbiamo inviato un nuovo link di conferma.'),
    ).toBeInTheDocument();
    expect(resends).toHaveLength(1);
  });
});
