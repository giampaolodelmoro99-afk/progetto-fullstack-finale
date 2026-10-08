import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { browserSupportsWebAuthn, startAuthentication } from '@simplewebauthn/browser';
import { httpClient } from '@/api/httpClient';
import { useIdentityConfirmation } from '@/features/security/hooks/useIdentityConfirmation';
import { buildSecurityOverview, buildTwoFactorOverview, buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { renderWithProviders } from '@/test/renderWithProviders';

vi.mock('@simplewebauthn/browser', () => ({
  browserSupportsWebAuthn: vi.fn(() => true),
  startAuthentication: vi.fn(),
}));

const PASSKEY_RESPONSE = {
  id: 'cred-1',
  rawId: 'cred-1',
  type: 'public-key',
  response: { clientDataJSON: 'a', authenticatorData: 'b', signature: 'c', userHandle: 'd' },
  clientExtensionResults: {},
};

function ConfirmButton({ prompt, onResult }) {
  const { confirmIdentity } = useIdentityConfirmation();

  return (
    <button
      type="button"
      onClick={() =>
        confirmIdentity({ prompt }).then(
          () => onResult('confirmed'),
          (error) => onResult(error),
        )
      }
    >
      Apri
    </button>
  );
}

function useOverview(overview) {
  server.use(http.get(apiUrl('/api/v1/me/security'), () => HttpResponse.json({ data: overview })));
}

function recordConfirmation(response = () => HttpResponse.json({ data: {} })) {
  const bodies = [];
  server.use(
    http.post(apiUrl('/api/v1/me/confirm-identity'), async ({ request }) => {
      bodies.push(await request.json());
      return response();
    }),
  );

  return bodies;
}

async function openDialog(user, { prompt, onResult = vi.fn() } = {}) {
  renderWithProviders(<ConfirmButton prompt={prompt} onResult={onResult} />, {
    user: buildUser(),
  });
  await user.click(screen.getByRole('button', { name: 'Apri' }));

  return screen.findByRole('dialog', { name: 'Conferma la tua identità' });
}

describe('IdentityConfirmationProvider', () => {
  beforeEach(() => {
    browserSupportsWebAuthn.mockReturnValue(true);
    startAuthentication.mockReset();
  });

  it('confirms with the password alone when two factor authentication is off', async () => {
    const user = userEvent.setup();
    const onResult = vi.fn();
    useOverview(buildSecurityOverview());
    const bodies = recordConfirmation();

    const dialog = await openDialog(user, {
      prompt: "Prima di cambiare l'email, conferma che sei tu.",
      onResult,
    });

    expect(dialog).toHaveTextContent("Prima di cambiare l'email, conferma che sei tu.");
    await user.type(await within(dialog).findByLabelText('Password'), 'Silicio2026');
    expect(within(dialog).queryByText('Secondo fattore')).not.toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Conferma' }));

    await waitFor(() => expect(onResult).toHaveBeenCalledWith('confirmed'));
    expect(bodies).toEqual([{ password: 'Silicio2026' }]);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('uses a generic description when the request has none', async () => {
    const user = userEvent.setup();
    useOverview(buildSecurityOverview());

    const dialog = await openDialog(user);

    expect(dialog).toHaveTextContent('Per continuare, conferma che sei tu.');
  });

  it('asks for the password before sending anything', async () => {
    const user = userEvent.setup();
    useOverview(buildSecurityOverview());
    const bodies = recordConfirmation();

    const dialog = await openDialog(user);
    await within(dialog).findByLabelText('Password');
    await user.click(within(dialog).getByRole('button', { name: 'Conferma' }));

    expect(await within(dialog).findByText('Inserisci la password.')).toBeInTheDocument();
    expect(bodies).toEqual([]);
  });

  it('rejects the confirmation when the user cancels', async () => {
    const user = userEvent.setup();
    const onResult = vi.fn();
    useOverview(buildSecurityOverview());

    const dialog = await openDialog(user, { onResult });
    await user.click(within(dialog).getByRole('button', { name: 'Annulla' }));

    await waitFor(() =>
      expect(onResult).toHaveBeenCalledWith(
        expect.objectContaining({ code: 'identity_confirmation_cancelled' }),
      ),
    );
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('asks for the second factor with the recovery codes left', async () => {
    const user = userEvent.setup();
    useOverview(buildTwoFactorOverview({ recovery_codes_remaining: 6, passkeys_count: 0 }));
    const bodies = recordConfirmation();

    const dialog = await openDialog(user);
    await user.type(await within(dialog).findByLabelText('Password'), 'Silicio2026');

    expect(within(dialog).getByRole('button', { name: 'App' })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    expect(within(dialog).queryByRole('button', { name: 'Passkey' })).not.toBeInTheDocument();
    expect(within(dialog).getByText("Lo trovi nell'app di autenticazione.")).toBeInTheDocument();

    await user.click(within(dialog).getByRole('button', { name: 'Codice di recupero' }));
    expect(
      within(dialog).getByText('Ogni codice si può usare una sola volta. Te ne restano 6.'),
    ).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Conferma' }));
    expect(await within(dialog).findByText('Inserisci un codice di recupero.')).toBeInTheDocument();

    await user.type(within(dialog).getByLabelText('Codice di recupero'), 'K7PM-2XQA');
    await user.click(within(dialog).getByRole('button', { name: 'Conferma' }));

    await waitFor(() =>
      expect(bodies).toEqual([
        { password: 'Silicio2026', method: 'recovery_code', code: 'K7PM-2XQA' },
      ]),
    );
  });

  it('shows a wrong code on the field and keeps the dialog open', async () => {
    const user = userEvent.setup();
    useOverview(buildTwoFactorOverview());
    recordConfirmation(() =>
      HttpResponse.json(
        { message: 'Codice non valido.', errors: { code: ['Codice non valido.'] } },
        { status: 422 },
      ),
    );

    const dialog = await openDialog(user);
    await user.type(await within(dialog).findByLabelText('Password'), 'Silicio2026');
    await user.type(within(dialog).getByLabelText('Codice a 6 cifre'), '000000');
    await user.click(within(dialog).getByRole('button', { name: 'Conferma' }));

    expect(await within(dialog).findByText('Codice non valido.')).toBeInTheDocument();
    expect(within(dialog).getByLabelText('Codice a 6 cifre')).toHaveAttribute(
      'aria-invalid',
      'true',
    );
  });

  it('confirms with a passkey when the user has one', async () => {
    const user = userEvent.setup();
    useOverview(buildTwoFactorOverview({ passkeys_count: 2 }));
    server.use(
      http.post(apiUrl('/api/v1/me/confirm-identity/passkey-options'), () =>
        HttpResponse.json({ data: { options: { challenge: 'abc' } } }),
      ),
    );
    startAuthentication.mockResolvedValue(PASSKEY_RESPONSE);
    const bodies = recordConfirmation();
    const onResult = vi.fn();

    const dialog = await openDialog(user, { onResult });
    await user.type(await within(dialog).findByLabelText('Password'), 'Silicio2026');
    await user.click(within(dialog).getByRole('button', { name: 'Passkey' }));
    expect(
      within(dialog).getByText("Il dispositivo ti chiederà l'impronta, il volto o il PIN."),
    ).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Usa la passkey' }));

    await waitFor(() => expect(onResult).toHaveBeenCalledWith('confirmed'));
    expect(startAuthentication).toHaveBeenCalledWith({ optionsJSON: { challenge: 'abc' } });
    expect(bodies).toEqual([
      { password: 'Silicio2026', method: 'passkey', credential: PASSKEY_RESPONSE },
    ]);
  });

  it('opens the dialog after a 423 and repeats the request once confirmed', async () => {
    const user = userEvent.setup();
    useOverview(buildSecurityOverview());
    recordConfirmation();
    let attempts = 0;
    server.use(
      http.delete(apiUrl('/api/v1/me/two-factor'), () => {
        attempts += 1;
        return attempts === 1
          ? HttpResponse.json({ message: 'Conferma.', requires: ['password'] }, { status: 423 })
          : new HttpResponse(null, { status: 204 });
      }),
    );
    renderWithProviders(<p>Sicurezza</p>, { user: buildUser() });

    const request = httpClient.delete('/api/v1/me/two-factor', {
      identityPrompt: 'Per disattivare la doppia autenticazione, conferma che sei tu.',
    });
    const dialog = await screen.findByRole('dialog', { name: 'Conferma la tua identità' });

    expect(dialog).toHaveTextContent(
      'Per disattivare la doppia autenticazione, conferma che sei tu.',
    );
    await user.type(await within(dialog).findByLabelText('Password'), 'Silicio2026');
    await user.click(within(dialog).getByRole('button', { name: 'Conferma' }));

    await expect(request).resolves.toMatchObject({ status: 204 });
    expect(attempts).toBe(2);
  });

  it('shares one dialog between requests locked at the same time', async () => {
    const user = userEvent.setup();
    useOverview(buildSecurityOverview());
    recordConfirmation();
    const attempts = { first: 0, second: 0 };
    server.use(
      http.post(apiUrl('/api/v1/me/passkeys/options'), () => {
        attempts.first += 1;
        return attempts.first === 1
          ? HttpResponse.json({ message: 'Conferma.' }, { status: 423 })
          : HttpResponse.json({ data: {} });
      }),
      http.post(apiUrl('/api/v1/me/two-factor'), () => {
        attempts.second += 1;
        return attempts.second === 1
          ? HttpResponse.json({ message: 'Conferma.' }, { status: 423 })
          : HttpResponse.json({ data: {} });
      }),
    );
    renderWithProviders(<p>Sicurezza</p>, { user: buildUser() });

    const requests = Promise.all([
      httpClient.post('/api/v1/me/passkeys/options'),
      httpClient.post('/api/v1/me/two-factor'),
    ]);
    const dialog = await screen.findByRole('dialog', { name: 'Conferma la tua identità' });
    await waitFor(() => expect(attempts).toEqual({ first: 1, second: 1 }));

    expect(screen.getAllByRole('dialog')).toHaveLength(1);
    await user.type(await within(dialog).findByLabelText('Password'), 'Silicio2026');
    await user.click(within(dialog).getByRole('button', { name: 'Conferma' }));

    await expect(requests).resolves.toHaveLength(2);
    expect(attempts).toEqual({ first: 2, second: 2 });
  });

  it('shows the description while the security data are loading', async () => {
    const user = userEvent.setup();
    let release;
    server.use(
      http.get(
        apiUrl('/api/v1/me/security'),
        () =>
          new Promise((resolve) => {
            release = () => resolve(HttpResponse.json({ data: buildSecurityOverview() }));
          }),
      ),
    );

    const dialog = await openDialog(user, {
      prompt: 'Per aggiungere una passkey, conferma che sei tu.',
    });

    expect(
      within(dialog).getByRole('status', { name: 'Caricamento in corso' }),
    ).toBeInTheDocument();
    expect(dialog).toHaveTextContent('Per aggiungere una passkey, conferma che sei tu.');
    release();
    expect(await within(dialog).findByLabelText('Password')).toBeInTheDocument();
  });

  it('retries loading the security data after an error', async () => {
    const user = userEvent.setup();
    let calls = 0;
    server.use(
      http.get(apiUrl('/api/v1/me/security'), () => {
        calls += 1;
        return calls === 1
          ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
          : HttpResponse.json({ data: buildSecurityOverview() });
      }),
    );

    const dialog = await openDialog(user);

    expect(
      await within(dialog).findByText(
        'Non è stato possibile caricare i dati di sicurezza del tuo account.',
      ),
    ).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Riprova' }));

    expect(await within(dialog).findByLabelText('Password')).toBeInTheDocument();
    expect(calls).toBe(2);
  });
});
