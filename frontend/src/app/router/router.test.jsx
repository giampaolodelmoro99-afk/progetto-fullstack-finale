import { screen, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it, vi } from 'vitest';
import { routes } from '@/app/router/router';
import { buildAdmin, buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { renderWithProviders } from '@/test/renderWithProviders';

function renderRoute(route, user = null) {
  return renderWithProviders(null, { routes, route, user });
}

async function expectPage(title, level = 1) {
  expect(await screen.findByRole('heading', { level, name: title })).toBeInTheDocument();
}

describe('router', () => {
  it('opens the public pages for a guest', async () => {
    const { router } = renderRoute('/products');

    await expectPage('Catalogo');
    expect(router.state.location.pathname).toBe('/products');
  });

  it('redirects a guest to the login and remembers where they came from', async () => {
    const { router } = renderRoute('/account/security');

    await expectPage('Accedi');
    expect(router.state.location.pathname).toBe('/login');
    expect(router.state.location.state.from).toMatchObject({ pathname: '/account/security' });
  });

  it('keeps the query string of the page the guest came from', async () => {
    const { router } = renderRoute('/account/orders/42?tab=1');

    await expectPage('Accedi');
    expect(router.state.location.state.from).toMatchObject({
      pathname: '/account/orders/42',
      search: '?tab=1',
    });
  });

  it('shows a loading indicator while the user is being loaded', async () => {
    server.use(
      http.get(apiUrl('/api/v1/me'), async () => {
        await new Promise((resolve) => setTimeout(resolve, 50));
        return HttpResponse.json({ data: buildUser() });
      }),
    );

    const { router } = renderWithProviders(null, { routes, route: '/account/profile' });

    expect(screen.getByRole('progressbar', { name: 'Caricamento in corso' })).toBeInTheDocument();
    await expectPage('Profilo', 2);
    expect(router.state.location.pathname).toBe('/account/profile');
  });

  it.each(['/login', '/login/two-factor', '/register', '/forgot-password'])(
    'sends an authenticated user from %s to the home',
    async (route) => {
      const { router } = renderRoute(route, buildUser());

      await expectPage('Casa del Silicio');
      expect(router.state.location.pathname).toBe('/');
    },
  );

  it.each([
    ['/verify-email', 'Conferma la tua email'],
    ['/reset-password?token=abc&email=a%40b.it', 'Scegli una nuova password'],
    ['/confirm-email-change?token=abc', 'Conferma della nuova email'],
    ['/cancel-email-change?token=abc', 'Annulla il cambio email'],
  ])('opens %s for guests and authenticated users', async (route, title) => {
    renderRoute(route);
    await expectPage(title);
  });

  it('opens the email link pages for an authenticated user too', async () => {
    renderRoute('/verify-email', buildUser());

    await expectPage('Conferma la tua email');
  });

  it('redirects /account to the profile', async () => {
    const { router } = renderRoute('/account', buildUser());

    await expectPage('Profilo', 2);
    expect(router.state.location.pathname).toBe('/account/profile');
  });

  it('sends a guest from the admin area to the login', async () => {
    const { router } = renderRoute('/admin/orders');

    await expectPage('Accedi');
    expect(router.state.location.state.from).toMatchObject({ pathname: '/admin/orders' });
  });

  it('sends a customer from the admin area to the home', async () => {
    const { router } = renderRoute('/admin/products', buildUser());

    await expectPage('Casa del Silicio');
    expect(router.state.location.pathname).toBe('/');
  });

  it('opens the admin area for an admin, with the title from the route', async () => {
    renderRoute('/admin/products/new', buildAdmin());

    await expectPage('Nuovo prodotto');
    expect(await screen.findByText('Questa pagina arriva presto.')).toBeInTheDocument();
  });

  it.each([
    ['/account/addresses', '/account/profile'],
    ['/account/orders', '/account/profile'],
    ['/account/orders/12', '/account/profile'],
    ['/cart', '/'],
    ['/checkout', '/'],
    ['/checkout/success/12', '/'],
  ])('redirects an admin from %s to %s', async (route, destination) => {
    const { router } = renderRoute(route, buildAdmin());

    await waitFor(() => expect(router.state.location.pathname).toBe(destination));
    expect(router.state.historyAction).toBe('REPLACE');
  });

  it.each([
    ['/account/addresses', 'Indirizzi', 2],
    ['/account/orders', 'I miei ordini', 2],
    ['/account/orders/12', 'Dettaglio ordine', 2],
    ['/cart', 'Il tuo carrello', 1],
    ['/checkout', 'Checkout', 1],
    ['/checkout/success/12', 'Grazie per il tuo ordine', 1],
  ])('opens %s for a customer', async (route, title, level) => {
    const { router } = renderRoute(route, buildUser());

    await expectPage(title, level);
    expect(router.state.location.pathname).toBe(route);
  });

  it.each(['/cart', '/checkout', '/account/orders'])(
    'sends a guest from %s to the login',
    async (route) => {
      const { router } = renderRoute(route);

      await expectPage('Accedi');
      expect(router.state.location.state.from).toMatchObject({ pathname: route });
    },
  );

  it('shows the not found page inside the shop for an unknown address', async () => {
    renderRoute('/pagina-inesistente');

    await expectPage('Pagina non trovata');
    expect(screen.getByRole('link', { name: 'Torna alla home' })).toHaveAttribute('href', '/');
    expect(screen.getByRole('contentinfo')).toBeInTheDocument();
  });

  it('shows the error page when a route throws', async () => {
    function Broken() {
      throw new Error('boom');
    }
    const brokenRoutes = [{ ...routes[0], children: [{ path: '/', element: <Broken /> }] }];
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    const consoleWarn = vi.spyOn(console, 'warn').mockImplementation(() => {});

    renderWithProviders(null, { routes: brokenRoutes, route: '/', user: null });

    await expectPage('Qualcosa non ha funzionato');
    expect(screen.getByRole('link', { name: 'Torna alla home' })).toHaveAttribute('href', '/');
    consoleError.mockRestore();
    consoleWarn.mockRestore();
  });
});
