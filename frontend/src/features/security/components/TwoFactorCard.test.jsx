import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { domainError, recordPost, tooManyAttempts, validationError } from '@/test/authHelpers';
import { buildSecurityOverview, buildTwoFactorOverview } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import {
  cancelIdentityConfirmation,
  confirmIdentityWith,
  protectedEndpoint,
  recordRequest,
  serveOverview,
  renderSecurityPage,
} from '@/test/security';

const SETUP = {
  secret: 'JBSWY3DPEHPK3PXP',
  otpauth_url: 'otpauth://totp/Casa%20del%20Silicio:giulia?secret=JBSWY3DPEHPK3PXP',
  qr_code_svg: '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
};

const CODES = [
  '7KQ4-M2XP',
  'R9TD-3HWA',
  'C5NB-8JZE',
  'V2LF-6YUQ',
  'H8GS-4KMR',
  'P3XW-9TCD',
  'E6JA-2NVB',
  'Z4UH-7RLK',
];

function serveChangingOverview(initial) {
  let current = initial;
  server.use(http.get(apiUrl('/api/v1/me/security'), () => HttpResponse.json({ data: current })));

  return (next) => {
    current = next;
  };
}

async function startActivation(user) {
  recordPost('/api/v1/me/two-factor', () => HttpResponse.json({ data: SETUP }));
  await user.click(await screen.findByRole('button', { name: 'Attiva' }));
  await screen.findByRole('img', { name: "Codice QR da inquadrare con l'app di autenticazione" });
}

