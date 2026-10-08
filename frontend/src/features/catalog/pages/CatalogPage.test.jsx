import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderApp } from '@/test/authHelpers';
import { buildCategories, buildCategory, buildProduct, buildProductPage } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';

function audioProducts() {
  return [
    buildProduct({ name: 'Cuffie Aria', price: 34900, is_new: true }),
    buildProduct({
      name: 'Diffusore Eco Mini',
      price: 39900,
      availability: { status: 'low_stock', label: 'Ultimi 2 pezzi' },
    }),
    buildProduct({
      name: 'Radio Onda Portatile',
      price: 12900,
      availability: { status: 'out_of_stock', label: 'Esaurito' },
    }),
  ];
}

function serveProducts(respond = () => HttpResponse.json(buildProductPage(audioProducts()))) {
  const requests = [];
  server.use(
    http.get(apiUrl('/api/v1/products'), ({ request }) => {
      const params = new URL(request.url).searchParams;
      requests.push(params.toString());
      return respond(params);
    }),
  );

  return requests;
}

function location(router) {
  return `${router.state.location.pathname}${router.state.location.search}`;
}

function filters() {
  return screen.getByRole('complementary', { name: 'Filtri' });
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('CatalogPage', () => {
  it('shows the category title, breadcrumb, count and products', async () => {
    const requests = serveProducts();

    renderApp('/products?category=audio');

    expect(await screen.findByRole('heading', { level: 1, name: 'Audio' })).toBeInTheDocument();
    expect(await screen.findByText('3 prodotti')).toBeInTheDocument();
    const breadcrumb = screen.getByRole('navigation', { name: 'Percorso' });
    expect(within(breadcrumb).getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/');
    expect(within(breadcrumb).getByText('Audio')).toHaveAttribute('aria-current', 'page');
    const grid = screen.getByRole('list', { name: 'Prodotti' });
    expect(within(grid).getAllByRole('listitem')).toHaveLength(3);
    expect(within(grid).getByText('Ultimi 2 pezzi')).toBeInTheDocument();
    expect(within(grid).getByText('Esaurito')).toBeInTheDocument();
    expect(within(grid).getAllByText('Novità')).toHaveLength(1);
    expect(requests).toEqual(['sort=newest&page=1&per_page=12&category=audio']);
  });

  it.each([
    ['/products', 'Tutti i prodotti'],
    ['/products?new=1', 'Novità'],
  ])('titles %s as %s', async (route, title) => {
    serveProducts();

    renderApp(route);

    expect(await screen.findByRole('heading', { level: 1, name: title })).toBeInTheDocument();
  });

  it('shows the search from the query string', async () => {
    const requests = serveProducts(() =>
      HttpResponse.json(buildProductPage([buildProduct({ name: 'Cuffie Aria' })])),
    );

    renderApp('/products?search=aria');

    expect(await screen.findByText('Risultati per «aria»')).toBeInTheDocument();
    expect(await screen.findByText('1 prodotto')).toBeInTheDocument();
    expect(requests).toEqual(['sort=newest&page=1&per_page=12&search=aria']);
  });

  it('changes category from the chips and drops page and price', async () => {
    const user = userEvent.setup();
    const requests = serveProducts();
    const { router } = renderApp('/products?category=audio&min_price=1000&page=2');

    await user.click(await within(filters()).findByRole('button', { name: 'Scrivania' }));

    expect(location(router)).toBe('/products?category=scrivania');
    await waitFor(() =>
      expect(requests.at(-1)).toBe('sort=newest&page=1&per_page=12&category=scrivania'),
    );
    expect(within(filters()).getByRole('button', { name: 'Scrivania' })).toHaveAttribute(
      'aria-pressed',
      'true',
    );

    await user.click(within(filters()).getByRole('button', { name: 'Tutte' }));

    expect(location(router)).toBe('/products');
  });

  it('sorts the products and goes back to the first page', async () => {
    const user = userEvent.setup();
    const requests = serveProducts();
    const { router } = renderApp('/products?category=audio&page=2');

    await user.click(await screen.findByRole('combobox', { name: 'Ordina per' }));
    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual([
      'Novità',
      'Prezzo crescente',
      'Prezzo decrescente',
    ]);
    await user.click(screen.getByRole('option', { name: 'Prezzo crescente' }));

    expect(location(router)).toBe('/products?category=audio&sort=price_asc');
    await waitFor(() =>
      expect(requests.at(-1)).toBe('sort=price_asc&page=1&per_page=12&category=audio'),
    );

    await user.click(screen.getByRole('combobox', { name: 'Ordina per' }));
    await user.click(screen.getByRole('option', { name: 'Novità' }));

    expect(location(router)).toBe('/products?category=audio');
  });

  it('shows only available products with the switch', async () => {
    const user = userEvent.setup();
    const requests = serveProducts();
    const { router } = renderApp('/products?page=3');

    await user.click(await within(filters()).findByRole('switch', { name: 'Solo disponibili' }));

    expect(location(router)).toBe('/products?in_stock=1');
    await waitFor(() => expect(requests.at(-1)).toBe('sort=newest&page=1&per_page=12&in_stock=1'));
  });

  it('shows only new arrivals with the switch and titles the page accordingly', async () => {
    const user = userEvent.setup();
    const requests = serveProducts();
    const { router } = renderApp('/products?page=2');

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Tutti i prodotti' }),
    ).toBeInTheDocument();
    await user.click(within(filters()).getByRole('switch', { name: 'Solo novità' }));

    expect(location(router)).toBe('/products?new=1');
    expect(screen.getByRole('heading', { level: 1, name: 'Novità' })).toBeInTheDocument();
    await waitFor(() => expect(requests.at(-1)).toBe('sort=newest&page=1&per_page=12&new=1'));

    await user.click(within(filters()).getByRole('switch', { name: 'Solo novità' }));

    expect(location(router)).toBe('/products');
    expect(screen.getByRole('heading', { level: 1, name: 'Tutti i prodotti' })).toBeInTheDocument();
  });

  it('opens a product from a card at the top of the page', async () => {
    const user = userEvent.setup();
    serveProducts(() =>
      HttpResponse.json(
        buildProductPage([buildProduct({ name: 'Cuffie Aria', slug: 'cuffie-aria' })]),
      ),
    );
    const { router } = renderApp('/products');
    const scrollTo = vi.spyOn(window, 'scrollTo');

    const card = await screen.findByRole('link', { name: /Cuffie Aria/ });
    scrollTo.mockClear();
    await user.click(card);

    expect(router.state.location.pathname).toBe('/products/cuffie-aria');
    await waitFor(() => expect(scrollTo).toHaveBeenCalledWith(0, 0));
  });

  it('moves between pages', async () => {
    const user = userEvent.setup();
    const requests = serveProducts((params) =>
      HttpResponse.json(
        buildProductPage(audioProducts(), {
          current_page: Number(params.get('page')),
          last_page: 3,
          total: 30,
        }),
      ),
    );
    const { router } = renderApp('/products?category=audio');

    await user.click(await screen.findByRole('button', { name: 'Vai alla pagina 2' }));

    expect(location(router)).toBe('/products?category=audio&page=2');
    await waitFor(() =>
      expect(requests.at(-1)).toBe('sort=newest&page=2&per_page=12&category=audio'),
    );
    expect(await screen.findByRole('button', { name: 'pagina 2' })).toHaveAttribute(
      'aria-current',
      'page',
    );
  });

  it('goes back to the first page when the page is beyond the last one', async () => {
    const requests = serveProducts((params) =>
      HttpResponse.json(
        params.get('page') === '9'
          ? buildProductPage([], { current_page: 9, last_page: 1, total: 3 })
          : buildProductPage(audioProducts()),
      ),
    );
    const { router } = renderApp('/products?category=audio&page=9');

    await waitFor(() => expect(location(router)).toBe('/products?category=audio'));
    expect(await screen.findByRole('list', { name: 'Prodotti' })).toBeInTheDocument();
    expect(requests.at(-1)).toBe('sort=newest&page=1&per_page=12&category=audio');
  });

  it('takes the slider limits from the price range and applies them only on release', async () => {
    const requests = serveProducts(() =>
      HttpResponse.json(
        buildProductPage(audioProducts(), { price_range: { min: 1950, max: 9900 } }),
      ),
    );
    const { router } = renderApp('/products?category=audio');

    const minimum = await within(filters()).findByRole('slider', { name: 'Prezzo minimo' });
    const maximum = within(filters()).getByRole('slider', { name: 'Prezzo massimo' });
    expect(minimum).toHaveAttribute('aria-valuemin', '1900');
    expect(maximum).toHaveAttribute('aria-valuemax', '9900');
    expect(minimum).toHaveAttribute('aria-valuetext', '19,00\u00a0€');
    expect(maximum).toHaveAttribute('aria-valuetext', '99,00\u00a0€');

    const root = minimum.closest('.MuiSlider-root');
    root.hasPointerCapture = () => false;
    root.releasePointerCapture = () => {};
    vi.spyOn(root, 'getBoundingClientRect').mockReturnValue({
      left: 0,
      width: 80,
      top: 0,
      bottom: 10,
      height: 10,
      right: 80,
    });

    fireEvent.pointerDown(root, { button: 0, pointerId: 1, clientX: 10 });
    fireEvent.pointerMove(document, { buttons: 1, pointerId: 1, clientX: 20 });

    expect(within(filters()).getByText('39,00 €')).toBeInTheDocument();
    expect(location(router)).toBe('/products?category=audio');
    expect(requests).toHaveLength(1);

    fireEvent.pointerUp(document, { pointerId: 1, clientX: 20 });

    expect(location(router)).toBe('/products?category=audio&min_price=3900');
    await waitFor(() =>
      expect(requests.at(-1)).toBe('sort=newest&page=1&per_page=12&category=audio&min_price=3900'),
    );
  });

  it('hides the price slider for a category without a price range', async () => {
    serveProducts(() =>
      HttpResponse.json(buildProductPage([], { price_range: { min: null, max: null } })),
    );

    renderApp('/products?category=audio');

    await screen.findByText('0 prodotti');
    expect(within(filters()).queryByRole('slider')).not.toBeInTheDocument();
  });

  it('suggests clearing the filters when nothing matches', async () => {
    const user = userEvent.setup();
    serveProducts((params) =>
      HttpResponse.json(buildProductPage(params.has('min_price') ? [] : audioProducts())),
    );
    const { router } = renderApp(
      '/products?category=audio&min_price=130000&in_stock=1&sort=price_desc',
    );

    expect(
      await screen.findByRole('heading', { name: 'Nessun prodotto corrisponde ai filtri' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Prova ad ampliare la fascia di prezzo o a disattivare «Solo disponibili».'),
    ).toBeInTheDocument();

    const main = screen.getByRole('main');
    await user.click(within(main).getAllByRole('button', { name: 'Azzera filtri' }).at(-1));

    expect(location(router)).toBe('/products?sort=price_desc');
    expect(await screen.findByRole('list', { name: 'Prodotti' })).toBeInTheDocument();
  });

  it('shows skeletons while the products load', async () => {
    serveProducts(async () => {
      await new Promise((resolve) => setTimeout(resolve, 50));
      return HttpResponse.json(buildProductPage(audioProducts()));
    });

    renderApp('/products');

    expect(await screen.findByLabelText('Prodotti')).toHaveAttribute('aria-busy', 'true');
    expect(await screen.findByRole('list', { name: 'Prodotti' })).toBeInTheDocument();
  });

  it('retries after an error', async () => {
    const user = userEvent.setup();
    let calls = 0;
    serveProducts(() => {
      calls += 1;
      return calls === 1
        ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
        : HttpResponse.json(buildProductPage(audioProducts()));
    });

    renderApp('/products');

    expect(
      await screen.findByText('Non è stato possibile caricare i prodotti'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(await screen.findByRole('list', { name: 'Prodotti' })).toBeInTheDocument();
  });
});

describe('CatalogPage with a missing category', () => {
  async function expectCategoryNotFound() {
    expect(
      await screen.findByRole('heading', { level: 1, name: 'Questa categoria non esiste' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText("Forse il link è vecchio, oppure l'indirizzo ha un errore."),
    ).toBeInTheDocument();
    const breadcrumb = screen.getByRole('navigation', { name: 'Percorso' });
    expect(within(breadcrumb).getByText('Categoria non trovata')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Vedi tutti i prodotti' })).toHaveAttribute(
      'href',
      '/products',
    );
    expect(screen.queryByRole('complementary', { name: 'Filtri' })).not.toBeInTheDocument();
    expect(screen.queryByRole('combobox', { name: 'Ordina per' })).not.toBeInTheDocument();
  }

  it('shows the missing category state when the api rejects the category', async () => {
    serveProducts(() =>
      HttpResponse.json(
        {
          message: 'Questa categoria non esiste.',
          errors: { category: ['Questa categoria non esiste.'] },
        },
        { status: 422 },
      ),
    );

    renderApp('/products?category=giardino');

    await expectCategoryNotFound();
  });

  it('shows the same state for a category hidden from the shop', async () => {
    serveProducts(() =>
      HttpResponse.json(buildProductPage([], { price_range: { min: null, max: null } })),
    );

    renderApp('/products?category=viaggio');

    await expectCategoryNotFound();
  });

  it('falls back to the category of the products when the categories fail to load', async () => {
    server.use(
      http.get(apiUrl('/api/v1/categories'), () =>
        HttpResponse.json({ message: 'Errore.' }, { status: 500 }),
      ),
    );
    serveProducts(() =>
      HttpResponse.json(
        buildProductPage([buildProduct({ category: { name: 'Casa smart', slug: 'casa-smart' } })]),
      ),
    );

    renderApp('/products?category=casa-smart');

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Casa smart' }),
    ).toBeInTheDocument();
  });

  it('lists a sixth category among the filters', async () => {
    server.use(
      http.get(apiUrl('/api/v1/categories'), () =>
        HttpResponse.json({
          data: [...buildCategories(), buildCategory({ name: 'Viaggio', slug: 'viaggio' })],
        }),
      ),
    );
    serveProducts();

    renderApp('/products?category=viaggio');

    expect(await screen.findByRole('heading', { level: 1, name: 'Viaggio' })).toBeInTheDocument();
    expect(within(filters()).getByRole('button', { name: 'Viaggio' })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
  });
});

describe('CatalogPage on mobile', () => {
  it('opens the filters in a drawer with the active count and the result count', async () => {
    const user = userEvent.setup();
    setViewportWidth(MOBILE_WIDTH);
    serveProducts((params) =>
      HttpResponse.json(
        buildProductPage(params.has('in_stock') ? audioProducts().slice(0, 2) : audioProducts()),
      ),
    );
    const { router } = renderApp('/products?category=audio');

    expect(screen.queryByRole('complementary', { name: 'Filtri' })).not.toBeInTheDocument();
    await user.click(await screen.findByRole('button', { name: 'Filtri (1)' }));

    const drawer = await screen.findByRole('dialog', { name: 'Filtri' });
    expect(within(drawer).getByRole('button', { name: 'Audio' })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    expect(
      await within(drawer).findByRole('button', { name: 'Mostra 3 prodotti' }),
    ).toBeInTheDocument();

    await user.click(within(drawer).getByRole('switch', { name: 'Solo disponibili' }));

    expect(location(router)).toBe('/products?category=audio&in_stock=1');
    await user.click(await within(drawer).findByRole('button', { name: 'Mostra 2 prodotti' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(screen.getByRole('button', { name: 'Filtri (2)' })).toBeInTheDocument();
  });

  it('clears the filters from the drawer', async () => {
    const user = userEvent.setup();
    setViewportWidth(MOBILE_WIDTH);
    serveProducts();
    const { router } = renderApp('/products?category=audio&in_stock=1');

    await user.click(await screen.findByRole('button', { name: 'Filtri (2)' }));
    await user.click(
      within(await screen.findByRole('dialog')).getByRole('button', { name: 'Azzera' }),
    );

    expect(location(router)).toBe('/products');
  });
});
