import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderApp } from '@/test/authHelpers';
import { buildOrder, buildOrderPage, buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';

const DELIVERED = buildOrder({
  id: 24,
  number: 'ORD-2026-000024',
  status: { value: 'delivered', label: 'Consegnato' },
  created_at: '2026-09-02T10:11:00+02:00',
  paid_at: '2026-09-02T10:12:00+02:00',
  total: 8800,
  items_count: 1,
  can_cancel: false,
});

function serveOrders(respond) {
  const pages = [];
  server.use(
    http.get(apiUrl('/api/v1/orders'), ({ request }) => {
      pages.push(new URL(request.url).searchParams.get('page'));
      return respond(new URL(request.url).searchParams.get('page'));
    }),
  );

  return pages;
}

function renderOrders(route = '/account/orders') {
  return renderApp(route, { user: buildUser() });
}

describe('OrdersPage', () => {
  it('shows the orders in a table on desktop', async () => {
    serveOrders(() => HttpResponse.json(buildOrderPage([buildOrder(), DELIVERED])));

    renderOrders();

    const table = await screen.findByRole('table', { name: 'I miei ordini' });
    const rows = within(table).getAllByRole('row');
    const paid = within(rows[1]);
    expect(paid.getByText('ORD-2026-000042')).toBeInTheDocument();
    expect(paid.getByText('28 settembre 2026')).toBeInTheDocument();
    expect(paid.getByText('4 articoli')).toBeInTheDocument();
    expect(paid.getByText(/^496,00\s€$/)).toBeInTheDocument();
    expect(paid.getByText('Pagato')).toBeInTheDocument();
    expect(
      paid.getByRole('link', { name: "Dettagli dell'ordine ORD-2026-000042" }),
    ).toHaveAttribute('href', '/account/orders/42');
    const delivered = within(rows[2]);
    expect(delivered.getByText('1 articolo')).toBeInTheDocument();
    expect(delivered.getByText('Consegnato')).toBeInTheDocument();
  });

  it('shows the orders as cards on mobile', async () => {
    setViewportWidth(MOBILE_WIDTH);
    serveOrders(() => HttpResponse.json(buildOrderPage([DELIVERED])));

    renderOrders();

    const link = await screen.findByRole('link', { name: 'Ordine ORD-2026-000024' });
    expect(link).toHaveAttribute('href', '/account/orders/24');
    expect(within(link).getByText('Consegnato')).toBeInTheDocument();
    expect(within(link).getByText('2 settembre 2026 · 1 articolo')).toBeInTheDocument();
    expect(within(link).getByText(/^88,00\s€$/)).toBeInTheDocument();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });

  it('moves between pages through the query string', async () => {
    const user = userEvent.setup();
    const pages = serveOrders((page) =>
      HttpResponse.json(
        page === '2'
          ? buildOrderPage([DELIVERED], { current_page: 2, last_page: 2, total: 11 })
          : buildOrderPage([buildOrder()], { last_page: 2, total: 11 }),
      ),
    );

    const { router } = renderOrders();

    expect(await screen.findByText('ORD-2026-000042')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Vai alla pagina 2' }));

    expect(await screen.findByText('ORD-2026-000024')).toBeInTheDocument();
    expect(router.state.location.search).toBe('?page=2');
    expect(pages).toEqual([null, '2']);
  });

  it('reads the page from the query string', async () => {
    const pages = serveOrders(() =>
      HttpResponse.json(buildOrderPage([DELIVERED], { current_page: 2, last_page: 2, total: 11 })),
    );

    renderOrders('/account/orders?page=2');

    expect(await screen.findByText('ORD-2026-000024')).toBeInTheDocument();
    expect(pages).toEqual(['2']);
  });

  it('goes back to the last page from a page that no longer exists', async () => {
    const pages = serveOrders((page) =>
      HttpResponse.json(
        page === '2'
          ? buildOrderPage([DELIVERED], { current_page: 2, last_page: 2, total: 11 })
          : buildOrderPage([], { current_page: Number(page), last_page: 2, total: 11 }),
      ),
    );

    const { router } = renderOrders('/account/orders?page=5');

    expect(await screen.findByText('ORD-2026-000024')).toBeInTheDocument();
    expect(router.state.location.search).toBe('?page=2');
    expect(router.state.historyAction).toBe('REPLACE');
    expect(pages).toEqual(['5', '2']);
    expect(screen.queryByText('Non hai ancora fatto ordini')).not.toBeInTheDocument();
  });

  it('hides the pagination with a single page', async () => {
    serveOrders(() => HttpResponse.json(buildOrderPage([buildOrder()])));

    renderOrders();

    expect(await screen.findByText('ORD-2026-000042')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /pagina/ })).not.toBeInTheDocument();
  });

  it('invites to discover the collection without orders', async () => {
    serveOrders(() => HttpResponse.json(buildOrderPage([])));

    renderOrders();

    expect(await screen.findByText('Non hai ancora fatto ordini')).toBeInTheDocument();
    expect(
      screen.getByText(
        'Quando acquisterai qualcosa, qui troverai lo stato e i dettagli di ogni ordine.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Scopri la collezione' })).toHaveAttribute(
      'href',
      '/products',
    );
  });

  it('shows the shape of the orders while loading', async () => {
    serveOrders(async () => {
      await new Promise((resolve) => setTimeout(resolve, 50));
      return HttpResponse.json(buildOrderPage([buildOrder()]));
    });

    renderOrders();

    expect(
      await screen.findByRole('status', { name: 'Caricamento degli ordini' }),
    ).toBeInTheDocument();
    expect(await screen.findByText('ORD-2026-000042')).toBeInTheDocument();
  });

  it('shows an error with retry', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    serveOrders(() => {
      attempts += 1;
      return attempts === 1
        ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
        : HttpResponse.json(buildOrderPage([buildOrder()]));
    });

    renderOrders();

    expect(
      await screen.findByText('Non è stato possibile caricare i tuoi ordini'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    await waitFor(() => expect(screen.getByText('ORD-2026-000042')).toBeInTheDocument());
  });
});
