import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { queryKeys } from '@/api/queryKeys';
import { recordPost, renderApp, validationError } from '@/test/authHelpers';
import { buildUser } from '@/test/factories';

async function fillForm(user, { password = 'Silicio2026', confirmation = password } = {}) {
  await user.type(await screen.findByLabelText('Nome e cognome'), 'Sara Galli');
  await user.type(screen.getByLabelText('Email'), 'sara.galli@esempio.it');
  await user.type(screen.getByLabelText('Password'), password);
  await user.type(screen.getByLabelText('Conferma password'), confirmation);
}

describe('RegisterPage', () => {
  it('updates the password requirements while typing', async () => {
    const user = userEvent.setup();
    renderApp('/register');

    const requirements = await screen.findByRole('list', { name: 'Requisiti della password' });
    expect(
      within(requirements)
        .getAllByRole('listitem')
        .map((item) => item.textContent),
    ).toEqual([
      'Almeno 10 caratteri, requisito non ancora soddisfatto',
      'Lettere maiuscole e minuscole, requisito non ancora soddisfatto',
      'Almeno un numero, requisito non ancora soddisfatto',
    ]);

    await user.type(screen.getByLabelText('Password'), 'Silicio');
    expect(
      within(requirements)
        .getAllByRole('listitem')
        .map((item) => item.textContent),
    ).toEqual([
      'Almeno 10 caratteri, requisito non ancora soddisfatto',
      'Lettere maiuscole e minuscole, requisito soddisfatto',
      'Almeno un numero, requisito non ancora soddisfatto',
    ]);

    await user.type(screen.getByLabelText('Password'), '2026');
    expect(
      within(requirements)
        .getAllByRole('listitem')
        .map((item) => item.textContent),
    ).toEqual([
      'Almeno 10 caratteri, requisito soddisfatto',
      'Lettere maiuscole e minuscole, requisito soddisfatto',
      'Almeno un numero, requisito soddisfatto',
    ]);
  });

  it('creates the account and asks to confirm the email', async () => {
    const user = userEvent.setup();
    const bodies = recordPost('/api/v1/register', () =>
      HttpResponse.json(
        { data: buildUser({ email: 'sara.galli@esempio.it', email_verified_at: null }) },
        { status: 201 },
      ),
    );
    const { router, queryClient } = renderApp('/register');

    await fillForm(user);
    await user.click(screen.getByRole('button', { name: 'Crea account' }));

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Controlla la tua email' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(/Ti abbiamo inviato un link a sara\.galli@esempio\.it\./),
    ).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/verify-email');
    expect(router.state.location.search).toBe('');
    expect(queryClient.getQueryData(queryKeys.me)).toMatchObject({
      email: 'sara.galli@esempio.it',
    });
    expect(bodies).toEqual([
      {
        name: 'Sara Galli',
        email: 'sara.galli@esempio.it',
        password: 'Silicio2026',
        password_confirmation: 'Silicio2026',
      },
    ]);
  });

  it('validates the form before sending', async () => {
    const user = userEvent.setup();
    const bodies = recordPost('/api/v1/register', () => HttpResponse.json({}, { status: 201 }));
    renderApp('/register');

    await fillForm(user, { password: 'silicio', confirmation: 'altro' });
    await user.click(screen.getByRole('button', { name: 'Crea account' }));

    expect(
      await screen.findByText('La password deve avere almeno 10 caratteri.'),
    ).toBeInTheDocument();
    expect(bodies).toEqual([]);
  });

  it('asks to repeat the same password', async () => {
    const user = userEvent.setup();
    renderApp('/register');

    await fillForm(user, { confirmation: 'Silicio2027' });
    await user.click(screen.getByRole('button', { name: 'Crea account' }));

    expect(await screen.findByText('Le password non coincidono.')).toBeInTheDocument();
  });

  it('shows the errors of the server on the fields', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/register', () =>
      validationError('email', 'Questo indirizzo email è già in uso'),
    );
    renderApp('/register');

    await fillForm(user);
    await user.click(screen.getByRole('button', { name: 'Crea account' }));

    expect(await screen.findByText('Questo indirizzo email è già in uso')).toBeInTheDocument();
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true');
  });

  it('disables the button while sending', async () => {
    const user = userEvent.setup();
    let release;
    recordPost(
      '/api/v1/register',
      () =>
        new Promise((resolve) => {
          release = () =>
            resolve(
              HttpResponse.json({ data: buildUser({ email_verified_at: null }) }, { status: 201 }),
            );
        }),
    );
    renderApp('/register');

    await fillForm(user);
    await user.click(screen.getByRole('button', { name: 'Crea account' }));

    await waitFor(() =>
      expect(screen.getByRole('button', { name: /Crea account/ })).toBeDisabled(),
    );
    release();
    await screen.findByRole('heading', { level: 1, name: 'Controlla la tua email' });
  });

  it('links to the privacy notice', async () => {
    renderApp('/register');

    expect(await screen.findByRole('link', { name: 'informativa sulla privacy' })).toHaveAttribute(
      'href',
      '/note-legali#privacy',
    );
  });

  it('describes the password field with the requirements and, after an error, the message', async () => {
    const user = userEvent.setup();
    renderApp('/register');

    const password = await screen.findByLabelText('Password');
    expect(password).toHaveAccessibleDescription(
      expect.stringContaining('Almeno 10 caratteri, requisito non ancora soddisfatto'),
    );

    await user.type(password, 'corta');
    await user.click(screen.getByRole('button', { name: 'Crea account' }));

    await waitFor(() =>
      expect(password).toHaveAccessibleDescription(
        expect.stringContaining('La password deve avere almeno 10 caratteri.'),
      ),
    );
    expect(password).toHaveAccessibleDescription(
      expect.stringContaining('Almeno un numero, requisito non ancora soddisfatto'),
    );
  });
});
