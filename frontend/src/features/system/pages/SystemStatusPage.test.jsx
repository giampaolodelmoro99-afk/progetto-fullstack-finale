import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import SystemStatusPage from '@/features/system/pages/SystemStatusPage';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { renderWithProviders } from '@/test/renderWithProviders';

function section(name) {
  return screen.getByRole('region', { name });
}

describe('SystemStatusPage', () => {
  it('shows the page heading', () => {
    renderWithProviders(<SystemStatusPage />);

    expect(
      screen.getByRole('heading', { level: 1, name: 'Verifica del collegamento' }),
    ).toBeInTheDocument();
  });

  it('shows a loading state while the csrf cookie is requested', () => {
    renderWithProviders(<SystemStatusPage />);

    expect(
      within(section('Cookie CSRF')).getByRole('status', {
        name: 'Verifica in corso: Cookie CSRF',
      }),
    ).toBeInTheDocument();
    expect(within(section("Stato dell'API")).getByText('In attesa del cookie CSRF.')).toBeVisible();
  });

  it('shows both checks as successful when the backend answers', async () => {
    renderWithProviders(<SystemStatusPage />);

    expect(
      await within(section('Cookie CSRF')).findByText(
        'Cookie CSRF ricevuto: CORS con credenziali funzionante.',
      ),
    ).toBeInTheDocument();
    expect(
      await within(section("Stato dell'API")).findByText('Stato del servizio: ok'),
    ).toBeInTheDocument();
  });

  it('skips the health check when the csrf cookie request fails', async () => {
    let healthRequests = 0;
    server.use(
      http.get(apiUrl('/sanctum/csrf-cookie'), () =>
        HttpResponse.json({ message: 'SQLSTATE[HY000] connection refused' }, { status: 500 }),
      ),
      http.get(apiUrl('/api/v1/health'), () => {
        healthRequests += 1;
        return HttpResponse.json({ status: 'ok' });
      }),
    );

    renderWithProviders(<SystemStatusPage />);

    expect(
      await within(section('Cookie CSRF')).findByText(
        'Si è verificato un errore imprevisto. Riprova più tardi.',
      ),
    ).toBeVisible();
    expect(screen.queryByText(/SQLSTATE/)).not.toBeInTheDocument();
    expect(
      within(section("Stato dell'API")).getByText(
        'Verifica non eseguita: serve prima il cookie CSRF.',
      ),
    ).toBeVisible();
    expect(healthRequests).toBe(0);
  });

  it('shows the health error and recovers after retrying', async () => {
    const user = userEvent.setup();
    server.use(
      http.get(
        apiUrl('/api/v1/health'),
        () => HttpResponse.json({ message: 'Servizio non disponibile.' }, { status: 503 }),
        { once: true },
      ),
    );

    renderWithProviders(<SystemStatusPage />);

    const healthSection = section("Stato dell'API");
    expect(
      await within(healthSection).findByText(
        'Si è verificato un errore imprevisto. Riprova più tardi.',
      ),
    ).toBeVisible();

    await user.click(within(healthSection).getByRole('button', { name: 'Riprova' }));

    expect(await within(healthSection).findByText('Stato del servizio: ok')).toBeVisible();
  });

  it('shows a network error message when the api is unreachable', async () => {
    server.use(http.get(apiUrl('/sanctum/csrf-cookie'), () => HttpResponse.error()));

    renderWithProviders(<SystemStatusPage />);

    expect(
      await within(section('Cookie CSRF')).findByText(
        'Impossibile contattare il server. Controlla la connessione e riprova.',
      ),
    ).toBeVisible();
  });
});
