import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { routes } from '@/app/router/router';
import { buildAdmin } from '@/test/factories';
import { renderWithProviders } from '@/test/renderWithProviders';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';

function renderAdmin(route = '/admin') {
  return renderWithProviders(null, {
    routes,
    route,
    user: buildAdmin({ name: 'Marta Lenzi', email: 'marta.lenzi@esempio.it' }),
  });
}

function adminNavigation() {
  return screen.getByRole('navigation', { name: 'Amministrazione' });
}

describe('AdminLayout', () => {
  it('shows the page title from the route as the only heading of level one', () => {
    renderAdmin('/admin/orders/7');

    expect(screen.getByRole('heading', { level: 1, name: 'Dettaglio ordine' })).toBeInTheDocument();
    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
  });

  it('shows name, email and initials of the admin', () => {
    renderAdmin();

    const banner = screen.getByRole('banner');
    expect(within(banner).getByText('Marta Lenzi')).toBeInTheDocument();
    expect(within(banner).getByText('marta.lenzi@esempio.it')).toBeInTheDocument();
    expect(within(banner).getByText('ML')).toHaveAttribute('aria-hidden', 'true');
    expect(within(banner).queryByRole('img')).not.toBeInTheDocument();
  });

  it('names the browser tab after the page', () => {
    renderAdmin('/admin/categories');

    expect(document.title).toBe('Categorie · Casa del Silicio');
  });

  it('lists the sections and marks the dashboard only on its own page', () => {
    renderAdmin('/admin');

    const links = within(adminNavigation()).getAllByRole('link');
    expect(links.map((link) => [link.textContent, link.getAttribute('href')])).toEqual([
      ['Dashboard', '/admin'],
      ['Prodotti', '/admin/products'],
      ['Categorie', '/admin/categories'],
      ['Ordini', '/admin/orders'],
    ]);
    expect(within(adminNavigation()).getByRole('link', { name: 'Dashboard' })).toHaveAttribute(
      'aria-current',
      'page',
    );
  });

  it.each(['/admin/products', '/admin/products/new', '/admin/products/3/edit'])(
    'keeps products active on %s',
    (route) => {
      renderAdmin(route);

      expect(within(adminNavigation()).getByRole('link', { name: 'Prodotti' })).toHaveAttribute(
        'aria-current',
        'page',
      );
      expect(
        within(adminNavigation()).getByRole('link', { name: 'Dashboard' }),
      ).not.toHaveAttribute('aria-current');
    },
  );

  it('changes the title when navigating', async () => {
    const user = userEvent.setup();
    renderAdmin('/admin');

    await user.click(within(adminNavigation()).getByRole('link', { name: 'Categorie' }));

    expect(screen.getByRole('heading', { level: 1, name: 'Categorie' })).toBeInTheDocument();
  });

  it('links back to the shop', async () => {
    const user = userEvent.setup();
    const { router } = renderAdmin();

    await user.click(screen.getByRole('link', { name: 'Vai al negozio' }));

    expect(router.state.location.pathname).toBe('/');
  });

  it('logs out and goes to the home', async () => {
    const user = userEvent.setup();
    const { router } = renderAdmin('/admin/orders');

    await user.click(screen.getByRole('button', { name: 'Esci' }));

    expect(await screen.findByRole('link', { name: 'Accedi' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/');
  });

  it('opens the drawer from the menu button on mobile and closes it after a navigation', async () => {
    setViewportWidth(MOBILE_WIDTH);
    const user = userEvent.setup();
    const { router } = renderAdmin('/admin');

    expect(screen.queryByRole('navigation', { name: 'Amministrazione' })).not.toBeInTheDocument();
    expect(screen.queryByText('marta.lenzi@esempio.it')).not.toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Marta Lenzi' })).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Apri il menu' }));
    await user.click(within(adminNavigation()).getByRole('link', { name: 'Ordini' }));

    expect(router.state.location.pathname).toBe('/admin/orders');
    await waitFor(() =>
      expect(screen.queryByRole('navigation', { name: 'Amministrazione' })).not.toBeInTheDocument(),
    );
  });

  it('closes the mobile drawer with its close button', async () => {
    setViewportWidth(MOBILE_WIDTH);
    const user = userEvent.setup();
    renderAdmin('/admin');

    await user.click(screen.getByRole('button', { name: 'Apri il menu' }));
    await user.click(screen.getByRole('button', { name: 'Chiudi il menu' }));

    await waitFor(() =>
      expect(screen.queryByRole('navigation', { name: 'Amministrazione' })).not.toBeInTheDocument(),
    );
  });
});
