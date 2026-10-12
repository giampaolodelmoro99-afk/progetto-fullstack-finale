import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { delay, http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { queryKeys } from '@/api/queryKeys';
import OrderPage from '@/features/admin/orders/pages/OrderPage';
import { domainError } from '@/test/authHelpers';
import { buildAdminOrder } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { renderAdminPage } from '@/test/renderAdminPage';

const SHIPPED_ROW = {
  status: { value: 'shipped', label: 'Spedito' },
  description: 'Segnato come spedito da Marta Lenzi',
  changed_by: { id: 1, name: 'Marta Lenzi' },
  reason: null,
  created_at: '2026-09-29T09:30:00+02:00',
};

const FAILED = buildAdminOrder({
  id: 48,
  number: 'ORD-2026-000048',
  status: { value: 'cancelled', label: 'Annullato' },
  paid_at: null,
  failed_payment: true,
  can_cancel: false,
  allowed_transitions: [],
  payment: {
    card_last_four: '0002',
    status: { value: 'failed', label: 'Non riuscito' },
    amount: 21900,
    created_at: '2026-09-29T16:53:00+02:00',
  },
  status_history: [
    {
      status: { value: 'pending', label: 'In attesa' },
      description: 'Ordine ricevuto, in attesa del pagamento',
      changed_by: null,
      reason: null,
      created_at: '2026-09-29T16:52:00+02:00',
    },
    {
      status: { value: 'cancelled', label: 'Annullato' },
      description: 'Pagamento non riuscito: Carta rifiutata dalla banca',
      changed_by: null,
      reason: 'Carta rifiutata dalla banca',
      created_at: '2026-09-29T16:53:00+02:00',
    },
  ],
});

function serveOrder(order) {
  server.use(
    http.get(apiUrl(`/api/v1/admin/orders/${order.id}`), () => HttpResponse.json({ data: order })),
  );
}

function recordStatusChange(orderId, respond) {
  const bodies = [];
  server.use(
    http.patch(apiUrl(`/api/v1/admin/orders/${orderId}/status`), async ({ request }) => {
      bodies.push(await request.json());
      return respond();
    }),
  );

  return bodies;
}

function renderOrder(orderId = 42) {
  return renderAdminPage(<OrderPage />, {
    path: '/admin/orders/:orderId',
    route: `/admin/orders/${orderId}`,
  });
}

function card(title) {
  return within(screen.getByRole('region', { name: title }));
}

describe('OrderPage', () => {
  it('shows products, totals, history, customer, address and payment', async () => {
    serveOrder(buildAdminOrder());

    renderOrder();

    expect(
      await screen.findByRole('heading', { level: 2, name: 'ORD-2026-000042' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Effettuato il 28 settembre 2026 alle 18:42 · 4 articoli'),
    ).toBeInTheDocument();
    const products = card('Prodotti');
    expect(products.getByText("Prezzi al momento dell'acquisto")).toBeInTheDocument();
    expect(products.getByText('CDS-RIC-001')).toBeInTheDocument();
    expect(products.getByText(/^118,00\s€$/)).toBeInTheDocument();
    expect(products.getByText('Spedizione standard')).toBeInTheDocument();
    const history = card('Cronologia degli stati');
    expect(history.getByText('Pagamento con carta riuscito')).toBeInTheDocument();
    expect(history.getByText('28 set 2026, 18:42')).toBeInTheDocument();
    expect(card('Cliente').getByRole('link', { name: 'giulia.ferri@esempio.it' })).toHaveAttribute(
      'href',
      'mailto:giulia.ferri@esempio.it',
    );
    expect(card('Cliente').getByText('+39 333 123 4567')).toBeInTheDocument();
    expect(
      card('Indirizzo di spedizione').getByText('56125 Pisa (PI), Italia'),
    ).toBeInTheDocument();
    const payment = card('Pagamento');
    expect(payment.getByText('Carta che termina con 4242')).toBeInTheDocument();
    expect(payment.getByText('Riuscito')).toBeInTheDocument();
    expect(payment.queryByText('Motivo')).not.toBeInTheDocument();
  });

  it('shows «Cliente eliminato» without the email row', async () => {
    serveOrder(
      buildAdminOrder({
        status: { value: 'delivered', label: 'Consegnato' },
        customer: { name: 'Cliente eliminato', email: null },
        allowed_transitions: [],
      }),
    );

    renderOrder();

    const customer = await screen.findByRole('region', { name: 'Cliente' });
    expect(within(customer).getByText('Cliente eliminato')).toBeInTheDocument();
    expect(within(customer).queryByRole('link')).not.toBeInTheDocument();
    expect(within(customer).getByText('+39 333 123 4567')).toBeInTheDocument();
  });

  it('derives the actions of a paid order from the allowed transitions', async () => {
    serveOrder(buildAdminOrder());

    renderOrder();

    const actions = within(await screen.findByRole('region', { name: 'Azioni' }));
    expect(actions.getByRole('button', { name: 'Segna come spedito' })).toBeInTheDocument();
    expect(actions.getByRole('button', { name: "Annulla l'ordine" })).toBeInTheDocument();
    expect(
      actions.getByText("Dopo la spedizione l'ordine non si può più annullare."),
    ).toBeInTheDocument();
    expect(
      actions.queryByRole('button', { name: 'Segna come consegnato' }),
    ).not.toBeInTheDocument();
  });

  it('marks a paid order as shipped', async () => {
    const user = userEvent.setup();
    serveOrder(buildAdminOrder());
    const bodies = recordStatusChange(42, () =>
      HttpResponse.json({
        data: buildAdminOrder({
          status: { value: 'shipped', label: 'Spedito' },
          can_cancel: false,
          allowed_transitions: ['delivered'],
          status_history: [...buildAdminOrder().status_history, SHIPPED_ROW],
        }),
      }),
    );

    const { queryClient } = renderOrder();
    queryClient.setQueryData(queryKeys.admin.dashboard, {});

    await user.click(await screen.findByRole('button', { name: 'Segna come spedito' }));

    expect(await screen.findByText('Ordine segnato come spedito')).toBeInTheDocument();
    expect(bodies).toEqual([{ status: 'shipped' }]);
    expect(screen.getByRole('button', { name: 'Segna come consegnato' })).toBeInTheDocument();
    expect(
      screen.getByText('Un ordine spedito non si può annullare. Consegnato è lo stato finale.'),
    ).toBeInTheDocument();
    expect(screen.getByText('Segnato come spedito da Marta Lenzi')).toBeInTheDocument();
    expect(queryClient.getQueryState(queryKeys.admin.dashboard).isInvalidated).toBe(true);
  });

  it('cancels the order with the refund and the restored stock', async () => {
    const user = userEvent.setup();
    serveOrder(buildAdminOrder());
    const bodies = recordStatusChange(42, () =>
      HttpResponse.json({
        data: buildAdminOrder({
          status: { value: 'cancelled', label: 'Annullato' },
          can_cancel: false,
          allowed_transitions: [],
        }),
      }),
    );

    const { queryClient } = renderOrder();
    queryClient.setQueryData(queryKeys.admin.products.list({}), { data: [], meta: {} });

    await user.click(await screen.findByRole('button', { name: "Annulla l'ordine" }));
    const dialog = screen.getByRole('dialog', { name: "Annullare l'ordine ORD-2026-000042?" });
    expect(dialog).toHaveTextContent('Rimborso di 496,00 € sulla carta che termina con 4242.');
    expect(dialog).toHaveTextContent(
      'Scorte ripristinate: Cuffie Aria +1, Caricatore Brezza 65 W +2.',
    );
    expect(
      within(dialog).getByText("L'ordine passa ad Annullato. L'operazione non si può annullare."),
    ).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: "Annulla l'ordine" }));

    expect(await screen.findByText('Ordine annullato e rimborsato')).toBeInTheDocument();
    expect(bodies).toEqual([{ status: 'cancelled' }]);
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(screen.getByText('Nessuna azione disponibile per questo ordine.')).toBeInTheDocument();
    expect(queryClient.getQueryState(queryKeys.admin.products.list({})).isInvalidated).toBe(true);
  });

  it('keeps the dialog open when the refund fails', async () => {
    const user = userEvent.setup();
    serveOrder(buildAdminOrder());
    recordStatusChange(42, () =>
      domainError(502, 'refund_failed', 'Il rimborso non è andato a buon fine. Riprova più tardi.'),
    );

    renderOrder();

    await user.click(await screen.findByRole('button', { name: "Annulla l'ordine" }));
    await user.click(
      within(screen.getByRole('dialog')).getByRole('button', { name: "Annulla l'ordine" }),
    );

    const dialog = screen.getByRole('dialog');
    expect(
      await within(dialog).findByText('Il rimborso non è andato a buon fine. Riprova più tardi.'),
    ).toBeInTheDocument();
    expect(screen.getByText('Pagato', { selector: '.MuiChip-label' })).toBeInTheDocument();
  });

  it('reads the order again after a transition that is no longer allowed', async () => {
    const user = userEvent.setup();
    let reads = 0;
    server.use(
      http.get(apiUrl('/api/v1/admin/orders/42'), () => {
        reads += 1;
        return HttpResponse.json({
          data:
            reads === 1
              ? buildAdminOrder()
              : buildAdminOrder({
                  status: { value: 'shipped', label: 'Spedito' },
                  allowed_transitions: ['delivered'],
                }),
        });
      }),
    );
    recordStatusChange(42, () =>
      domainError(
        422,
        'invalid_order_transition',
        "Lo stato attuale dell'ordine non consente questa operazione.",
      ),
    );

    renderOrder();

    await user.click(await screen.findByRole('button', { name: 'Segna come spedito' }));

    expect(
      await screen.findByText("Lo stato attuale dell'ordine non consente questa operazione."),
    ).toBeInTheDocument();
    expect(
      await screen.findByRole('button', { name: 'Segna come consegnato' }),
    ).toBeInTheDocument();
    expect(reads).toBe(2);
  });

  it('explains a failed payment without actions', async () => {
    serveOrder(FAILED);

    renderOrder(48);

    expect(await screen.findByText('Pagamento non riuscito')).toBeInTheDocument();
    expect(
      screen.getByText(
        /Carta rifiutata dalla banca \(carta che termina con 0002\)\. Questo tentativo non è visibile al cliente/,
      ),
    ).toBeInTheDocument();
    expect(screen.getByText('Annullato', { selector: '.MuiChip-label' })).toBeInTheDocument();
    expect(screen.getByText('Nessuna azione disponibile per questo ordine.')).toBeInTheDocument();
    const payment = card('Pagamento');
    expect(payment.getByText('Non riuscito')).toBeInTheDocument();
    expect(payment.getByText('Motivo')).toBeInTheDocument();
    expect(payment.getByText('Carta rifiutata dalla banca')).toBeInTheDocument();
  });

  it('explains an order that does not exist', async () => {
    server.use(
      http.get(apiUrl('/api/v1/admin/orders/404'), () =>
        HttpResponse.json({ message: 'Risorsa non trovata.' }, { status: 404 }),
      ),
    );

    renderOrder(404);

    expect(await screen.findByText('Non abbiamo trovato questo ordine')).toBeInTheDocument();
  });

  it('shows the skeleton while the order loads', () => {
    server.use(http.get(apiUrl('/api/v1/admin/orders/42'), () => delay('infinite')));

    renderOrder();

    expect(screen.getByRole('status', { name: "Caricamento dell'ordine" })).toBeInTheDocument();
  });

  it('offers to retry when the order does not load', async () => {
    const user = userEvent.setup();
    let calls = 0;
    server.use(
      http.get(apiUrl('/api/v1/admin/orders/42'), () => {
        calls += 1;
        return calls === 1
          ? new HttpResponse(null, { status: 500 })
          : HttpResponse.json({ data: buildAdminOrder() });
      }),
    );

    renderOrder();

    expect(await screen.findByText("Non è stato possibile caricare l'ordine")).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(
      await screen.findByRole('heading', { level: 2, name: 'ORD-2026-000042' }),
    ).toBeInTheDocument();
  });

  it('explains an order without a payment', async () => {
    serveOrder(buildAdminOrder({ payment: null }));

    renderOrder();

    expect(await screen.findByText('Nessun pagamento registrato.')).toBeInTheDocument();
  });
});
