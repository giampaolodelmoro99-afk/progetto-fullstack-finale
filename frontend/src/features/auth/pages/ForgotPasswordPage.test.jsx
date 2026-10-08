import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { recordPost, renderApp, tooManyAttempts } from '@/test/authHelpers';

describe('ForgotPasswordPage', () => {
  it('shows the same answer after sending the link', async () => {
    const user = userEvent.setup();
    const bodies = recordPost('/api/v1/forgot-password', () =>
      HttpResponse.json({ message: 'Messaggio generico.' }),
    );
    renderApp('/forgot-password');

    await user.type(await screen.findByLabelText('Email'), 'nessuno@esempio.it');
    await user.click(screen.getByRole('button', { name: 'Invia il link' }));

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Controlla la tua email' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "Se l'indirizzo è registrato, riceverai un'email con il link per scegliere una nuova password.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: "Torna all'accesso" })).toHaveAttribute(
      'href',
      '/login',
    );
    expect(bodies).toEqual([{ email: 'nessuno@esempio.it' }]);
  });

  it('shows the waiting time after too many requests', async () => {
    const user = userEvent.setup();
    recordPost('/api/v1/forgot-password', () => tooManyAttempts(null));
    renderApp('/forgot-password');

    await user.type(await screen.findByLabelText('Email'), 'giulia.ferri@esempio.it');
    await user.click(screen.getByRole('button', { name: 'Invia il link' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Troppi tentativi. Riprova tra poco',
    );
  });
});
