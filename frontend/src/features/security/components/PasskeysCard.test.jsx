import { browserSupportsWebAuthn, startRegistration } from '@simplewebauthn/browser';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { recordPost, validationError } from '@/test/authHelpers';
import { buildPasskey, buildSecurityOverview } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import {
  cancelIdentityConfirmation,
  confirmIdentityWith,
  protectedEndpoint,
  recordRequest,
  renderSecurityPage,
  serveOverview,
  servePasskeys,
} from '@/test/security';

vi.mock('@simplewebauthn/browser', () => ({
  browserSupportsWebAuthn: vi.fn(() => true),
  startAuthentication: vi.fn(),
  startRegistration: vi.fn(),
}));

const REGISTRATION_RESPONSE = {
  id: 'cred-2',
  rawId: 'cred-2',
  type: 'public-key',
  response: { clientDataJSON: 'a', attestationObject: 'b', transports: ['internal'] },
  clientExtensionResults: {},
};

const OPTIONS = { challenge: 'xyz', rp: { id: 'localhost' } };

function passkeysCard() {
  return screen.getByRole('region', { name: 'Passkey' });
}

async function openAddDialog(user, name = 'iPhone di Giulia') {
  await user.click(await screen.findByRole('button', { name: 'Aggiungi una passkey' }));
  const dialog = screen.getByRole('dialog', { name: 'Aggiungi una passkey' });
  await user.type(within(dialog).getByLabelText('Nome della passkey'), name);

  return dialog;
}

