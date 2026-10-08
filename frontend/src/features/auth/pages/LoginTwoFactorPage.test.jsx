import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HttpResponse } from 'msw';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { browserSupportsWebAuthn, startAuthentication } from '@simplewebauthn/browser';
import {
  PASSKEY_RESPONSE,
  domainError,
  recordPost,
  renderApp,
  tooManyAttempts,
  validationError,
} from '@/test/authHelpers';
import { buildUser } from '@/test/factories';

vi.mock('@simplewebauthn/browser', () => ({
  browserSupportsWebAuthn: vi.fn(() => true),
  startAuthentication: vi.fn(),
}));

function challengeRoute(
  methods = ['totp', 'recovery_code'],
  from = { pathname: '/account/orders' },
) {
  return { pathname: '/login/two-factor', state: { methods, from } };
}

describe('LoginTwoFactorPage', () => {
  beforeEach(() => {
    browserSupportsWebAuthn.mockReturnValue(true);
    startAuthentication.mockReset();
  });

  it('sends back to the login when opened without a challenge', async () => {
    const { router } = renderApp('/login/two-factor');

    expect(await screen.findByRole('heading', { level: 1, name: 'Accedi' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/login');
  });

  it('completes the login with the code of the app and goes back to the previous page', async () => {
    const user = userEvent.setup();
    const bodies = recordPost('/api/v1/login/two-factor', () =>
      HttpResponse.json({ data: buildUser() }),
    );
    const { router } = renderApp(challengeRoute());

    expect(
      await screen.findByText("Apri l'app di autenticazione e inserisci il codice a 6 cifre."),
    ).toBeInTheDocument();
    expect(screen.getByText('Il codice cambia ogni 30 secondi.')).toBeInTheDocument();
    await user.type(screen.getByLabelText('Codice a 6 cifre'), '123456');
    await user.click(screen.getByRole('button', { name: 'Verifica' }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/account/orders'));
    expect(bodies).toEqual([{ method: 'totp', code: '123456' }]);
  });

  it('asks for the code before sending', async () => {
    const user = userEvent.setup();
    const bodies = recordPost('/api/v1/login/two-factor', () =>
      HttpResponse.json({ data: buildUser() }),
    );
    renderApp(challengeRoute());

    await user.click(await screen.findByRole('button', { name: 'Verifica' }));

    expect(await screen.findByText('Inserisci il codice a 6 cifre.')).toBeInTheDocument();
    expect(bodies).toEqual([]);
  });

  it('shows a wrong code on the field and stays on the page', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/login/two-factor', () => validationError('code', 'Codice non valido.'));
    const { router } = renderApp(challengeRoute());

    await user.type(await screen.findByLabelText('Codice a 6 cifre'), '000000');
    await user.click(screen.getByRole('button', { name: 'Verifica' }));

    expect(await screen.findByText('Codice non valido.')).toBeInTheDocument();
    expect(screen.getByLabelText('Codice a 6 cifre')).toHaveAttribute('aria-invalid', 'true');
    expect(router.state.location.pathname).toBe('/login/two-factor');
  });

  it('goes back to the login when the challenge has expired', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/login/two-factor', () =>
      domainError(422, 'two_factor_challenge_expired', 'La verifica è scaduta: accedi di nuovo.'),
    );
    const { router } = renderApp(challengeRoute());

    await user.type(await screen.findByLabelText('Codice a 6 cifre'), '123456');
    await user.click(screen.getByRole('button', { name: 'Verifica' }));

    expect(await screen.findByRole('heading', { level: 1, name: 'Accedi' })).toBeInTheDocument();
    expect(screen.getByText('La verifica è scaduta: accedi di nuovo.')).toBeInTheDocument();
    expect(router.state.location.state.from).toEqual({ pathname: '/account/orders' });
  });

  it('accepts a recovery code and warns when few codes are left', async () => {
    const user = userEvent.setup();
    const bodies = recordPost('/api/v1/login/two-factor', () =>
      HttpResponse.json({
        data: buildUser(),
        meta: { recovery_codes_remaining: 2, recovery_codes_low: true },
      }),
    );
    const { router } = renderApp(challengeRoute());

    await user.click(await screen.findByRole('button', { name: 'Usa un codice di recupero' }));
    expect(
      screen.getByText(
        'Inserisci uno dei codici di recupero che hai salvato quando hai attivato la doppia autenticazione.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByText('Ogni codice si può usare una sola volta.')).toBeInTheDocument();
    await user.type(screen.getByLabelText('Codice di recupero'), 'K7PM-2XQA');
    await user.click(screen.getByRole('button', { name: 'Verifica' }));

    expect(
      await screen.findByText(
        'Ti restano 2 codici di recupero: generane di nuovi dalla pagina Sicurezza.',
      ),
    ).toBeInTheDocument();
    expect(bodies).toEqual([{ method: 'recovery_code', code: 'K7PM-2XQA' }]);

    await user.click(screen.getByRole('button', { name: 'Vai a Sicurezza' }));
    await waitFor(() => expect(router.state.location.pathname).toBe('/account/security'));
  });

  it('offers the passkey only when the user has one', async () => {
    const { unmount } = renderApp(challengeRoute(['totp', 'recovery_code']));

    await screen.findByLabelText('Codice a 6 cifre');
    expect(screen.queryByRole('button', { name: 'Usa una passkey' })).not.toBeInTheDocument();
    unmount();

    renderApp(challengeRoute(['totp', 'recovery_code', 'passkey']));
    expect(await screen.findByRole('button', { name: 'Usa una passkey' })).toBeInTheDocument();
  });

  it('hides the passkey when the browser does not support it', async () => {
    browserSupportsWebAuthn.mockReturnValue(false);
    renderApp(challengeRoute(['totp', 'recovery_code', 'passkey']));

    await screen.findByLabelText('Codice a 6 cifre');
    expect(screen.queryByRole('button', { name: 'Usa una passkey' })).not.toBeInTheDocument();
  });

  it('completes the login with a passkey', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/login/two-factor/passkey-options', () =>
      HttpResponse.json({ data: { options: { challenge: 'two-factor' } } }),
    );
    const bodies = recordPost('/api/v1/login/two-factor', () =>
      HttpResponse.json({ data: buildUser() }),
    );
    startAuthentication.mockResolvedValue(PASSKEY_RESPONSE);
    const { router } = renderApp(challengeRoute(['totp', 'recovery_code', 'passkey']));

    await user.click(await screen.findByRole('button', { name: 'Usa una passkey' }));
    expect(
      screen.getByText('Usa la passkey registrata su questo dispositivo.'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Usa la passkey' }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/account/orders'));
    expect(startAuthentication).toHaveBeenCalledWith({ optionsJSON: { challenge: 'two-factor' } });
    expect(bodies).toEqual([{ method: 'passkey', credential: PASSKEY_RESPONSE }]);
  });

  it('shows a rejected passkey under the passkey choice', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/login/two-factor/passkey-options', () =>
      HttpResponse.json({ data: { options: { challenge: 'two-factor' } } }),
    );
    recordPost('/api/v1/login/two-factor', () =>
      validationError('credential', 'Passkey non valida: riprova.'),
    );
    startAuthentication.mockResolvedValue(PASSKEY_RESPONSE);
    renderApp(challengeRoute(['totp', 'recovery_code', 'passkey']));

    await user.click(await screen.findByRole('button', { name: 'Usa una passkey' }));
    await user.click(screen.getByRole('button', { name: 'Usa la passkey' }));

    expect(await screen.findByText('Passkey non valida: riprova.')).toBeInTheDocument();
  });

  it('links back to the login keeping the previous page', async () => {
    const user = userEvent.setup();
    const { router } = renderApp(challengeRoute());

    await user.click(await screen.findByRole('link', { name: "Torna all'accesso" }));

    expect(router.state.location.pathname).toBe('/login');
    expect(router.state.location.state.from).toEqual({ pathname: '/account/orders' });
  });

  it('blocks the button after too many wrong codes', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/login/two-factor', () => tooManyAttempts(60));
    renderApp(challengeRoute());

    await user.type(await screen.findByLabelText('Codice a 6 cifre'), '123456');
    await user.click(screen.getByRole('button', { name: 'Verifica' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Troppi tentativi. Riprova tra 60 secondi',
    );
    expect(screen.getByRole('button', { name: 'Verifica' })).toBeDisabled();
  });
});
