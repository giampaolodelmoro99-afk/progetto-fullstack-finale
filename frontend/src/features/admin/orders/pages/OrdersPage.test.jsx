import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { delay, http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import OrdersPage from '@/features/admin/orders/pages/OrdersPage';
import { buildAdminOrderPage, buildAdminOrderSummary, buildAdminSettings } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';
import { renderAdminPage } from '@/test/renderAdminPage';

const PAID = buildAdminOrderSummary();
const FAILED = buildAdminOrderSummary({
  id: 48,
  number: 'ORD-2026-000048',
  customer: { name: 'Chiara Russo', email: 'chiara.russo@esempio.it' },
  created_at: '2026-09-29T16:52:00+02:00',
  items_count: 1,
  total: 21900,
  status: { value: 'cancelled', label: 'Annullato' },
  failed_payment: true,
});
const DELETED_CUSTOMER = buildAdminOrderSummary({
  id: 12,
  number: 'ORD-2026-000012',
  customer: { name: 'Cliente eliminato', email: null },
  status: { value: 'delivered', label: 'Consegnato' },
});

function serveOrders(respond = () => HttpResponse.json(buildAdminOrderPage([PAID]))) {
  const requests = [];
  server.use(
    http.get(apiUrl('/api/v1/admin/orders'), ({ request }) => {
      const params = new URL(request.url).searchParams;
      requests.push(params.toString());
      return respond(params);
    }),
  );

  return requests;
}

function renderOrders(route = '/admin/orders') {
  return renderAdminPage(<OrdersPage />, { path: '/admin/orders', route });
}

describe('OrdersPage', () => {
  it('shows customer, date, items, total and state of every order', async () => {
    serveOrders(() =>
      HttpResponse.json(buildAdminOrderPage([PAID, FAILED, DELETED_CUSTOMER], { total: 48 })),
    );

    renderOrders();

    await screen.findByText('Chiara Russo');
    const rows = within(screen.getByRole('table', { name: 'Ordini' })).getAllByRole('row');
    const paid = within(rows[1]);
    expect(paid.getByRole('link', { name: 'ORD-2026-000042' })).toHaveAttribute(
      'href',
      '/admin/orders/42',
    );
    expect(paid.getByText('giulia.ferri@esempio.it')).toBeInTheDocument();
    expect(paid.getByText('28 set 2026, 18:41')).toBeInTheDocument();
    expect(paid.getByText('4')).toBeInTheDocument();
    expect(paid.getByText(/^496,00\s€$/)).toBeInTheDocument();
    expect(paid.getByText('Pagato')).toBeInTheDocument();
    expect(
      paid.getByRole('link', { name: "Dettagli dell'ordine ORD-2026-000042" }),
    ).toHaveAttribute('href', '/admin/orders/42');
    expect(within(rows[2]).getByText('Annullato')).toBeInTheDocument();
    expect(within(rows[2]).getByText('Pagamento non riuscito')).toBeInTheDocument();
    expect(within(rows[3]).getByText('Cliente eliminato')).toBeInTheDocument();
    expect(rows[3].textContent).not.toContain('@');
    expect(screen.getByText('48 ordini')).toBeInTheDocument();
  });

  it('says «1 ordine» for a single result', async () => {
    serveOrders();

    renderOrders();

    expect(await screen.findByText('1 ordine')).toBeInTheDocument();
  });

  it('offers the states received from the admin settings', async () => {
    const user = userEvent.setup();
    serveOrders();
    server.use(
      http.get(apiUrl('/api/v1/admin/settings'), () =>
        HttpResponse.json({
          data: buildAdminSettings({
            order_statuses: [
              { value: 'paid', label: 'Pagato (test)' },
              { value: 'shipped', label: 'Spedito (test)' },
            ],
          }),
        }),
      ),
    );

    const { router } = renderOrders('/admin/orders?page=2');

    await screen.findByText('ORD-2026-000042');
    await user.click(screen.getByRole('combobox', { name: 'Stato' }));
    const options = screen.getAllByRole('option').map((option) => option.textContent);
    expect(options).toEqual(['Tutti', 'Pagato (test)', 'Spedito (test)']);
    await user.click(screen.getByRole('option', { name: 'Spedito (test)' }));

    expect(router.state.location.search).toBe('?status=shipped');
  });

  it('filters the failed payments with the switch', async () => {
    const user = userEvent.setup();
    const requests = serveOrders();

    const { router } = renderOrders();

    await screen.findByText('ORD-2026-000042');
    await user.click(screen.getByRole('switch', { name: 'Solo pagamenti non riusciti' }));

    expect(router.state.location.search).toBe('?failed_payment=1');
    expect(requests).toContain('failed_payment=1');
  });

  it('shows the month of payment from the dashboard as a filter that can be removed', async () => {
    const user = userEvent.setup();
    const requests = serveOrders((params) =>
      HttpResponse.json(
        buildAdminOrderPage([PAID], {
          current_page: Number(params.get('page') ?? 1),
          last_page: 3,
          total: 25,
        }),
      ),
    );

    const { router } = renderOrders('/admin/orders?status=paid&paid_month=2026-09&page=2');

    expect(await screen.findByText('Pagati a Settembre 2026')).toBeInTheDocument();
    expect(requests[0]).toBe('status=paid&paid_month=2026-09&page=2');

    await user.click(screen.getByRole('img', { name: 'Rimuovi il filtro del mese' }));

    expect(router.state.location.search).toBe('?status=paid');
    expect(screen.queryByText('Pagati a Settembre 2026')).not.toBeInTheDocument();
  });

  it('cites the search in the empty state and clears the month too', async () => {
    const user = userEvent.setup();
    serveOrders((params) =>
      HttpResponse.json(buildAdminOrderPage(params.get('search') ? [] : [PAID])),
    );

    const { router } = renderOrders(
      '/admin/orders?search=mario.rossi%40esempio.it&paid_month=2026-09',
    );

    expect(await screen.findByText('Nessun ordine trovato')).toBeInTheDocument();
    expect(
      screen.getByText(
        "Nessun ordine corrisponde a «mario.rossi@esempio.it». Controlla il numero d'ordine o l'email, oppure azzera i filtri.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByText('0 ordini')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Azzera filtri' }));

    expect(router.state.location.search).toBe('');
  });

  it('shows the orders as cards on mobile with the failed payment', async () => {
    setViewportWidth(MOBILE_WIDTH);
    serveOrders(() =>
      HttpResponse.json(
        buildAdminOrderPage([FAILED, DELETED_CUSTOMER], {
          total: 48,
          from: 1,
          to: 10,
          last_page: 5,
        }),
      ),
    );

    renderOrders();

    const failed = await screen.findByRole('link', { name: 'Ordine ORD-2026-000048' });
    expect(within(failed).getByText('Chiara Russo · chiara.russo@esempio.it')).toBeInTheDocument();
    expect(within(failed).getByText('29 set, 16:52 · 1 articolo')).toBeInTheDocument();
    expect(within(failed).getByText('Pagamento non riuscito')).toBeInTheDocument();
    const deleted = screen.getByRole('link', { name: 'Ordine ORD-2026-000012' });
    expect(within(deleted).getByText('Cliente eliminato')).toBeInTheDocument();
    expect(screen.getByText('1–10 di 48')).toBeInTheDocument();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });

  it('offers to retry when the orders do not load', async () => {
    serveOrders(() => new HttpResponse(null, { status: 500 }));

    renderOrders();

    expect(
      await screen.findByText('Non è stato possibile caricare gli ordini'),
    ).toBeInTheDocument();
  });

  it('shows ten skeleton rows under the headers while the list loads', () => {
    serveOrders(() => delay('infinite'));

    renderOrders();

    const table = screen.getByRole('table', { name: 'Ordini' });
    expect(within(table).getByRole('columnheader', { name: 'Cliente' })).toBeInTheDocument();
    expect(within(table).getAllByRole('row')).toHaveLength(11);
  });

  it('shows the skeleton cards while the list loads on mobile', () => {
    setViewportWidth(MOBILE_WIDTH);
    serveOrders(() => delay('infinite'));

    renderOrders();

    expect(screen.getByRole('status', { name: 'Caricamento degli ordini' })).toBeInTheDocument();
  });
});
