import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it, vi } from 'vitest';
import { routes } from '@/app/router/router';
import { buildAdmin, buildCart, buildCartItem, buildUser } from '@/test/factories';
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

    await expectPage('Tutti i prodotti');
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

      await expectPage('Suono, in silenzio');
      expect(router.state.location.pathname).toBe('/');
    },
  );

  it.each([
    ['a guest', null],
    ['an authenticated user', buildUser({ email: 'sara.galli@esempio.it' })],
  ])('opens the email link pages for %s', async (label, user) => {
    server.use(
      http.post(apiUrl('/api/v1/reset-password/check'), () =>
        HttpResponse.json({ requires_second_factor: false, methods: [] }),
      ),
      http.post(apiUrl('/api/v1/email-change/confirm'), () =>
        HttpResponse.json({ data: { email: 'nuova@esempio.it' } }),
      ),
      http.post(
        apiUrl('/api/v1/email-change/cancel'),
        () => new HttpResponse(null, { status: 204 }),
      ),
    );

    const pages = [
      ['/reset-password?token=abc&email=a%40b.it', 'Scegli una nuova password'],
      ['/confirm-email-change?token=abc', 'Email aggiornata'],
      ['/cancel-email-change?token=abc', 'Richiesta annullata'],
    ];

    for (const [route, title] of pages) {
      const { unmount } = renderRoute(route, user);
      await expectPage(title);
      unmount();
    }
  });

  it('asks a guest to log in before showing the email to verify', async () => {
    const { router } = renderRoute('/verify-email');

    await expectPage('Accedi');
    expect(router.state.location.state.from).toMatchObject({ pathname: '/verify-email' });
  });

  it('shows the email to verify to an authenticated user', async () => {
    renderRoute('/verify-email', buildUser({ email_verified_at: null }));

    await expectPage('Controlla la tua email');
  });

  it('honours the page the guest came from when an authenticated user opens the login', async () => {
    const { router } = renderWithProviders(null, {
      routes,
      route: { pathname: '/login', state: { from: { pathname: '/account/security' } } },
      user: buildUser(),
    });

    await expectPage('Sicurezza', 2);
    expect(router.state.location.pathname).toBe('/account/security');
  });

  it.each(['/account/profile', '/cart', '/admin'])(
    'shows an error with a retry instead of the login when the user cannot be loaded on %s',
    async (route) => {
      const user = userEvent.setup();
      let attempts = 0;
      server.use(
        http.get(apiUrl('/api/v1/me'), () => {
          attempts += 1;
          return attempts === 1
            ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
            : HttpResponse.json({ data: buildAdmin() });
        }),
      );

      const { router } = renderWithProviders(null, { routes, route });

      expect(
        await screen.findByText('Non è stato possibile caricare il tuo account.'),
      ).toBeInTheDocument();
      expect(router.state.location.pathname).toBe(route);

      await user.click(screen.getByRole('button', { name: 'Riprova' }));

      await waitFor(() =>
        expect(
          screen.queryByText('Non è stato possibile caricare il tuo account.'),
        ).not.toBeInTheDocument(),
      );
      expect(router.state.location.pathname).not.toBe('/login');
    },
  );

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

    await expectPage('Suono, in silenzio');
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
    ['/account/orders/12', 'Ordine ORD-2026-000042', 2],
    ['/cart', 'Il tuo carrello', 1],
    ['/checkout', 'Checkout', 1],
    ['/checkout/success/12', 'Grazie per il tuo ordine', 1],
  ])('opens %s for a customer', async (route, title, level) => {
    server.use(
      http.get(apiUrl('/api/v1/cart'), () =>
        HttpResponse.json({
          data: buildCart({
            items: [buildCartItem()],
            summary: { items_count: 1, subtotal: 34900, total: 34900 },
            can_checkout: true,
          }),
        }),
      ),
    );

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
