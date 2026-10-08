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
  validationError,
} from '@/test/authHelpers';

vi.mock('@simplewebauthn/browser', () => ({
  browserSupportsWebAuthn: vi.fn(() => true),
  startAuthentication: vi.fn(),
}));

const RESET_ROUTE = '/reset-password?token=abc123&email=davide.conti%40esempio.it';

function tokenCheck(answer) {
  return recordPost('/api/v1/reset-password/check', () => answer);
}

function invalidToken() {
  return domainError(
    422,
    'invalid_reset_token',
    'Il link per reimpostare la password non è valido o è scaduto.',
  );
}

async function fillPasswords(user) {
  await user.type(await screen.findByLabelText('Nuova password'), 'NuovaPassword2026');
  await user.type(screen.getByLabelText('Conferma la nuova password'), 'NuovaPassword2026');
}

describe('ResetPasswordPage', () => {
  beforeEach(() => {
    browserSupportsWebAuthn.mockReturnValue(true);
    startAuthentication.mockReset();
  });

  it('checks the link once and shows the form for the account', async () => {
    const checks = tokenCheck(HttpResponse.json({ requires_second_factor: false, methods: [] }));
    renderApp(RESET_ROUTE, { strict: true });

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Scegli una nuova password' }),
    ).toBeInTheDocument();
    expect(screen.getByText('davide.conti@esempio.it')).toBeInTheDocument();
    expect(screen.queryByText('Verifica in due passaggi')).not.toBeInTheDocument();
    expect(checks).toEqual([{ token: 'abc123', email: 'davide.conti@esempio.it' }]);
  });

  it('shows the expired link for an invalid token', async () => {
    tokenCheck(invalidToken());
    renderApp(RESET_ROUTE);

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Il link è scaduto' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Richiedi un nuovo link' })).toHaveAttribute(
      'href',
      '/forgot-password',
    );
    expect(screen.getByRole('link', { name: "Torna all'accesso" })).toHaveAttribute(
      'href',
      '/login',
    );
  });

  it('shows the expired link when the parameters are missing', async () => {
    const checks = tokenCheck(HttpResponse.json({ requires_second_factor: false, methods: [] }));
    renderApp('/reset-password?token=abc123');

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Il link è scaduto' }),
    ).toBeInTheDocument();
    expect(checks).toEqual([]);
  });

  it('saves the new password and sends to the login with the message', async () => {
    const user = userEvent.setup();
    tokenCheck(HttpResponse.json({ requires_second_factor: false, methods: [] }));
    const bodies = recordPost('/api/v1/reset-password', () =>
      HttpResponse.json({
        message: 'La password è stata reimpostata: accedi con la nuova password.',
      }),
    );
    const { router } = renderApp(RESET_ROUTE);

    await fillPasswords(user);
    await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

    expect(await screen.findByRole('heading', { level: 1, name: 'Accedi' })).toBeInTheDocument();
    expect(
      screen.getByText('La password è stata reimpostata: accedi con la nuova password.'),
    ).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/login');
    expect(bodies).toEqual([
      {
        token: 'abc123',
        email: 'davide.conti@esempio.it',
        password: 'NuovaPassword2026',
        password_confirmation: 'NuovaPassword2026',
      },
    ]);
  });

  it('asks for the second factor when the account needs it', async () => {
    const user = userEvent.setup();
    tokenCheck(
      HttpResponse.json({ requires_second_factor: true, methods: ['totp', 'recovery_code'] }),
    );
    const bodies = recordPost('/api/v1/reset-password', () =>
      HttpResponse.json({ message: 'Fatto.' }),
    );
    renderApp(RESET_ROUTE);

    expect(
      await screen.findByRole('heading', { level: 2, name: 'Verifica in due passaggi' }),
    ).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Usa una passkey' })).not.toBeInTheDocument();
    await fillPasswords(user);
    await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));
    expect(await screen.findByText('Inserisci il codice a 6 cifre.')).toBeInTheDocument();

    await user.type(screen.getByLabelText('Codice a 6 cifre'), '123456');
    await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

    await waitFor(() =>
      expect(bodies).toEqual([
        {
          token: 'abc123',
          email: 'davide.conti@esempio.it',
          password: 'NuovaPassword2026',
          password_confirmation: 'NuovaPassword2026',
          method: 'totp',
          code: '123456',
        },
      ]),
    );
  });

  it('uses a passkey as second factor', async () => {
    const user = userEvent.setup();
    tokenCheck(
      HttpResponse.json({
        requires_second_factor: true,
        methods: ['totp', 'recovery_code', 'passkey'],
      }),
    );
    const optionRequests = recordPost('/api/v1/reset-password/passkey-options', () =>
      HttpResponse.json({ data: { options: { challenge: 'reset' } } }),
    );
    const bodies = recordPost('/api/v1/reset-password', () =>
      HttpResponse.json({ message: 'Fatto.' }),
    );
    startAuthentication.mockResolvedValue(PASSKEY_RESPONSE);
    renderApp(RESET_ROUTE);

    await fillPasswords(user);
    await user.click(screen.getByRole('button', { name: 'Usa una passkey' }));
    await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

    await screen.findByRole('heading', { level: 1, name: 'Accedi' });
    expect(optionRequests).toEqual([{ token: 'abc123', email: 'davide.conti@esempio.it' }]);
    expect(bodies[0]).toMatchObject({ method: 'passkey', credential: PASSKEY_RESPONSE });
  });

  it('shows a wrong code on the field', async () => {
    const user = userEvent.setup();
    tokenCheck(
      HttpResponse.json({ requires_second_factor: true, methods: ['totp', 'recovery_code'] }),
    );
    recordPost('/api/v1/reset-password', () => validationError('code', 'Codice non valido.'));
    renderApp(RESET_ROUTE);

    await fillPasswords(user);
    await user.type(screen.getByLabelText('Codice a 6 cifre'), '000000');
    await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

    expect(await screen.findByText('Codice non valido.')).toBeInTheDocument();
  });

  it('shows the expired link when the token expires before saving', async () => {
    const user = userEvent.setup();
    tokenCheck(HttpResponse.json({ requires_second_factor: false, methods: [] }));
    recordPost('/api/v1/reset-password', () => invalidToken());
    renderApp(RESET_ROUTE);

    await fillPasswords(user);
    await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Il link è scaduto' }),
    ).toBeInTheDocument();
  });

  it('shows a loading state while the link is checked', async () => {
    let release;
    tokenCheck(
      new Promise((resolve) => {
        release = () => resolve(HttpResponse.json({ requires_second_factor: false, methods: [] }));
      }),
    );
    renderApp(RESET_ROUTE);

    expect(await screen.findByRole('status', { name: 'Caricamento in corso' })).toBeInTheDocument();
    release();
    expect(
      await screen.findByRole('heading', { level: 1, name: 'Scegli una nuova password' }),
    ).toBeInTheDocument();
  });

  it('retries the check after an error of the server', async () => {
    const user = userEvent.setup();
    const checks = recordPost('/api/v1/reset-password/check', ({ call }) =>
      call === 1
        ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
        : HttpResponse.json({ requires_second_factor: false, methods: [] }),
    );
    renderApp(RESET_ROUTE);

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Si è verificato un errore imprevisto. Riprova più tardi.',
    );
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Scegli una nuova password' }),
    ).toBeInTheDocument();
    expect(checks).toHaveLength(2);
  });

  it('describes the new password with the error and the requirements', async () => {
    const user = userEvent.setup();
    tokenCheck(HttpResponse.json({ requires_second_factor: false, methods: [] }));
    recordPost('/api/v1/reset-password', () =>
      validationError('password', 'Questa password è comparsa in una violazione di dati.'),
    );
    renderApp(RESET_ROUTE);

    await fillPasswords(user);
    await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

    const password = screen.getByLabelText('Nuova password');
    await waitFor(() =>
      expect(password).toHaveAccessibleDescription(
        expect.stringContaining('Questa password è comparsa in una violazione di dati.'),
      ),
    );
    expect(password).toHaveAccessibleDescription(
      expect.stringContaining('Almeno 10 caratteri, requisito soddisfatto'),
    );
  });
});
