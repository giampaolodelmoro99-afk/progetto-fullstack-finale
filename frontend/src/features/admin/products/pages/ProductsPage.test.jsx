import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { delay, http, HttpResponse } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ProductsPage from '@/features/admin/products/pages/ProductsPage';
import { buildAdminProduct, buildAdminProductPage } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';
import { renderAdminPage } from '@/test/renderAdminPage';

const ARIA = buildAdminProduct({ id: 1, name: 'Cuffie Aria', sku: 'CDS-AUD-001' });
const SPORT = buildAdminProduct({
  id: 2,
  name: 'Auricolari Sussurro Sport',
  sku: 'CDS-AUD-011',
  price: 23900,
  stock: 4,
  stock_status: { status: 'low_stock', label: 'Ultimi 4 pezzi' },
});
const RADIO = buildAdminProduct({
  id: 3,
  name: 'Radio Onda Portatile',
  sku: 'CDS-AUD-031',
  price: 124900,
  stock: 0,
  stock_status: { status: 'out_of_stock', label: 'Esaurito' },
  is_active: false,
});

function serveProducts(respond = () => HttpResponse.json(buildAdminProductPage([ARIA]))) {
  const requests = [];
  server.use(
    http.get(apiUrl('/api/v1/admin/products'), ({ request }) => {
      const params = new URL(request.url).searchParams;
      requests.push(params.toString());
      return respond(params);
    }),
  );

  return requests;
}

function renderProducts(route = '/admin/products') {
  return renderAdminPage(<ProductsPage />, { path: '/admin/products', route });
}

