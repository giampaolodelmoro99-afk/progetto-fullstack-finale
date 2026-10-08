import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { routes } from '@/app/router/router';
import { buildAdmin, buildCart, buildCategories, buildCategory, buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { renderWithProviders } from '@/test/renderWithProviders';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';

function renderStore(route = '/', user = null) {
  return renderWithProviders(null, { routes, route, user });
}

function categoryNavigation() {
  return screen.getByRole('navigation', { name: 'Menu principale' });
}

function footer() {
  return screen.getByRole('contentinfo');
}

function delayedCategories() {
  return http.get(apiUrl('/api/v1/categories'), async () => {
    await new Promise((resolve) => setTimeout(resolve, 30));
    return HttpResponse.json({ data: buildCategories() });
  });
}

function failingCategories() {
  return http.get(apiUrl('/api/v1/categories'), () =>
    HttpResponse.json({ message: 'Errore.' }, { status: 500 }),
  );
}

describe('StoreLayout header', () => {
  it('shows new arrivals and the categories received from the api, in order', async () => {
    server.use(
      http.get(apiUrl('/api/v1/categories'), () =>
        HttpResponse.json({
          data: [...buildCategories(), buildCategory({ name: 'Viaggio', slug: 'viaggio' })],
        }),
      ),
    );

    renderStore();

    const navigation = categoryNavigation();
    await within(navigation).findByRole('link', { name: 'Viaggio' });
    const links = within(navigation).getAllByRole('link');
    expect(links.map((link) => link.textContent)).toEqual([
      'Novità',
      'Audio',
      'Ricarica ed energia',
      'Scrivania',
      'Indossabili',
      'Casa smart',
      'Viaggio',
    ]);
    expect(links[0]).toHaveAttribute('href', '/products?new=1');
    expect(links[6]).toHaveAttribute('href', '/products?category=viaggio');
  });

  it('marks the navigation as busy while the categories load', async () => {
    server.use(delayedCategories());

    renderStore();

    expect(categoryNavigation()).toHaveAttribute('aria-busy', 'true');
    expect(within(categoryNavigation()).getAllByRole('link')).toHaveLength(1);
    await within(categoryNavigation()).findByRole('link', { name: 'Audio' });
    expect(categoryNavigation()).toHaveAttribute('aria-busy', 'false');
  });

  it('keeps only new arrivals when the categories fail to load, without a message', async () => {
    server.use(failingCategories());

    renderStore();

    await waitFor(() => expect(categoryNavigation()).toHaveAttribute('aria-busy', 'false'));
    expect(
      within(categoryNavigation())
        .getAllByRole('link')
        .map((link) => link.textContent),
    ).toEqual(['Novità']);
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('underlines the category of the current catalog page', async () => {
    renderStore('/products?category=scrivania');

    const active = await within(categoryNavigation()).findByRole('link', { name: 'Scrivania' });
    expect(active).toHaveAttribute('aria-current', 'page');
    expect(within(categoryNavigation()).getByRole('link', { name: 'Audio' })).not.toHaveAttribute(
      'aria-current',
    );
  });

  it('underlines new arrivals on the new arrivals page', async () => {
    renderStore('/products?new=1');

    expect(within(categoryNavigation()).getByRole('link', { name: 'Novità' })).toHaveAttribute(
      'aria-current',
      'page',
    );
  });

  it('searches the catalog from the header', async () => {
    const user = userEvent.setup();
    const { router } = renderStore();

    await user.type(
      screen.getByRole('searchbox', { name: 'Cerca nel negozio' }),
      'cuffie aria{Enter}',
    );

    expect(router.state.location.pathname).toBe('/products');
    expect(router.state.location.search).toBe('?search=cuffie+aria');
    expect(screen.getByRole('searchbox', { name: 'Cerca nel negozio' })).toHaveValue('cuffie aria');
  });

  it('does not search with an empty text', async () => {
    const user = userEvent.setup();
    const { router } = renderStore();

    await user.type(screen.getByRole('searchbox', { name: 'Cerca nel negozio' }), '   {Enter}');

    expect(router.state.location.pathname).toBe('/');
  });

  it('links the logo to the home', () => {
    renderStore('/products');

    expect(
      within(screen.getByRole('banner')).getByRole('link', {
        name: 'Casa del Silicio, vai alla home',
      }),
    ).toHaveAttribute('href', '/');
  });
});

describe('StoreLayout account menu', () => {
  it('links a guest to the login', async () => {
    renderStore();

    expect(await screen.findByRole('link', { name: 'Accedi' })).toHaveAttribute('href', '/login');
    expect(screen.queryByRole('button', { name: 'Il tuo account' })).not.toBeInTheDocument();
  });

  it('opens the customer menu with name, email and the customer items', async () => {
    const user = userEvent.setup();
    renderStore('/', buildUser({ name: 'Giulia Ferri', email: 'giulia.ferri@esempio.it' }));

    await user.click(screen.getByRole('button', { name: 'Il tuo account' }));

    const menu = screen.getByRole('menu');
    expect(within(menu).getByText('Giulia Ferri')).toBeInTheDocument();
    expect(within(menu).getByText('giulia.ferri@esempio.it')).toBeInTheDocument();
    expect(
      within(menu)
        .getAllByRole('menuitem')
        .map((item) => item.textContent),
    ).toEqual(['Profilo', 'Indirizzi', 'I miei ordini', 'Sicurezza', 'Esci']);
    expect(within(menu).getByRole('menuitem', { name: 'I miei ordini' })).toHaveAttribute(
      'href',
      '/account/orders',
    );
  });

  it('shows administration instead of addresses and orders to an admin', async () => {
    const user = userEvent.setup();
    renderStore('/', buildAdmin());

    await user.click(screen.getByRole('button', { name: 'Il tuo account' }));

    const menu = screen.getByRole('menu');
    expect(
      within(menu)
        .getAllByRole('menuitem')
        .map((item) => item.textContent),
    ).toEqual(['Profilo', 'Sicurezza', 'Amministrazione', 'Esci']);
    expect(within(menu).getByRole('menuitem', { name: 'Amministrazione' })).toHaveAttribute(
      'href',
      '/admin',
    );
  });

  it('navigates and closes the menu when an item is chosen', async () => {
    const user = userEvent.setup();
    const { router } = renderStore('/', buildUser());

    await user.click(screen.getByRole('button', { name: 'Il tuo account' }));
    await user.click(screen.getByRole('menuitem', { name: 'Sicurezza' }));

    expect(router.state.location.pathname).toBe('/account/security');
    await waitFor(() => expect(screen.queryByRole('menu')).not.toBeInTheDocument());
  });

  it('names the browser tab after the page, with only the shop name on the home', async () => {
    const { router } = renderStore('/products');

    expect(document.title).toBe('Catalogo · Casa del Silicio');

    await router.navigate('/');

    await waitFor(() => expect(document.title).toBe('Casa del Silicio'));
  });

  it('stays authenticated on the home when the logout fails', async () => {
    server.use(
      http.post(apiUrl('/api/v1/logout'), () =>
        HttpResponse.json({ message: 'Errore.' }, { status: 500 }),
      ),
    );
    const user = userEvent.setup();
    const { router } = renderStore('/account/profile', buildUser());

    await user.click(await screen.findByRole('button', { name: 'Il tuo account' }));
    await user.click(screen.getByRole('menuitem', { name: 'Esci' }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/'));
    expect(screen.getByRole('button', { name: 'Il tuo account' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Accedi' })).not.toBeInTheDocument();
  });

  it('logs out and goes back to the home', async () => {
    const user = userEvent.setup();
    const { router } = renderStore('/account/profile', buildUser());

    await user.click(await screen.findByRole('button', { name: 'Il tuo account' }));
    await user.click(screen.getByRole('menuitem', { name: 'Esci' }));

    expect(await screen.findByRole('link', { name: 'Accedi' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/');
  });
});

describe('StoreLayout cart button', () => {
  it('shows the number of items of the customer cart', async () => {
    server.use(
      http.get(apiUrl('/api/v1/cart'), () =>
        HttpResponse.json({ data: buildCart({ summary: { items_count: 3 } }) }),
      ),
    );

    renderStore('/', buildUser());

    const cart = await screen.findByRole('link', { name: 'Carrello, 3 articoli' });
    expect(cart).toHaveAttribute('href', '/cart');
    expect(within(cart).getByText('3')).toBeInTheDocument();
  });

  it('uses the singular for one item', async () => {
    server.use(
      http.get(apiUrl('/api/v1/cart'), () =>
        HttpResponse.json({ data: buildCart({ summary: { items_count: 1 } }) }),
      ),
    );

    renderStore('/', buildUser());

    expect(await screen.findByRole('link', { name: 'Carrello, 1 articolo' })).toBeInTheDocument();
  });

  it('shows the cart without a badge to a guest, without asking the api', async () => {
    let cartRequests = 0;
    server.use(
      http.get(apiUrl('/api/v1/cart'), () => {
        cartRequests += 1;
        return HttpResponse.json({ data: buildCart() });
      }),
    );

    renderStore();

    expect(await screen.findByRole('link', { name: 'Carrello' })).toHaveAttribute('href', '/cart');
    expect(cartRequests).toBe(0);
  });

  it('hides the cart from an admin and never asks for it', async () => {
    let cartRequests = 0;
    server.use(
      http.get(apiUrl('/api/v1/cart'), () => {
        cartRequests += 1;
        return HttpResponse.json({ data: buildCart() });
      }),
    );

    renderStore('/', buildAdmin());

    await screen.findByRole('button', { name: 'Il tuo account' });
    expect(screen.queryByRole('link', { name: /^Carrello/ })).not.toBeInTheDocument();
    expect(cartRequests).toBe(0);
  });
});

describe('StoreLayout footer', () => {
  it('lists the categories received from the api', async () => {
    renderStore();

    const column = within(footer()).getByRole('navigation', { name: 'Categorie' });
    expect(await within(column).findByRole('link', { name: 'Casa smart' })).toHaveAttribute(
      'href',
      '/products?category=casa-smart',
    );
    expect(within(column).getAllByRole('link')).toHaveLength(5);
  });

  it('links the support column to the support anchors and to the orders', () => {
    renderStore();

    const column = within(footer()).getByRole('navigation', { name: 'Assistenza' });
    const links = within(column).getAllByRole('link');
    expect(links.map((link) => [link.textContent, link.getAttribute('href')])).toEqual([
      ['Spedizioni e consegne', '/assistenza#spedizioni'],
      ['Resi e rimborsi', '/assistenza#resi'],
      ['Garanzia', '/assistenza#garanzia'],
      ["Stato dell'ordine", '/account/orders'],
      ['Contatti', '/assistenza#contatti'],
    ]);
  });

  it('links the legal column to the legal anchors', () => {
    renderStore();

    const column = within(footer()).getByRole('navigation', { name: 'Note legali' });
    const links = within(column).getAllByRole('link');
    expect(links.map((link) => [link.textContent, link.getAttribute('href')])).toEqual([
      ['Condizioni di vendita', '/note-legali#condizioni-di-vendita'],
      ['Diritto di recesso', '/note-legali#diritto-di-recesso'],
      ['Privacy', '/note-legali#privacy'],
      ['Cookie', '/note-legali#cookie'],
      ['Note legali', '/note-legali'],
    ]);
  });

  it('hides the categories column when the categories fail to load', async () => {
    server.use(failingCategories());

    renderStore();

    await waitFor(() =>
      expect(
        within(footer()).queryByRole('navigation', { name: 'Categorie' }),
      ).not.toBeInTheDocument(),
    );
    expect(within(footer()).getByRole('navigation', { name: 'Assistenza' })).toBeInTheDocument();
  });

  it('shows the payoff, the copyright and the reassurance line', () => {
    renderStore();

    expect(
      within(footer()).getByText(
        'Elettronica e accessori tech della nostra linea, scelti per durare.',
      ),
    ).toBeInTheDocument();
    expect(within(footer()).getByText(/^© \d{4} Casa del Silicio$/)).toBeInTheDocument();
    expect(
      within(footer()).getByText('Prezzi IVA inclusa · Pagamenti protetti'),
    ).toBeInTheDocument();
  });
});

describe('StoreLayout on mobile', () => {
  it('replaces the category bar with a drawer', async () => {
    setViewportWidth(MOBILE_WIDTH);
    const user = userEvent.setup();
    renderStore();

    expect(screen.queryByRole('navigation', { name: 'Menu principale' })).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Apri il menu' }));

    const drawer = screen.getByRole('navigation', { name: 'Menu del negozio' });
    const categories = within(drawer).getByRole('list', { name: 'Categorie' });
    await within(categories).findByRole('link', { name: 'Audio' });
    expect(
      within(categories)
        .getAllByRole('link')
        .map((link) => link.textContent),
    ).toEqual(['Audio', 'Ricarica ed energia', 'Scrivania', 'Indossabili', 'Casa smart', 'Novità']);
    const account = within(drawer).getByRole('list', { name: 'Il tuo account' });
    expect(
      within(account)
        .getAllByRole('link')
        .map((link) => link.textContent),
    ).toEqual(['Accedi o registrati', 'I miei ordini', 'Assistenza']);
  });

  it('closes the drawer after a navigation', async () => {
    setViewportWidth(MOBILE_WIDTH);
    const user = userEvent.setup();
    const { router } = renderStore();

    await user.click(screen.getByRole('button', { name: 'Apri il menu' }));
    await user.click(await screen.findByRole('link', { name: 'Scrivania' }));

    expect(router.state.location.search).toBe('?category=scrivania');
    await waitFor(() =>
      expect(
        screen.queryByRole('navigation', { name: 'Menu del negozio' }),
      ).not.toBeInTheDocument(),
    );
  });

  it('closes the drawer with its close button', async () => {
    setViewportWidth(MOBILE_WIDTH);
    const user = userEvent.setup();
    renderStore();

    await user.click(screen.getByRole('button', { name: 'Apri il menu' }));
    await user.click(screen.getByRole('button', { name: 'Chiudi il menu' }));

    await waitFor(() =>
      expect(
        screen.queryByRole('navigation', { name: 'Menu del negozio' }),
      ).not.toBeInTheDocument(),
    );
  });

  it('keeps only new arrivals in the drawer when the categories fail', async () => {
    setViewportWidth(MOBILE_WIDTH);
    server.use(failingCategories());
    const user = userEvent.setup();
    renderStore();

    await user.click(screen.getByRole('button', { name: 'Apri il menu' }));

    const categories = within(
      screen.getByRole('navigation', { name: 'Menu del negozio' }),
    ).getByRole('list', { name: 'Categorie' });
    await waitFor(() =>
      expect(
        within(categories)
          .getAllByRole('link')
          .map((link) => link.textContent),
      ).toEqual(['Novità']),
    );
  });

  it('shows the account items, support and logout to an authenticated admin', async () => {
    setViewportWidth(MOBILE_WIDTH);
    const user = userEvent.setup();
    const { router } = renderStore('/', buildAdmin());

    await user.click(screen.getByRole('button', { name: 'Apri il menu' }));

    const account = within(screen.getByRole('navigation', { name: 'Menu del negozio' })).getByRole(
      'list',
      { name: 'Il tuo account' },
    );
    expect(
      within(account)
        .getAllByRole('link')
        .map((link) => link.textContent),
    ).toEqual(['Profilo', 'Sicurezza', 'Amministrazione', 'Assistenza']);

    await user.click(within(account).getByRole('button', { name: 'Esci' }));

    expect(await screen.findByRole('link', { name: 'Accedi' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/');
  });

  it('moves the search below the app bar', () => {
    setViewportWidth(MOBILE_WIDTH);
    renderStore();

    expect(screen.getByRole('searchbox', { name: 'Cerca nel negozio' })).toBeInTheDocument();
  });
});