describe('TwoFactorCard', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('activates two factor authentication and shows the recovery codes once', async () => {
    const user = userEvent.setup();
    const setOverview = serveChangingOverview(buildSecurityOverview());
    const confirmations = recordPost('/api/v1/me/two-factor/confirm', () => {
      setOverview(buildTwoFactorOverview({ recovery_codes_remaining: 8 }));
      return HttpResponse.json({ data: { recovery_codes: CODES } });
    });

    renderSecurityPage();

    await startActivation(user);
    expect(screen.getByRole('img', { name: /Codice QR/ })).toHaveAttribute(
      'src',
      expect.stringMatching(/^data:image\/svg\+xml/),
    );
    expect(screen.getByText('JBSW Y3DP EHPK 3PXP')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Continua' }));
    await user.type(screen.getByLabelText('Codice a 6 cifre'), '123456');
    await user.click(screen.getByRole('button', { name: 'Verifica' }));

    const codes = await screen.findByRole('list', { name: 'Codici di recupero' });
    expect(within(codes).getAllByRole('listitem')).toHaveLength(8);
    expect(within(codes).getByText('7KQ4-M2XP')).toBeInTheDocument();
    expect(confirmations).toEqual([{ code: '123456' }]);

    await user.click(screen.getByRole('button', { name: 'Ho salvato i codici' }));

    expect(await screen.findByText('Codici di recupero rimasti: 8 di 8')).toBeInTheDocument();
    expect(screen.queryByText('7KQ4-M2XP')).not.toBeInTheDocument();
    expect(screen.getByText('Doppia autenticazione attivata')).toBeInTheDocument();
  });

  it('asks the identity confirmation before showing the QR code', async () => {
    const user = userEvent.setup();
    const calls = protectedEndpoint('post', '/api/v1/me/two-factor', () =>
      HttpResponse.json({ data: SETUP }),
    );

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Attiva' }));
    await confirmIdentityWith(user, 'Per attivare la doppia autenticazione, conferma che sei tu.');

    expect(
      await screen.findByRole('img', {
        name: "Codice QR da inquadrare con l'app di autenticazione",
      }),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Copia la chiave' })).toBeInTheDocument();
    expect(calls).toHaveLength(2);
  });

  it('keeps the secret and the recovery codes out of the mutation cache', async () => {
    const user = userEvent.setup();
    serveChangingOverview(buildSecurityOverview());
    recordPost('/api/v1/me/two-factor/confirm', () =>
      HttpResponse.json({ data: { recovery_codes: CODES } }),
    );

    const { queryClient } = renderSecurityPage();

    await startActivation(user);
    await user.click(screen.getByRole('button', { name: 'Continua' }));
    await user.type(screen.getByLabelText('Codice a 6 cifre'), '123456');
    await user.click(screen.getByRole('button', { name: 'Verifica' }));
    await screen.findByRole('list', { name: 'Codici di recupero' });

    const cached = JSON.stringify(
      queryClient
        .getMutationCache()
        .getAll()
        .map((mutation) => mutation.state),
    );
    expect(cached).not.toContain('JBSWY3DPEHPK3PXP');
    expect(cached).not.toContain('7KQ4-M2XP');
  });

  it('keeps the regenerated codes out of the mutation cache', async () => {
    const user = userEvent.setup();
    serveOverview(buildTwoFactorOverview());
    recordPost('/api/v1/me/two-factor/recovery-codes', () =>
      HttpResponse.json({ data: { recovery_codes: CODES } }),
    );

    const { queryClient } = renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Genera nuovi codici' }));
    await user.click(
      within(screen.getByRole('dialog')).getByRole('button', { name: 'Genera nuovi codici' }),
    );
    await screen.findByRole('list', { name: 'Codici di recupero' });

    const cached = JSON.stringify(
      queryClient
        .getMutationCache()
        .getAll()
        .map((mutation) => mutation.state),
    );
    expect(cached).not.toContain('7KQ4-M2XP');
  });

  it('shows a wrong activation code on the field', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/me/two-factor/confirm', () =>
      validationError('code', 'Codice non valido.'),
    );

    renderSecurityPage();

    await startActivation(user);
    await user.click(screen.getByRole('button', { name: 'Continua' }));
    await user.type(screen.getByLabelText('Codice a 6 cifre'), '111111');
    await user.click(screen.getByRole('button', { name: 'Verifica' }));

    expect(await screen.findByText('Codice non valido.')).toBeInTheDocument();
    expect(screen.getByLabelText('Codice a 6 cifre')).toHaveAttribute('aria-invalid', 'true');
    expect(screen.queryByRole('list', { name: 'Codici di recupero' })).not.toBeInTheDocument();
  });

  it('copies and downloads the recovery codes', async () => {
    const user = userEvent.setup();
    const writeText = vi.spyOn(navigator.clipboard, 'writeText');
    const createObjectURL = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:codes');
    const revokeObjectURL = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
    serveOverview(buildTwoFactorOverview());
    protectedEndpoint('post', '/api/v1/me/two-factor/recovery-codes', () =>
      HttpResponse.json({ data: { recovery_codes: CODES } }),
    );

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Genera nuovi codici' }));
    await user.click(
      within(screen.getByRole('dialog')).getByRole('button', { name: 'Genera nuovi codici' }),
    );
    await confirmIdentityWith(
      user,
      'Per generare nuovi codici di recupero, conferma che sei tu. I codici che hai ora smetteranno di funzionare.',
      { code: '123456' },
    );
    await screen.findByRole('list', { name: 'Codici di recupero' });

    await user.click(screen.getByRole('button', { name: 'Copia' }));
    expect(writeText).toHaveBeenCalledWith(CODES.join('\n'));
    expect(await screen.findByText('Codici copiati')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Scarica' }));
    expect(createObjectURL).toHaveBeenCalledTimes(1);
    expect(await createObjectURL.mock.calls[0][0].text()).toBe(`${CODES.join('\n')}\n`);
    expect(click.mock.contexts[0].download).toBe('casa-del-silicio-codici-di-recupero.txt');
    await waitFor(() => expect(revokeObjectURL).toHaveBeenCalledWith('blob:codes'));
  });

  it('validates the code format before sending it', async () => {
    const user = userEvent.setup();
    const confirmations = recordPost('/api/v1/me/two-factor/confirm', () =>
      HttpResponse.json({ data: { recovery_codes: CODES } }),
    );

    renderSecurityPage();

    await startActivation(user);
    await user.click(screen.getByRole('button', { name: 'Continua' }));
    await user.type(screen.getByLabelText('Codice a 6 cifre'), '12345');
    await user.click(screen.getByRole('button', { name: 'Verifica' }));

    expect(await screen.findByText('Inserisci il codice a 6 cifre.')).toBeInTheDocument();
    expect(confirmations).toEqual([]);
  });

  it('goes back to the scan step and cancels the activation', async () => {
    const user = userEvent.setup();

    renderSecurityPage();

    await startActivation(user);
    await user.click(screen.getByRole('button', { name: 'Continua' }));
    await user.click(screen.getByRole('button', { name: 'Indietro' }));
    expect(screen.getByText('JBSW Y3DP EHPK 3PXP')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Annulla' }));

    expect(await screen.findByRole('button', { name: 'Attiva' })).toBeInTheDocument();
  });

  it('restarts when the setup is missing', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/me/two-factor/confirm', () =>
      domainError(
        409,
        'two_factor_setup_missing',
        "Avvia di nuovo l'attivazione della doppia autenticazione.",
      ),
    );

    renderSecurityPage();

    await startActivation(user);
    await user.click(screen.getByRole('button', { name: 'Continua' }));
    await user.type(screen.getByLabelText('Codice a 6 cifre'), '123456');
    await user.click(screen.getByRole('button', { name: 'Verifica' }));

    expect(
      await screen.findByText("Avvia di nuovo l'attivazione della doppia autenticazione."),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Attiva' })).toBeInTheDocument();
  });

  it('shows the retry time after too many codes', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/me/two-factor/confirm', () => tooManyAttempts(30));

    renderSecurityPage();

    await startActivation(user);
    await user.click(screen.getByRole('button', { name: 'Continua' }));
    await user.type(screen.getByLabelText('Codice a 6 cifre'), '123456');
    await user.click(screen.getByRole('button', { name: 'Verifica' }));

    expect(
      await screen.findByText('Troppi tentativi. Riprova tra 30 secondi', {
        selector: 'span:not([aria-hidden])',
      }),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Verifica' })).toBeDisabled();
  });

  it('does not start the activation when the identity confirmation is cancelled', async () => {
    const user = userEvent.setup();
    const calls = protectedEndpoint('post', '/api/v1/me/two-factor', () =>
      HttpResponse.json({ data: SETUP }),
    );

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Attiva' }));
    await cancelIdentityConfirmation(user);

    await waitFor(() =>
      expect(
        screen.queryByRole('dialog', { name: 'Conferma la tua identità' }),
      ).not.toBeInTheDocument(),
    );
    expect(screen.getByRole('button', { name: 'Attiva' })).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(calls).toHaveLength(1);
  });

  it('keeps the current codes when the regeneration is not confirmed', async () => {
    const user = userEvent.setup();
    serveOverview(buildTwoFactorOverview());
    const calls = recordPost('/api/v1/me/two-factor/recovery-codes', () =>
      HttpResponse.json({ data: { recovery_codes: CODES } }),
    );

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Genera nuovi codici' }));
    const dialog = screen.getByRole('dialog', { name: 'Vuoi generare nuovi codici di recupero?' });
    expect(dialog).toHaveTextContent('I codici che hai ora smetteranno di funzionare.');
    await user.click(within(dialog).getByRole('button', { name: 'Mantieni i codici attuali' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(calls).toEqual([]);
  });

  it('disables two factor authentication after the confirmations', async () => {
    const user = userEvent.setup();
    const setOverview = serveChangingOverview(buildTwoFactorOverview());
    const calls = protectedEndpoint('delete', '/api/v1/me/two-factor', () => {
      setOverview(buildSecurityOverview());
      return new HttpResponse(null, { status: 204 });
    });

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Disattiva' }));
    const dialog = screen.getByRole('dialog', {
      name: 'Vuoi disattivare la doppia autenticazione?',
    });
    await user.click(
      within(dialog).getByRole('button', { name: 'Disattiva la doppia autenticazione' }),
    );
    await confirmIdentityWith(
      user,
      'Per disattivare la doppia autenticazione, conferma che sei tu.',
      {
        code: '123456',
      },
    );

    expect(await screen.findByText('Doppia autenticazione disattivata')).toBeInTheDocument();
    expect(await screen.findByRole('button', { name: 'Attiva' })).toBeInTheDocument();
    expect(calls).toHaveLength(2);
  });

  it('keeps the regeneration dialog open when the identity confirmation is cancelled', async () => {
    const user = userEvent.setup();
    serveOverview(buildTwoFactorOverview());
    protectedEndpoint('post', '/api/v1/me/two-factor/recovery-codes', () =>
      HttpResponse.json({ data: { recovery_codes: CODES } }),
    );

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Genera nuovi codici' }));
    await user.click(
      within(screen.getByRole('dialog')).getByRole('button', { name: 'Genera nuovi codici' }),
    );
    await cancelIdentityConfirmation(user);

    const dialog = await screen.findByRole('dialog', {
      name: 'Vuoi generare nuovi codici di recupero?',
    });
    await waitFor(() =>
      expect(
        screen.queryByRole('dialog', { name: 'Conferma la tua identità' }),
      ).not.toBeInTheDocument(),
    );
    expect(within(dialog).queryByRole('alert')).not.toBeInTheDocument();
    expect(screen.queryByRole('list', { name: 'Codici di recupero' })).not.toBeInTheDocument();
  });

  it('shows a state conflict in the dialog and reloads the overview', async () => {
    const user = userEvent.setup();
    const setOverview = serveChangingOverview(buildTwoFactorOverview());
    recordRequest('delete', '/api/v1/me/two-factor', () => {
      setOverview(buildSecurityOverview());
      return domainError(409, 'two_factor_not_enabled', 'La doppia autenticazione non è attiva.');
    });

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Disattiva' }));
    const dialog = screen.getByRole('dialog', {
      name: 'Vuoi disattivare la doppia autenticazione?',
    });
    await user.click(
      within(dialog).getByRole('button', { name: 'Disattiva la doppia autenticazione' }),
    );

    expect(
      await within(dialog).findByText('La doppia autenticazione non è attiva.'),
    ).toBeInTheDocument();
    await user.click(
      within(dialog).getByRole('button', { name: 'Mantieni la doppia autenticazione' }),
    );
    expect(await screen.findByRole('button', { name: 'Attiva' })).toBeInTheDocument();
  });

  it('keeps the disable dialog open when the identity confirmation is cancelled', async () => {
    const user = userEvent.setup();
    serveOverview(buildTwoFactorOverview());
    protectedEndpoint(
      'delete',
      '/api/v1/me/two-factor',
      () => new HttpResponse(null, { status: 204 }),
    );

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Disattiva' }));
    await user.click(screen.getByRole('button', { name: 'Disattiva la doppia autenticazione' }));
    await cancelIdentityConfirmation(user);

    const dialog = await screen.findByRole('dialog', {
      name: 'Vuoi disattivare la doppia autenticazione?',
    });
    await waitFor(() =>
      expect(
        screen.queryByRole('dialog', { name: 'Conferma la tua identità' }),
      ).not.toBeInTheDocument(),
    );
    expect(within(dialog).queryByRole('alert')).not.toBeInTheDocument();
  });
});
