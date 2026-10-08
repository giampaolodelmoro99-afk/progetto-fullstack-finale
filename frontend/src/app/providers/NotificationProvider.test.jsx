import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it, vi } from 'vitest';
import { queryKeys } from '@/api/queryKeys';
import { useLogoutToHome } from '@/features/auth/hooks/useLogoutToHome';
import { useNotify } from '@/hooks/useNotify';
import { buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { renderWithProviders } from '@/test/renderWithProviders';

function NotifyButton({ notification }) {
  const notify = useNotify();

  return (
    <button type="button" onClick={() => notify(notification)}>
      Notifica
    </button>
  );
}

function LogoutButton() {
  const logoutToHome = useLogoutToHome();

  return (
    <button type="button" onClick={logoutToHome}>
      Esci
    </button>
  );
}

describe('NotificationProvider', () => {
  it('shows a message in a snackbar', async () => {
    const user = userEvent.setup();
    renderWithProviders(<NotifyButton notification={{ message: 'Modifiche salvate' }} />, {
      user: null,
    });

    await user.click(screen.getByRole('button', { name: 'Notifica' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Modifiche salvate');
  });

  it('runs the action and closes the snackbar', async () => {
    const user = userEvent.setup();
    const onClick = vi.fn();
    renderWithProviders(
      <NotifyButton
        notification={{
          message: 'Aggiunto al carrello',
          action: { label: 'Vai al carrello', onClick },
        }}
      />,
      { user: null },
    );

    await user.click(screen.getByRole('button', { name: 'Notifica' }));
    await user.click(await screen.findByRole('button', { name: 'Vai al carrello' }));

    expect(onClick).toHaveBeenCalledOnce();
    await waitFor(() => expect(screen.queryByRole('alert')).not.toBeInTheDocument());
  });
});

describe('useLogoutToHome', () => {
  it('keeps the user signed in and explains that the logout failed', async () => {
    const user = userEvent.setup();
    server.use(
      http.post(apiUrl('/api/v1/logout'), () =>
        HttpResponse.json({ message: 'Errore.' }, { status: 500 }),
      ),
    );
    const { queryClient } = renderWithProviders(<LogoutButton />, {
      user: buildUser({ name: 'Giulia Ferri' }),
    });

    await user.click(screen.getByRole('button', { name: 'Esci' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Non è stato possibile uscire. Riprova.',
    );
    expect(queryClient.getQueryData(queryKeys.me)).toMatchObject({ name: 'Giulia Ferri' });
  });
});
