import { screen, within } from '@testing-library/react';
import { delay, http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import DashboardPage from '@/features/admin/dashboard/pages/DashboardPage';
import { buildAdminSettings, buildDashboard } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';
import { renderAdminPage } from '@/test/renderAdminPage';

const DELETED_CUSTOMER_ORDER = {
  id: 12,
  number: 'ORD-2026-000012',
  customer: { name: 'Cliente eliminato', email: null },
  created_at: '2026-09-03T11:20:00+02:00',
  total: 8800,
  status: { value: 'delivered', label: 'Consegnato' },
};

function serveDashboard(overrides = {}) {
  server.use(
    http.get(apiUrl('/api/v1/admin/dashboard'), () =>
      HttpResponse.json({ data: buildDashboard(overrides) }),
    ),
  );
}

function renderDashboard() {
  return renderAdminPage(<DashboardPage />, { path: '/admin' });
}

describe('DashboardPage', () => {
  it('shows the indicators of the month with the threshold from the admin settings', async () => {
    serveDashboard();
    server.use(
      http.get(apiUrl('/api/v1/admin/settings'), () =>
        HttpResponse.json({ data: buildAdminSettings({ low_stock_threshold: 8 }) }),
      ),
    );

    renderDashboard();

    expect(await screen.findByText(/^8\.742,00\s€$/)).toBeInTheDocument();
    expect(
      screen.getByText('Settembre 2026 · solo ordini pagati, esclusi gli annullati'),
    ).toBeInTheDocument();
    expect(screen.getByText('Settembre 2026 · tutti gli stati')).toBeInTheDocument();
    expect(screen.getByText('24')).toBeInTheDocument();
    expect(await screen.findByText('8 pezzi o meno, esauriti compresi')).toBeInTheDocument();
    expect(screen.getByText('8 pezzi o meno')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Ordini da spedire/ })).toHaveAttribute(
      'href',
      '/admin/orders?status=paid',
    );
  });

  it('links every state of the month to the orders filtered by state and month of payment', async () => {
    serveDashboard();

    renderDashboard();

    expect(
      await screen.findByRole('link', { name: 'Pagato: 6 ordini pagati a Settembre 2026' }),
    ).toHaveAttribute('href', '/admin/orders?status=paid&paid_month=2026-09');
    expect(
      screen.getByRole('link', { name: 'Annullato: 2 ordini pagati a Settembre 2026' }),
    ).toHaveAttribute('href', '/admin/orders?status=cancelled&paid_month=2026-09');
    expect(screen.queryByText('In attesa')).not.toBeInTheDocument();
  });

  it('shows the latest orders and a deleted customer without email', async () => {
    serveDashboard({
      latest_orders: [buildDashboard().latest_orders[0], DELETED_CUSTOMER_ORDER],
    });

    renderDashboard();

    const table = await screen.findByRole('table', { name: 'Ultimi ordini' });
    const rows = within(table).getAllByRole('row');
    expect(within(rows[1]).getByRole('link', { name: 'ORD-2026-000047' })).toHaveAttribute(
      'href',
      '/admin/orders/47',
    );
    expect(within(rows[1]).getByText('Marco Bellini')).toBeInTheDocument();
    expect(within(rows[1]).getByText('29 set 2026, 15:10')).toBeInTheDocument();
    expect(within(rows[2]).getByText('Cliente eliminato')).toBeInTheDocument();
    expect(within(table).queryByText('marco.bellini@esempio.it')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Vedi tutti' })).toHaveAttribute(
      'href',
      '/admin/orders',
    );
  });

  it('links every low stock product to its form', async () => {
    serveDashboard();

    renderDashboard();

    const list = await screen.findByRole('list', { name: 'Prodotti con scorte basse' });
    expect(within(list).getByText('Radio Onda Portatile')).toBeInTheDocument();
    expect(within(list).getByText('Esaurito')).toBeInTheDocument();
    expect(
      within(list).getByRole('link', { name: 'Modifica Radio Onda Portatile' }),
    ).toHaveAttribute('href', '/admin/products/31/edit');
  });

  it('explains the empty lists', async () => {
    serveDashboard({ latest_orders: [], low_stock_products: [] });

    renderDashboard();

    expect(await screen.findByText('Non ci sono ancora ordini pagati.')).toBeInTheDocument();
    expect(screen.getByText('Nessun prodotto sotto la soglia.')).toBeInTheDocument();
  });

  it('shows the shorter captions and lists on mobile', async () => {
    setViewportWidth(MOBILE_WIDTH);
    serveDashboard({ latest_orders: [DELETED_CUSTOMER_ORDER] });

    renderDashboard();

    expect(
      await screen.findByText('Solo ordini pagati, esclusi gli annullati'),
    ).toBeInTheDocument();
    expect(screen.getByText('Pagati, da spedire')).toBeInTheDocument();
    expect(screen.getByText('Scorte basse', { selector: 'p' })).toBeInTheDocument();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
    const order = screen.getByRole('link', { name: 'Ordine ORD-2026-000012' });
    expect(within(order).getByText('Cliente eliminato · 3 set, 11:20')).toBeInTheDocument();
  });

  it('offers to retry when the dashboard does not load', async () => {
    server.use(
      http.get(apiUrl('/api/v1/admin/dashboard'), () => new HttpResponse(null, { status: 500 })),
    );

    renderDashboard();

    expect(
      await screen.findByText('Non è stato possibile caricare la dashboard'),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Riprova' })).toBeInTheDocument();
  });

  it('shows the skeleton while the dashboard loads', () => {
    server.use(http.get(apiUrl('/api/v1/admin/dashboard'), () => delay('infinite')));

    renderDashboard();

    expect(screen.getByRole('status', { name: 'Caricamento della dashboard' })).toBeInTheDocument();
  });
});
