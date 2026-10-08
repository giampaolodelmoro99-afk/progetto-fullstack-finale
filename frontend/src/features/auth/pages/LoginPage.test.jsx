import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HttpResponse } from 'msw';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { browserSupportsWebAuthn, startAuthentication } from '@simplewebauthn/browser';
import { queryKeys } from '@/api/queryKeys';
import {
  PASSKEY_RESPONSE,
  domainError,
  recordPost,
  renderApp,
  tooManyAttempts,
} from '@/test/authHelpers';
import { buildUser } from '@/test/factories';

vi.mock('@simplewebauthn/browser', () => ({
  browserSupportsWebAuthn: vi.fn(() => true),
  startAuthentication: vi.fn(),
}));

async function fillLogin(user, email = 'giulia.ferri@esempio.it', password = 'Silicio2026') {
  await user.type(await screen.findByLabelText('Email'), email);
  await user.type(screen.getByLabelText('Password'), password);
}

describe('LoginPage', () => {
  beforeEach(() => {
    browserSupportsWebAuthn.mockReturnValue(true);
    startAuthentication.mockReset();
  });

  it('logs in and goes back to the page the user came from', async () => {
    const user = userEvent.setup();
    const bodies = recordPost('/api/v1/login', () =>
      HttpResponse.json({ data: buildUser({ name: 'Giulia Ferri' }) }),
    );
    const { router, queryClient } = renderApp({
      pathname: '/login',
      state: { from: { pathname: '/account/security' } },
    });

    await fillLogin(user);
    await user.click(screen.getByRole('button', { name: 'Accedi' }));

    expect(await screen.findByRole('heading', { level: 2, name: 'Sicurezza' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/account/security');
    expect(bodies).toEqual([{ email: 'giulia.ferri@esempio.it', password: 'Silicio2026' }]);
    expect(queryClient.getQueryData(queryKeys.me)).toMatchObject({ name: 'Giulia Ferri' });
  });

  it('goes to the home when there is no previous page', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/login', () => HttpResponse.json({ data: buildUser() }));
    const { router } = renderApp('/login');

    await fillLogin(user);
    await user.click(screen.getByRole('button', { name: 'Accedi' }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/'));
  });

  it('shows a single alert for wrong credentials, without errors on the fields', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/login', () =>
      domainError(422, 'invalid_credentials', 'Credenziali non valide'),
    );
    renderApp('/login');

    await fillLogin(user, 'giulia.ferri@esempio.it', 'Sbagliata2026');
    await user.click(screen.getByRole('button', { name: 'Accedi' }));

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('Credenziali non valide');
    expect(alert).toHaveTextContent("Controlla l'email e la password e riprova.");
    expect(screen.getByLabelText('Email')).not.toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByLabelText('Password')).not.toHaveAttribute('aria-invalid', 'true');
  });

  it('validates the fields before sending', async () => {
    const user = userEvent.setup();
    const bodies = recordPost('/api/v1/login', () => HttpResponse.json({ data: buildUser() }));
    renderApp('/login');

    await user.type(await screen.findByLabelText('Email'), 'giulia');
    await user.click(screen.getByRole('button', { name: 'Accedi' }));

    expect(await screen.findByText('Inserisci un indirizzo email valido.')).toBeInTheDocument();
    expect(screen.getByText('Inserisci la password.')).toBeInTheDocument();
    expect(bodies).toEqual([]);
  });

  it('opens the second step with the methods received', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/login', () =>
      HttpResponse.json({ two_factor_required: true, methods: ['totp', 'recovery_code'] }),
    );
    const { router } = renderApp({
      pathname: '/login',
      state: { from: { pathname: '/account/orders' } },
    });

    await fillLogin(user, 'davide.conti@esempio.it');
    await user.click(screen.getByRole('button', { name: 'Accedi' }));

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Verifica in due passaggi' }),
    ).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/login/two-factor');
    expect(router.state.location.state).toEqual({
      methods: ['totp', 'recovery_code'],
      from: { pathname: '/account/orders' },
    });
  });

  it('shows the notice received from the previous page only once', async () => {
    const { router } = renderApp({
      pathname: '/login',
      state: {
        notice: {
          severity: 'success',
          message: 'La password è stata reimpostata: accedi con la nuova password.',
        },
      },
    });

    expect(
      await screen.findByText('La password è stata reimpostata: accedi con la nuova password.'),
    ).toBeInTheDocument();
    await waitFor(() => expect(router.state.location.state).toBeNull());
    expect(
      screen.getByText('La password è stata reimpostata: accedi con la nuova password.'),
    ).toBeInTheDocument();
  });

  describe('too many attempts', () => {
    beforeEach(() => {
      vi.useFakeTimers({ shouldAdvanceTime: true });
    });

    afterEach(() => {
      vi.useRealTimers();
    });

    it('counts down the seconds and blocks the button until they expire', async () => {
      const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
      recordPost('/api/v1/login', () => tooManyAttempts(45));
      renderApp('/login');

      await fillLogin(user);
      await user.click(screen.getByRole('button', { name: 'Accedi' }));

      const alert = await screen.findByRole('alert');
      expect(alert).toHaveTextContent(
        'Nel frattempo puoi scegliere una nuova password o accedere con una passkey.',
      );
      expect(
        within(alert).getByText('Troppi tentativi. Riprova tra 45 secondi', {
          selector: '[aria-hidden="true"]',
        }),
      ).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Accedi' })).toBeDisabled();
      expect(screen.getByRole('button', { name: 'Accedi con una passkey' })).toBeEnabled();

      await act(async () => vi.advanceTimersByTime(1000));
      expect(
        within(alert).getByText('Troppi tentativi. Riprova tra 44 secondi', {
          selector: '[aria-hidden="true"]',
        }),
      ).toBeInTheDocument();
      expect(
        within(alert).getByText('Troppi tentativi. Riprova tra 45 secondi', {
          selector: 'span:not([aria-hidden])',
        }),
      ).toBeInTheDocument();

      await act(async () => vi.advanceTimersByTime(44_000));
      expect(screen.queryByRole('alert')).not.toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Accedi' })).toBeEnabled();
    });

    it('asks to retry later when the waiting time is unknown', async () => {
      const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
      recordPost('/api/v1/login', () => tooManyAttempts(null));
      renderApp('/login');

      await fillLogin(user);
      await user.click(screen.getByRole('button', { name: 'Accedi' }));

      expect(await screen.findByRole('alert')).toHaveTextContent(
        'Troppi tentativi. Riprova tra poco',
      );
      expect(screen.getByRole('button', { name: 'Accedi' })).toBeEnabled();
    });
  });

  describe('passkey', () => {
    it('is hidden when the browser does not support passkeys', async () => {
      browserSupportsWebAuthn.mockReturnValue(false);
      renderApp('/login');

      await screen.findByLabelText('Email');
      expect(
        screen.queryByRole('button', { name: 'Accedi con una passkey' }),
      ).not.toBeInTheDocument();
    });

    it('logs in without a password', async () => {
      const user = userEvent.setup();
      recordPost('/api/v1/passkeys/login/options', () =>
        HttpResponse.json({ data: { options: { challenge: 'xyz' } } }),
      );
      const bodies = recordPost('/api/v1/passkeys/login', () =>
        HttpResponse.json({ data: buildUser() }),
      );
      startAuthentication.mockResolvedValue(PASSKEY_RESPONSE);
      const { router } = renderApp({
        pathname: '/login',
        state: { from: { pathname: '/account/profile' } },
      });

      await user.click(await screen.findByRole('button', { name: 'Accedi con una passkey' }));

      await waitFor(() => expect(router.state.location.pathname).toBe('/account/profile'));
      expect(startAuthentication).toHaveBeenCalledWith({ optionsJSON: { challenge: 'xyz' } });
      expect(bodies).toEqual([{ credential: PASSKEY_RESPONSE }]);
    });

    it('shows the message of a passkey that is not recognised', async () => {
      const user = userEvent.setup();
      recordPost('/api/v1/passkeys/login/options', () =>
        HttpResponse.json({ data: { options: { challenge: 'xyz' } } }),
      );
      recordPost('/api/v1/passkeys/login', () =>
        domainError(
          422,
          'invalid_passkey',
          'Passkey non riconosciuta: riprova o accedi con la password.',
        ),
      );
      startAuthentication.mockResolvedValue(PASSKEY_RESPONSE);
      renderApp('/login');

      await user.click(await screen.findByRole('button', { name: 'Accedi con una passkey' }));

      expect(await screen.findByRole('alert')).toHaveTextContent(
        'Passkey non riconosciuta: riprova o accedi con la password.',
      );
    });

    it('stays silent when the user closes the passkey prompt', async () => {
      const user = userEvent.setup();
      recordPost('/api/v1/passkeys/login/options', () =>
        HttpResponse.json({ data: { options: { challenge: 'xyz' } } }),
      );
      const bodies = recordPost('/api/v1/passkeys/login', () =>
        HttpResponse.json({ data: buildUser() }),
      );
      startAuthentication.mockRejectedValue(
        Object.assign(new Error('The operation was cancelled.'), { name: 'NotAllowedError' }),
      );
      renderApp('/login');

      await user.click(await screen.findByRole('button', { name: 'Accedi con una passkey' }));

      await waitFor(() =>
        expect(screen.getByRole('button', { name: 'Accedi con una passkey' })).toBeEnabled(),
      );
      expect(screen.queryByRole('alert')).not.toBeInTheDocument();
      expect(bodies).toEqual([]);
    });
  });
});