describe('PasskeysCard', () => {
  beforeEach(() => {
    browserSupportsWebAuthn.mockReturnValue(true);
    startRegistration.mockReset();
  });

  it('lists the passkeys with creation and last use', async () => {
    servePasskeys([
      buildPasskey(),
      buildPasskey({
        name: 'iPhone di Giulia',
        created_at: '2026-09-12T21:44:00+02:00',
        last_used_at: null,
      }),
    ]);

    renderSecurityPage();

    const list = await screen.findByRole('list', { name: 'Le tue passkey' });
    expect(within(list).getByText('MacBook di Giulia')).toBeInTheDocument();
    expect(
      within(list).getByText('Creata il 20 settembre 2026 · Ultimo utilizzo 29 settembre 2026'),
    ).toBeInTheDocument();
    expect(within(list).getByText('Creata il 12 settembre 2026 · Mai usata')).toBeInTheDocument();
    expect(within(list).getByRole('button', { name: 'Rinomina iPhone di Giulia' })).toBeEnabled();
  });

  it('shows the empty state and the advice', async () => {
    renderSecurityPage();

    expect(await screen.findByText('Non hai ancora aggiunto passkey.')).toBeInTheDocument();
    expect(
      screen.getByText(
        'Ti consigliamo di registrarne almeno due, su dispositivi diversi: se ne perdi uno potrai comunque accedere.',
      ),
    ).toBeInTheDocument();
  });

  it('hides the add button when the browser does not support passkeys', async () => {
    browserSupportsWebAuthn.mockReturnValue(false);

    renderSecurityPage();

    expect(await screen.findByText(/Questo browser non supporta le passkey/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Aggiungi una passkey' })).not.toBeInTheDocument();
  });

  it('shows an error with a retry when the passkeys fail to load', async () => {
    const user = userEvent.setup();
    let calls = 0;
    server.use(
      http.get(apiUrl('/api/v1/me/passkeys'), () => {
        calls += 1;
        return calls === 1
          ? HttpResponse.json({ message: 'Errore' }, { status: 500 })
          : HttpResponse.json({ data: [buildPasskey()] });
      }),
    );

    renderSecurityPage();

    expect(
      await screen.findByText('Non è stato possibile caricare le passkey.'),
    ).toBeInTheDocument();
    await user.click(within(passkeysCard()).getByRole('button', { name: 'Riprova' }));

    expect(await screen.findByText('MacBook di Giulia')).toBeInTheDocument();
  });

  it('adds a passkey after the identity confirmation', async () => {
    const user = userEvent.setup();
    let passkeys = [];
    server.use(
      http.get(apiUrl('/api/v1/me/passkeys'), () => HttpResponse.json({ data: passkeys })),
    );
    const optionCalls = protectedEndpoint('post', '/api/v1/me/passkeys/options', () =>
      HttpResponse.json({ data: { options: OPTIONS } }),
    );
    const stored = recordPost('/api/v1/me/passkeys', () => {
      passkeys = [buildPasskey({ name: 'iPhone di Giulia', last_used_at: null })];
      return HttpResponse.json({ data: passkeys[0] }, { status: 201 });
    });
    startRegistration.mockResolvedValue(REGISTRATION_RESPONSE);

    renderSecurityPage();

    const dialog = await openAddDialog(user);
    await user.click(within(dialog).getByRole('button', { name: 'Continua' }));
    await confirmIdentityWith(user, 'Per aggiungere una passkey, conferma che sei tu.');

    expect(await screen.findByText('Passkey aggiunta')).toBeInTheDocument();
    expect(await screen.findByText('iPhone di Giulia')).toBeInTheDocument();
    expect(optionCalls).toHaveLength(2);
    expect(startRegistration).toHaveBeenCalledWith({ optionsJSON: OPTIONS });
    expect(stored).toEqual([{ name: 'iPhone di Giulia', credential: REGISTRATION_RESPONSE }]);
    expect(screen.queryByRole('dialog', { name: 'Aggiungi una passkey' })).not.toBeInTheDocument();
  });

  it('shows a skeleton while the passkeys load', async () => {
    server.use(
      http.get(apiUrl('/api/v1/me/passkeys'), async () => {
        await new Promise((resolve) => setTimeout(resolve, 50));
        return HttpResponse.json({ data: [buildPasskey()] });
      }),
    );

    renderSecurityPage();

    expect(
      await screen.findByRole('status', { name: 'Caricamento delle passkey' }),
    ).toBeInTheDocument();
    expect(await screen.findByText('MacBook di Giulia')).toBeInTheDocument();
  });

  it('keeps the add dialog open without errors when the identity confirmation is cancelled', async () => {
    const user = userEvent.setup();
    protectedEndpoint('post', '/api/v1/me/passkeys/options', () =>
      HttpResponse.json({ data: { options: OPTIONS } }),
    );

    renderSecurityPage();

    const dialog = await openAddDialog(user);
    await user.click(within(dialog).getByRole('button', { name: 'Continua' }));
    await cancelIdentityConfirmation(user);

    await waitFor(() =>
      expect(
        screen.queryByRole('dialog', { name: 'Conferma la tua identità' }),
      ).not.toBeInTheDocument(),
    );
    expect(screen.getByRole('dialog', { name: 'Aggiungi una passkey' })).toBeInTheDocument();
    expect(within(dialog).queryByRole('alert')).not.toBeInTheDocument();
    expect(startRegistration).not.toHaveBeenCalled();
  });

  it('requires a name before starting the registration', async () => {
    const user = userEvent.setup();
    const optionCalls = recordPost('/api/v1/me/passkeys/options', () =>
      HttpResponse.json({ data: { options: OPTIONS } }),
    );

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Aggiungi una passkey' }));
    await user.click(screen.getByRole('button', { name: 'Continua' }));

    expect(await screen.findByText('Dai un nome alla passkey.')).toBeInTheDocument();
    expect(optionCalls).toEqual([]);
  });

  it('keeps the dialog open without errors when the browser registration is cancelled', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/me/passkeys/options', () =>
      HttpResponse.json({ data: { options: OPTIONS } }),
    );
    const stored = recordPost('/api/v1/me/passkeys', () =>
      HttpResponse.json({ data: buildPasskey() }, { status: 201 }),
    );
    startRegistration.mockRejectedValue(
      Object.assign(new Error('cancel'), { name: 'NotAllowedError' }),
    );

    renderSecurityPage();

    const dialog = await openAddDialog(user);
    await user.click(within(dialog).getByRole('button', { name: 'Continua' }));

    await waitFor(() => expect(startRegistration).toHaveBeenCalledTimes(1));
    expect(within(dialog).queryByRole('alert')).not.toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Continua' })).toBeEnabled();
    expect(stored).toEqual([]);
  });

  it('shows the registration error in the dialog', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/me/passkeys/options', () =>
      HttpResponse.json({ data: { options: OPTIONS } }),
    );
    recordPost('/api/v1/me/passkeys', () =>
      validationError('credential', 'Non è stato possibile registrare la passkey: riprova.'),
    );
    startRegistration.mockResolvedValue(REGISTRATION_RESPONSE);

    renderSecurityPage();

    const dialog = await openAddDialog(user);
    await user.click(within(dialog).getByRole('button', { name: 'Continua' }));

    expect(
      await within(dialog).findByText('Non è stato possibile registrare la passkey: riprova.'),
    ).toBeInTheDocument();
  });

  it('renames a passkey', async () => {
    const user = userEvent.setup();
    const passkey = buildPasskey();
    servePasskeys([passkey]);
    const bodies = recordRequest('patch', `/api/v1/me/passkeys/${passkey.id}`, () =>
      HttpResponse.json({ data: { ...passkey, name: 'MacBook Pro di Giulia' } }),
    );

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Rinomina MacBook di Giulia' }));
    const dialog = screen.getByRole('dialog', { name: 'Rinomina la passkey' });
    const field = within(dialog).getByLabelText('Nome della passkey');
    expect(field).toHaveValue('MacBook di Giulia');
    await user.clear(field);
    await user.type(field, 'MacBook Pro di Giulia');
    await user.click(within(dialog).getByRole('button', { name: 'Salva' }));

    expect(await screen.findByText('Passkey rinominata')).toBeInTheDocument();
    expect(bodies).toEqual([{ name: 'MacBook Pro di Giulia' }]);
  });

  it('shows the name error from the server when renaming', async () => {
    const user = userEvent.setup();
    const passkey = buildPasskey();
    servePasskeys([passkey]);
    recordRequest('patch', `/api/v1/me/passkeys/${passkey.id}`, () =>
      validationError('name', 'Il nome può avere al massimo 255 caratteri.'),
    );

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Rinomina MacBook di Giulia' }));
    const dialog = screen.getByRole('dialog', { name: 'Rinomina la passkey' });
    await user.click(within(dialog).getByRole('button', { name: 'Salva' }));

    expect(
      await within(dialog).findByText('Il nome può avere al massimo 255 caratteri.'),
    ).toBeInTheDocument();
    expect(within(dialog).getByLabelText('Nome della passkey')).toHaveAttribute(
      'aria-invalid',
      'true',
    );
  });

  it('keeps the removal dialog open without errors when the identity confirmation is cancelled', async () => {
    const user = userEvent.setup();
    const passkey = buildPasskey();
    servePasskeys([passkey]);
    protectedEndpoint(
      'delete',
      `/api/v1/me/passkeys/${passkey.id}`,
      () => new HttpResponse(null, { status: 204 }),
    );

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Rimuovi MacBook di Giulia' }));
    await user.click(screen.getByRole('button', { name: 'Rimuovi la passkey' }));
    await cancelIdentityConfirmation(user);

    const dialog = await screen.findByRole('dialog', {
      name: 'Vuoi rimuovere la passkey «MacBook di Giulia»?',
    });
    await waitFor(() =>
      expect(
        screen.queryByRole('dialog', { name: 'Conferma la tua identità' }),
      ).not.toBeInTheDocument(),
    );
    expect(within(dialog).queryByRole('alert')).not.toBeInTheDocument();
  });

  it('shows a removal error inside the dialog', async () => {
    const user = userEvent.setup();
    const passkey = buildPasskey();
    servePasskeys([passkey]);
    recordRequest('delete', `/api/v1/me/passkeys/${passkey.id}`, () =>
      HttpResponse.json({ message: 'Errore' }, { status: 500 }),
    );

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Rimuovi MacBook di Giulia' }));
    const dialog = screen.getByRole('dialog', {
      name: 'Vuoi rimuovere la passkey «MacBook di Giulia»?',
    });
    await user.click(within(dialog).getByRole('button', { name: 'Rimuovi la passkey' }));

    expect(
      await within(dialog).findByText('Si è verificato un errore imprevisto. Riprova più tardi.'),
    ).toBeInTheDocument();
  });

  it('removes a passkey after the confirmations', async () => {
    const user = userEvent.setup();
    const passkey = buildPasskey();
    let passkeys = [passkey];
    server.use(
      http.get(apiUrl('/api/v1/me/passkeys'), () => HttpResponse.json({ data: passkeys })),
    );
    const calls = protectedEndpoint('delete', `/api/v1/me/passkeys/${passkey.id}`, () => {
      passkeys = [];
      return new HttpResponse(null, { status: 204 });
    });

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Rimuovi MacBook di Giulia' }));
    const dialog = screen.getByRole('dialog', {
      name: 'Vuoi rimuovere la passkey «MacBook di Giulia»?',
    });
    expect(dialog).toHaveTextContent('Non potrai più usarla per accedere.');
    await user.click(within(dialog).getByRole('button', { name: 'Rimuovi la passkey' }));
    await confirmIdentityWith(
      user,
      'Per rimuovere la passkey «MacBook di Giulia», conferma che sei tu.',
    );

    expect(await screen.findByText('Passkey rimossa')).toBeInTheDocument();
    expect(await screen.findByText('Non hai ancora aggiunto passkey.')).toBeInTheDocument();
    expect(calls).toHaveLength(2);
  });

  it('keeps the passkey when the removal is not confirmed', async () => {
    const user = userEvent.setup();
    const passkey = buildPasskey();
    servePasskeys([passkey]);
    const calls = recordRequest('delete', `/api/v1/me/passkeys/${passkey.id}`, () => {
      return new HttpResponse(null, { status: 204 });
    });

    renderSecurityPage();

    await user.click(await screen.findByRole('button', { name: 'Rimuovi MacBook di Giulia' }));
    await user.click(screen.getByRole('button', { name: 'Mantieni la passkey' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(calls).toEqual([]);
    expect(screen.getByText('MacBook di Giulia')).toBeInTheDocument();
  });

  it('mentions the second factor only with two factor authentication active', async () => {
    serveOverview(buildSecurityOverview({ two_factor_enabled: false }));

    renderSecurityPage();

    expect(await screen.findByText(/senza digitare la password\.$/)).toBeInTheDocument();
  });
});
