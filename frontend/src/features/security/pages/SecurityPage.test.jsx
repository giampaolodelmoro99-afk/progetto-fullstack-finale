import { browserSupportsWebAuthn, startAuthentication } from '@simplewebauthn/browser';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PASSKEY_RESPONSE, recordPost, tooManyAttempts, validationError } from '@/test/authHelpers';
import {
  buildSecurityEvent,
  buildSecurityEventPage,
  buildSecurityOverview,
  buildTwoFactorOverview,
} from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { recordRequest, renderSecurityPage, serveOverview } from '@/test/security';

vi.mock('@simplewebauthn/browser', () => ({
  browserSupportsWebAuthn: vi.fn(() => true),
  startAuthentication: vi.fn(),
  startRegistration: vi.fn(),
}));

function passwordChanged(meta) {
  return HttpResponse.json({
    data: { password_changed_at: '2026-10-11T19:30:00+02:00' },
    ...(meta ? { meta } : {}),
  });
}

async function openPasswordForm(user) {
  await user.click(await screen.findByRole('button', { name: 'Cambia password' }));
}

async function fillPasswords(user) {
  await user.type(screen.getByLabelText('Password attuale'), 'Silicio2026');
  await user.type(screen.getByLabelText('Nuova password'), 'NuovaPassword2026');
  await user.type(screen.getByLabelText('Conferma la nuova password'), 'NuovaPassword2026');
}

