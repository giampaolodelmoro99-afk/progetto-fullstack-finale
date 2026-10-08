import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { routes } from '@/app/router/router';
import { buildAdmin, buildUser } from '@/test/factories';
import { renderWithProviders } from '@/test/renderWithProviders';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';

function renderAccount(route, user = buildUser()) {
  return renderWithProviders(null, { routes, route, user });
}

function sideNavigation() {
  return screen.getByRole('navigation', { name: 'Area account' });
}

describe('AccountLayout', () => {
  it('shows the heading with name and email', () => {
    renderAccount(
      '/account/profile',
      buildUser({ name: 'Giulia Ferri', email: 'giulia.ferri@esempio.it' }),
    );

    expect(screen.getByRole('heading', { level: 1, name: 'Il tuo account' })).toBeInTheDocument();
    expect(screen.getByText('Giulia Ferri · giulia.ferri@esempio.it')).toBeInTheDocument();
  });

  it('lists the customer items and marks the current one', () => {
    renderAccount('/account/orders/12');

    const links = within(sideNavigation()).getAllByRole('link');
    expect(links.map((link) => link.textContent)).toEqual([
      'Profilo',
      'Indirizzi',
      'I miei ordini',
      'Sicurezza',
    ]);
    expect(within(sideNavigation()).getByRole('link', { name: 'I miei ordini' })).toHaveAttribute(
      'aria-current',
      'page',
    );
    expect(within(sideNavigation()).getByRole('link', { name: 'Profilo' })).not.toHaveAttribute(
      'aria-current',
    );
  });

  it('shows administration instead of addresses and orders to an admin', () => {
    renderAccount('/account/security', buildAdmin());

    const links = within(sideNavigation()).getAllByRole('link');
    expect(links.map((link) => [link.textContent, link.getAttribute('href')])).toEqual([
      ['Profilo', '/account/profile'],
      ['Sicurezza', '/account/security'],
      ['Amministrazione', '/admin'],
    ]);
  });

  it('navigates between the sections', async () => {
    const user = userEvent.setup();
    const { router } = renderAccount('/account/profile');

    await user.click(within(sideNavigation()).getByRole('link', { name: 'Sicurezza' }));

    expect(router.state.location.pathname).toBe('/account/security');
    expect(await screen.findByRole('heading', { level: 2, name: 'Sicurezza' })).toBeInTheDocument();
  });

  it('logs out from the side navigation', async () => {
    const user = userEvent.setup();
    const { router } = renderAccount('/account/profile');

    await user.click(within(sideNavigation()).getByRole('button', { name: 'Esci' }));

    expect(await screen.findByRole('link', { name: 'Accedi' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/');
  });

  it('uses tabs on mobile', async () => {
    setViewportWidth(MOBILE_WIDTH);
    const user = userEvent.setup();
    const { router } = renderAccount('/account/addresses');

    const tabs = screen.getByRole('tablist', { name: 'Area account' });
    expect(
      within(tabs)
        .getAllByRole('tab')
        .map((tab) => tab.textContent),
    ).toEqual(['Profilo', 'Indirizzi', 'I miei ordini', 'Sicurezza']);
    expect(within(tabs).getByRole('tab', { name: 'Indirizzi' })).toHaveAttribute(
      'aria-selected',
      'true',
    );
    expect(screen.queryByRole('navigation', { name: 'Area account' })).not.toBeInTheDocument();

    await user.click(within(tabs).getByRole('tab', { name: 'I miei ordini' }));

    expect(router.state.location.pathname).toBe('/account/orders');
  });

  it('shows the admin tabs on mobile', () => {
    setViewportWidth(MOBILE_WIDTH);
    renderAccount('/account/profile', buildAdmin());

    const tabs = screen.getByRole('tablist', { name: 'Area account' });
    expect(
      within(tabs)
        .getAllByRole('tab')
        .map((tab) => tab.textContent),
    ).toEqual(['Profilo', 'Sicurezza', 'Amministrazione']);
  });
});