describe('ProductsPage', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('shows price, stock and state of every product', async () => {
    serveProducts(() => HttpResponse.json(buildAdminProductPage([ARIA, SPORT, RADIO])));

    renderProducts();

    await screen.findByText('Radio Onda Portatile');
    const table = screen.getByRole('table', { name: 'Prodotti' });
    const rows = within(table).getAllByRole('row');
    const aria = within(rows[1]);
    expect(aria.getByText('Cuffie Aria')).toBeInTheDocument();
    expect(aria.getByText('CDS-AUD-001')).toBeInTheDocument();
    expect(aria.getByText('Audio')).toBeInTheDocument();
    expect(aria.getByText(/^349,00\s€$/)).toBeInTheDocument();
    expect(aria.getByText('18')).toBeInTheDocument();
    expect(aria.getByText('Attivo')).toBeInTheDocument();
    expect(aria.getByRole('link', { name: 'Modifica Cuffie Aria' })).toHaveAttribute(
      'href',
      '/admin/products/1/edit',
    );
    expect(rows[2]).toHaveTextContent('4, scorte basse');
    const radio = within(rows[3]);
    expect(radio.getByText(/^1\.249,00\s€$/)).toBeInTheDocument();
    expect(rows[3]).toHaveTextContent('Esaurito, scorte basse');
    expect(radio.getByText('Disattivato')).toBeInTheDocument();
  });

  it('reads the filters from the query string and sends them to the API', async () => {
    const requests = serveProducts(() =>
      HttpResponse.json(
        buildAdminProductPage([ARIA], { current_page: 2, last_page: 2, total: 26 }),
      ),
    );

    renderProducts('/admin/products?category=audio&status=inactive&page=2&per_page=25');

    expect(await screen.findByText('Cuffie Aria')).toBeInTheDocument();
    expect(requests).toEqual(['category=audio&status=inactive&page=2&per_page=25']);
    expect(screen.getByRole('combobox', { name: 'Stato' })).toHaveTextContent('Disattivato');
  });

  it('writes a filter in the query string and goes back to the first page', async () => {
    const user = userEvent.setup();
    serveProducts();

    const { router } = renderProducts('/admin/products?page=3');

    await screen.findByText('Cuffie Aria');
    await user.click(screen.getByRole('combobox', { name: 'Stato' }));
    await user.click(screen.getByRole('option', { name: 'Attivo' }));

    expect(router.state.location.search).toBe('?status=active');
    await user.click(screen.getByRole('combobox', { name: 'Categoria' }));
    await user.click(await screen.findByRole('option', { name: 'Viaggio' }));
    expect(router.state.location.search).toBe('?category=viaggio&status=active');
  });

  it('searches after a pause and clears the search at once with the X', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
    serveProducts();

    const { router } = renderProducts();

    await screen.findByText('Cuffie Aria');
    await user.type(screen.getByRole('textbox', { name: 'Cerca per nome o SKU' }), 'aria');
    expect(router.state.location.search).toBe('');

    await act(async () => vi.advanceTimersByTime(300));
    await waitFor(() => expect(router.state.location.search).toBe('?search=aria'));

    await user.click(screen.getByRole('button', { name: 'Cancella la ricerca' }));
    expect(router.state.location.search).toBe('');
    expect(screen.getByRole('textbox', { name: 'Cerca per nome o SKU' })).toHaveValue('');
  });

  it('changes page and rows per page', async () => {
    const user = userEvent.setup();
    serveProducts((params) =>
      HttpResponse.json(
        buildAdminProductPage([params.get('page') === '2' ? SPORT : ARIA], {
          current_page: Number(params.get('page') ?? 1),
          last_page: 5,
          total: 42,
          from: 1,
          to: 10,
        }),
      ),
    );

    const { router } = renderProducts();

    await screen.findByText('Cuffie Aria');
    await user.click(screen.getByRole('button', { name: 'Vai alla pagina successiva' }));
    expect(await screen.findByText('Auricolari Sussurro Sport')).toBeInTheDocument();
    expect(router.state.location.search).toBe('?page=2');

    await user.click(screen.getByRole('combobox', { name: 'Righe per pagina' }));
    await user.click(screen.getByRole('option', { name: '25' }));
    expect(router.state.location.search).toBe('?per_page=25');
  });

  it('cites the search in the empty state and clears every filter', async () => {
    const user = userEvent.setup();
    serveProducts((params) =>
      HttpResponse.json(buildAdminProductPage(params.get('search') ? [] : [ARIA])),
    );

    const { router } = renderProducts('/admin/products?search=Cuffie%20Luna&category=audio');

    expect(await screen.findByText('Nessun prodotto trovato')).toBeInTheDocument();
    expect(
      screen.getByText(
        'Nessun prodotto corrisponde a «Cuffie Luna». Controlla il nome o lo SKU, oppure azzera i filtri.',
      ),
    ).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Azzera filtri' }));

    expect(router.state.location.search).toBe('');
    expect(await screen.findByText('Cuffie Aria')).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Cerca per nome o SKU' })).toHaveValue('');
  });

  it('explains an empty result without a search', async () => {
    serveProducts(() => HttpResponse.json(buildAdminProductPage([])));

    renderProducts('/admin/products?status=inactive');

    expect(await screen.findByText('Nessun prodotto corrisponde ai filtri.')).toBeInTheDocument();
  });

  it('deletes a product after the confirmation', async () => {
    const user = userEvent.setup();
    let deleted = false;
    serveProducts(() => HttpResponse.json(buildAdminProductPage([RADIO])));
    server.use(
      http.delete(apiUrl('/api/v1/admin/products/3'), () => {
        deleted = true;
        return new HttpResponse(null, { status: 204 });
      }),
    );

    renderProducts();

    await user.click(await screen.findByRole('button', { name: 'Elimina Radio Onda Portatile' }));
    const dialog = screen.getByRole('dialog', { name: 'Eliminare «Radio Onda Portatile»?' });
    expect(
      within(dialog).getByText(
        "Il prodotto sparisce dal catalogo e dal negozio. Gli ordini già ricevuti che lo contengono restano invariati. L'operazione non si può annullare.",
      ),
    ).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Elimina il prodotto' }));

    expect(await screen.findByText('Prodotto eliminato')).toBeInTheDocument();
    expect(deleted).toBe(true);
  });

  it('keeps the product with «Mantieni il prodotto»', async () => {
    const user = userEvent.setup();
    serveProducts();

    renderProducts();

    await user.click(await screen.findByRole('button', { name: 'Elimina Cuffie Aria' }));
    await user.click(screen.getByRole('button', { name: 'Mantieni il prodotto' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  it('shows cards and the mobile pagination on a small screen', async () => {
    setViewportWidth(MOBILE_WIDTH);
    serveProducts(() =>
      HttpResponse.json(
        buildAdminProductPage([SPORT], { last_page: 5, total: 42, from: 1, to: 10 }),
      ),
    );

    renderProducts();

    const list = await screen.findByRole('list', { name: 'Prodotti' });
    expect(within(list).getByText('Auricolari Sussurro Sport')).toBeInTheDocument();
    expect(within(list).getByText('CDS-AUD-011 · Audio')).toBeInTheDocument();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Precedenti' })).toBeDisabled();
    expect(screen.getByText('1–10 di 42')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Successivi' })).toBeEnabled();
  });

  it('offers to retry when the list does not load', async () => {
    serveProducts(() => new HttpResponse(null, { status: 500 }));

    renderProducts();

    expect(
      await screen.findByText('Non è stato possibile caricare i prodotti'),
    ).toBeInTheDocument();
  });

  it('goes back to the last page from a page that no longer exists', async () => {
    serveProducts((params) =>
      HttpResponse.json(
        buildAdminProductPage(params.get('page') === '9' ? [] : [ARIA], {
          current_page: Number(params.get('page') ?? 1),
          last_page: 2,
          total: 11,
        }),
      ),
    );

    const { router } = renderProducts('/admin/products?page=9');

    await waitFor(() => expect(router.state.location.search).toBe('?page=2'));
    expect(router.state.historyAction).toBe('REPLACE');
  });

  it('shows ten skeleton rows under the headers while the list loads', () => {
    serveProducts(() => delay('infinite'));

    renderProducts();

    const table = screen.getByRole('table', { name: 'Prodotti' });
    expect(within(table).getByRole('columnheader', { name: 'Nome' })).toBeInTheDocument();
    expect(within(table).getAllByRole('row')).toHaveLength(11);
  });

  it('shows the skeleton cards while the list loads on mobile', () => {
    setViewportWidth(MOBILE_WIDTH);
    serveProducts(() => delay('infinite'));

    renderProducts();

    expect(screen.getByRole('status', { name: 'Caricamento dei prodotti' })).toBeInTheDocument();
  });
});