describe('SecurityPage', () => {
  beforeEach(() => {
    browserSupportsWebAuthn.mockReturnValue(true);
    startAuthentication.mockReset();
  });

  it('shows the overview with the last password change', async () => {
    renderSecurityPage();

    expect(await screen.findByRole('heading', { name: 'Sicurezza' })).toBeInTheDocument();
    expect(await screen.findByText('Ultima modifica: 12 settembre 2026')).toBeInTheDocument();
    expect(screen.getByText('Disattivata')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Attiva' })).toBeInTheDocument();
    expect(screen.queryByText(/Vale anche come secondo fattore/)).not.toBeInTheDocument();
  });

  it('shows the active two factor authentication with the recovery codes left', async () => {
    serveOverview(buildTwoFactorOverview({ passkeys_count: 1 }));

    renderSecurityPage();

    expect(await screen.findByText('Attiva dal 12 settembre 2026')).toBeInTheDocument();
    expect(screen.getByText('Configurata il 12 settembre 2026')).toBeInTheDocument();
    expect(screen.getByText('Codici di recupero rimasti: 6 di 8')).toBeInTheDocument();
    expect(screen.getByText(/Vale anche come secondo fattore\./)).toBeInTheDocument();
  });

  it('shows a skeleton while the overview loads', async () => {
    server.use(
      http.get(apiUrl('/api/v1/me/security'), async () => {
        await new Promise((resolve) => setTimeout(resolve, 50));
        return HttpResponse.json({ data: buildSecurityOverview() });
      }),
    );

    renderSecurityPage();

    expect(
      await screen.findByRole('status', { name: 'Caricamento dei dati di sicurezza' }),
    ).toBeInTheDocument();
    expect(await screen.findByText('Ultima modifica: 12 settembre 2026')).toBeInTheDocument();
  });

  it('shows an error with a retry when the overview fails', async () => {
    const user = userEvent.setup();
    let calls = 0;
    server.use(
      http.get(apiUrl('/api/v1/me/security'), () => {
        calls += 1;
        return calls === 1
          ? HttpResponse.json({ message: 'Errore' }, { status: 500 })
          : HttpResponse.json({ data: buildSecurityOverview() });
      }),
    );

    renderSecurityPage();

    expect(
      await screen.findByText(
        'Non è stato possibile caricare i dati di sicurezza del tuo account.',
      ),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(await screen.findByText('Ultima modifica: 12 settembre 2026')).toBeInTheDocument();
  });

  describe('password change', () => {
    it('changes the password without a second factor when it is not active', async () => {
      const user = userEvent.setup();
      const bodies = recordRequest('put', '/api/v1/me/password', () => passwordChanged());

      renderSecurityPage();

      await openPasswordForm(user);
      expect(screen.queryByText('Verifica in due passaggi')).not.toBeInTheDocument();
      await fillPasswords(user);
      await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

      expect(await screen.findByText('Password cambiata')).toBeInTheDocument();
      expect(bodies).toEqual([
        {
          current_password: 'Silicio2026',
          password: 'NuovaPassword2026',
          password_confirmation: 'NuovaPassword2026',
        },
      ]);
      expect(screen.queryByLabelText('Password attuale')).not.toBeInTheDocument();
    });

    it('reloads the overview after the change', async () => {
      const user = userEvent.setup();
      let overview = buildSecurityOverview();
      server.use(
        http.get(apiUrl('/api/v1/me/security'), () => HttpResponse.json({ data: overview })),
      );
      recordRequest('put', '/api/v1/me/password', () => {
        overview = buildSecurityOverview({ password_changed_at: '2026-10-11T19:30:00+02:00' });
        return passwordChanged();
      });

      renderSecurityPage();

      await openPasswordForm(user);
      await fillPasswords(user);
      await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

      expect(await screen.findByText('Ultima modifica: 11 ottobre 2026')).toBeInTheDocument();
    });

    it('validates the new password before sending it', async () => {
      const user = userEvent.setup();
      const bodies = recordRequest('put', '/api/v1/me/password', () => passwordChanged());

      renderSecurityPage();

      await openPasswordForm(user);
      await user.type(screen.getByLabelText('Nuova password'), 'corta');
      await user.type(screen.getByLabelText('Conferma la nuova password'), 'diversa');
      await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

      expect(await screen.findByText('Inserisci la password attuale.')).toBeInTheDocument();
      expect(screen.getByText('Le password non coincidono.')).toBeInTheDocument();
      expect(bodies).toEqual([]);
    });

    it('asks the app code inline when two factor authentication is active', async () => {
      const user = userEvent.setup();
      serveOverview(buildTwoFactorOverview());
      const bodies = recordRequest('put', '/api/v1/me/password', () => passwordChanged());

      renderSecurityPage();

      await openPasswordForm(user);
      expect(
        screen.getByText("La doppia autenticazione è attiva: inserisci anche il codice dell'app."),
      ).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Usa un codice di recupero' })).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Usa una passkey' })).not.toBeInTheDocument();
      await fillPasswords(user);
      await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));
      expect(await screen.findByText('Inserisci il codice a 6 cifre.')).toBeInTheDocument();
      await user.type(screen.getByLabelText('Codice a 6 cifre'), '123456');
      await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

      expect(await screen.findByText('Password cambiata')).toBeInTheDocument();
      expect(bodies).toEqual([
        {
          current_password: 'Silicio2026',
          password: 'NuovaPassword2026',
          password_confirmation: 'NuovaPassword2026',
          method: 'totp',
          code: '123456',
        },
      ]);
    });

    it('accepts a passkey as second factor with the password options', async () => {
      const user = userEvent.setup();
      serveOverview(buildTwoFactorOverview({ passkeys_count: 1 }));
      const optionCalls = recordPost('/api/v1/me/password/passkey-options', () =>
        HttpResponse.json({ data: { options: { challenge: 'abc' } } }),
      );
      const bodies = recordRequest('put', '/api/v1/me/password', () => passwordChanged());
      startAuthentication.mockResolvedValue(PASSKEY_RESPONSE);

      renderSecurityPage();

      await openPasswordForm(user);
      await fillPasswords(user);
      await user.click(screen.getByRole('button', { name: 'Usa una passkey' }));
      await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

      expect(await screen.findByText('Password cambiata')).toBeInTheDocument();
      expect(optionCalls).toHaveLength(1);
      expect(startAuthentication).toHaveBeenCalledWith({ optionsJSON: { challenge: 'abc' } });
      expect(bodies[0]).toMatchObject({ method: 'passkey', credential: PASSKEY_RESPONSE });
    });

    it('shows the server errors on the fields', async () => {
      const user = userEvent.setup();
      recordRequest('put', '/api/v1/me/password', () =>
        validationError('current_password', 'La password attuale non è corretta.'),
      );

      renderSecurityPage();

      await openPasswordForm(user);
      await fillPasswords(user);
      await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

      expect(await screen.findByText('La password attuale non è corretta.')).toBeInTheDocument();
      expect(screen.getByLabelText('Password attuale')).toHaveAttribute('aria-invalid', 'true');
    });

    it('shows the retry time after too many attempts', async () => {
      const user = userEvent.setup();
      recordRequest('put', '/api/v1/me/password', () => tooManyAttempts(45));

      renderSecurityPage();

      await openPasswordForm(user);
      await fillPasswords(user);
      await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

      expect(
        await screen.findByText('Troppi tentativi. Riprova tra 45 secondi', {
          selector: 'span:not([aria-hidden])',
        }),
      ).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Salva la nuova password' })).toBeDisabled();
    });

    it('warns when few recovery codes are left', async () => {
      const user = userEvent.setup();
      serveOverview(buildTwoFactorOverview());
      recordRequest('put', '/api/v1/me/password', () =>
        passwordChanged({ recovery_codes_low: true, recovery_codes_remaining: 2 }),
      );

      renderSecurityPage();

      await openPasswordForm(user);
      await fillPasswords(user);
      await user.click(screen.getByRole('button', { name: 'Usa un codice di recupero' }));
      await user.type(screen.getByLabelText('Codice di recupero'), 'K7PM-2XQA');
      await user.click(screen.getByRole('button', { name: 'Salva la nuova password' }));

      expect(
        await screen.findByText(
          'Password cambiata. Ti restano 2 codici di recupero: generane di nuovi dalla pagina Sicurezza.',
        ),
      ).toBeInTheDocument();
    });

    it('closes the form without sending anything when cancelled', async () => {
      const user = userEvent.setup();

      renderSecurityPage();

      await openPasswordForm(user);
      await user.click(screen.getByRole('button', { name: 'Annulla' }));

      expect(screen.queryByLabelText('Password attuale')).not.toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Cambia password' })).toBeInTheDocument();
    });
  });

  describe('recent activity', () => {
    it('shows labels, devices and passkey names from the API', async () => {
      server.use(
        http.get(apiUrl('/api/v1/me/security-events'), () =>
          HttpResponse.json(
            buildSecurityEventPage([
              buildSecurityEvent(),
              buildSecurityEvent({
                type: 'passkey_added',
                label: 'Passkey aggiunta',
                device: 'Safari su macOS',
                passkey_name: 'MacBook di Giulia',
                created_at: '2026-09-20T18:02:00+02:00',
              }),
            ]),
          ),
        ),
      );

      renderSecurityPage();

      const list = await screen.findByRole('list', { name: 'Attività recente' });
      expect(within(list).getByText('Accesso effettuato')).toBeInTheDocument();
      expect(within(list).getByText('Chrome su macOS · IP 93.45.112.18')).toBeInTheDocument();
      expect(
        within(list).getByText('MacBook di Giulia · Safari su macOS · IP 93.45.112.18'),
      ).toBeInTheDocument();
      expect(within(list).getByText('20 settembre 2026, 18:02')).toBeInTheDocument();
      expect(
        screen.queryByRole('button', { name: 'Mostra altre attività' }),
      ).not.toBeInTheDocument();
    });

    it('loads more activity until the last page', async () => {
      const user = userEvent.setup();
      const requestedPages = [];
      server.use(
        http.get(apiUrl('/api/v1/me/security-events'), ({ request }) => {
          const page = Number(new URL(request.url).searchParams.get('page'));
          requestedPages.push(page);

          return HttpResponse.json(
            page === 1
              ? buildSecurityEventPage([buildSecurityEvent({ label: 'Accesso effettuato' })], {
                  current_page: 1,
                  last_page: 2,
                })
              : buildSecurityEventPage(
                  [buildSecurityEvent({ type: 'logout', label: 'Disconnessione' })],
                  { current_page: 2, last_page: 2 },
                ),
          );
        }),
      );

      renderSecurityPage();

      await user.click(await screen.findByRole('button', { name: 'Mostra altre attività' }));

      expect(await screen.findByText('Disconnessione')).toBeInTheDocument();
      expect(screen.getByText('Accesso effettuato')).toBeInTheDocument();
      expect(requestedPages).toEqual([1, 2]);
      await waitFor(() =>
        expect(
          screen.queryByRole('button', { name: 'Mostra altre attività' }),
        ).not.toBeInTheDocument(),
      );
    });

    it('shows a skeleton while the activity loads', async () => {
      server.use(
        http.get(apiUrl('/api/v1/me/security-events'), async () => {
          await new Promise((resolve) => setTimeout(resolve, 50));
          return HttpResponse.json(buildSecurityEventPage([buildSecurityEvent()]));
        }),
      );

      renderSecurityPage();

      expect(
        await screen.findByRole('status', { name: "Caricamento dell'attività recente" }),
      ).toBeInTheDocument();
      expect(await screen.findByText('Accesso effettuato')).toBeInTheDocument();
    });

    it('shows a message when there is no activity yet', async () => {
      renderSecurityPage();

      expect(
        await screen.findByText('Non ci sono ancora attività da mostrare.'),
      ).toBeInTheDocument();
    });

    it('shows an error with a retry when the activity fails', async () => {
      const user = userEvent.setup();
      let calls = 0;
      server.use(
        http.get(apiUrl('/api/v1/me/security-events'), () => {
          calls += 1;
          return calls === 1
            ? HttpResponse.json({ message: 'Errore' }, { status: 500 })
            : HttpResponse.json(buildSecurityEventPage([buildSecurityEvent()]));
        }),
      );

      renderSecurityPage();

      expect(
        await screen.findByText("Non è stato possibile caricare l'attività recente."),
      ).toBeInTheDocument();
      await user.click(screen.getByRole('button', { name: 'Riprova' }));

      expect(await screen.findByText('Accesso effettuato')).toBeInTheDocument();
    });
  });
});
