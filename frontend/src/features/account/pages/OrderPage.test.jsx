import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { domainError, renderApp } from '@/test/authHelpers';
import { buildOrder, buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';

const PENDING_ROW = {
  status: { value: 'pending', label: 'In attesa' },
  description: 'Ordine ricevuto, in attesa del pagamento',
  created_at: '2026-09-28T18:41:00+02:00',
};
const PAID_ROW = {
  status: { value: 'paid', label: 'Pagato' },
  description: 'Pagamento con carta riuscito',
  created_at: '2026-09-28T18:42:00+02:00',
};
const SHIPPED_ROW = {
  status: { value: 'shipped', label: 'Spedito' },
  description: 'Segnato come spedito',
  created_at: '2026-09-29T16:40:00+02:00',
};
const DELIVERED_ROW = {
  status: { value: 'delivered', label: 'Consegnato' },
  description: 'Segnato come consegnato',
  created_at: '2026-10-01T11:05:00+02:00',
};
const CANCELLED_ROW = {
  status: { value: 'cancelled', label: 'Annullato' },
  description: 'Annullato e rimborsato',
  created_at: '2026-09-29T09:15:00+02:00',
};

const PAID_ORDER = buildOrder({ status_history: [PENDING_ROW, PAID_ROW] });
const CANCELLED_ORDER = buildOrder({
  status: { value: 'cancelled', label: 'Annullato' },
  can_cancel: false,
  status_history: [PENDING_ROW, PAID_ROW, CANCELLED_ROW],
});

function serveOrder(...responses) {
  let call = 0;
  server.use(
    http.get(apiUrl('/api/v1/orders/42'), () => {
      const response = responses[Math.min(call, responses.length - 1)];
      call += 1;
      return response();
    }),
  );
}

function json(order) {
  return () => HttpResponse.json({ data: order });
}

function recordCancel(respond) {
  const calls = [];
  server.use(
    http.post(apiUrl('/api/v1/orders/42/cancel'), () => {
      calls.push(42);
      return respond();
    }),
  );

  return calls;
}

function renderOrder() {
  return renderApp('/account/orders/42', { user: buildUser() });
}

function timeline() {
  return within(screen.getByRole('list', { name: "Stato dell'ordine" }));
}

describe('OrderPage', () => {
  it('shows the order with products, totals, address and card', async () => {
    serveOrder(
      json(
        buildOrder({
          status_history: [PENDING_ROW, PAID_ROW],
          items: [
            {
              product_name: 'Caricatore Brezza 65 W',
              product_sku: 'CDS-CHG-002',
              unit_price: 5900,
              quantity: 2,
              line_total: 11800,
              cover: null,
            },
          ],
        }),
      ),
    );

    renderOrder();

    expect(
      await screen.findByRole('heading', { level: 2, name: 'Ordine ORD-2026-000042' }),
    ).toBeInTheDocument();
    expect(screen.getByText('Effettuato il 28 settembre 2026 alle 18:42')).toBeInTheDocument();
    const products = within(screen.getByRole('region', { name: 'Prodotti' }));
    expect(products.getByText('Quantità 2 · 59,00 € cad.')).toBeInTheDocument();
    expect(products.getByText(/^118,00\s€$/)).toBeInTheDocument();
    expect(
      products.getByRole('img', { name: 'Caricatore Brezza 65 W' }).getAttribute('src'),
    ).toMatch(/fallback\.webp$/);
    expect(products.getByText('Gratuita')).toBeInTheDocument();
    const address = within(screen.getByRole('region', { name: 'Indirizzo di spedizione' }));
    expect(address.getByText('Via San Martino 42, 56125 Pisa (PI), Italia')).toBeInTheDocument();
    const payment = within(screen.getByRole('region', { name: 'Pagamento' }));
    expect(payment.getByText('Carta che termina con 4242')).toBeInTheDocument();
    expect(payment.queryByText(/Giulia Ferri/)).not.toBeInTheDocument();
    expect(payment.queryByText(/scadenza/)).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Tutti gli ordini' })).toHaveAttribute(
      'href',
      '/account/orders',
    );
  });

  it('shows the next steps of a paid order', async () => {
    serveOrder(json(PAID_ORDER));

    renderOrder();

    await screen.findByRole('list', { name: "Stato dell'ordine" });
    const steps = timeline().getAllByRole('listitem');
    expect(steps).toHaveLength(3);
    expect(steps[0]).toHaveTextContent('Pagato');
    expect(steps[0]).toHaveTextContent('28 settembre 2026, 18:42');
    expect(steps[0]).toHaveTextContent('Pagamento con carta riuscito');
    expect(steps[1]).toHaveTextContent('SpeditoTi scriveremo quando parte');
    expect(steps[2]).toHaveTextContent('ConsegnatoConsegna in 24/48 ore dalla spedizione');
    expect(timeline().queryByText('In attesa')).not.toBeInTheDocument();
  });

  it('shows every step of a delivered order with its date', async () => {
    serveOrder(
      json(
        buildOrder({
          status: { value: 'delivered', label: 'Consegnato' },
          can_cancel: false,
          status_history: [PENDING_ROW, PAID_ROW, SHIPPED_ROW, DELIVERED_ROW],
        }),
      ),
    );

    renderOrder();

    await screen.findByRole('list', { name: "Stato dell'ordine" });
    const steps = timeline().getAllByRole('listitem');
    expect(steps).toHaveLength(3);
    expect(steps[1]).toHaveTextContent('29 settembre 2026, 16:40');
    expect(steps[2]).toHaveTextContent('1 ottobre 2026, 11:05');
    expect(steps[2]).toHaveTextContent('Segnato come consegnato');
    expect(screen.queryByText('Ti scriveremo quando parte')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Annulla l'ordine" })).not.toBeInTheDocument();
  });

  it('shows the cancelled row without future steps', async () => {
    serveOrder(json(CANCELLED_ORDER));

    renderOrder();

    await screen.findByRole('list', { name: "Stato dell'ordine" });
    const steps = timeline().getAllByRole('listitem');
    expect(steps).toHaveLength(2);
    expect(steps[0]).toHaveTextContent('Pagato');
    expect(steps[1]).toHaveTextContent('Annullato');
    expect(steps[1]).toHaveTextContent('29 settembre 2026, 09:15');
    expect(steps[1]).toHaveTextContent('Annullato e rimborsato');
    expect(screen.queryByText('Spedito')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Annulla l'ordine" })).not.toBeInTheDocument();
  });

  it('keeps the order when the cancellation is not confirmed', async () => {
    const user = userEvent.setup();
    serveOrder(json(PAID_ORDER));
    const calls = recordCancel(json(CANCELLED_ORDER));

    renderOrder();

    expect(
      await screen.findByText("Puoi annullare l'ordine finché non viene spedito."),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: "Annulla l'ordine" }));
    const dialog = within(await screen.findByRole('dialog', { name: "Vuoi annullare l'ordine?" }));
    expect(
      dialog.getByText(/L'ordine ORD-2026-000042 non verrà spedito e riceverai il rimborso di/),
    ).toHaveTextContent('496,00 € sulla carta che termina con 4242.');
    expect(dialog.getByText('Ti invieremo una conferma via email.')).toBeInTheDocument();
    await user.click(dialog.getByRole('button', { name: "Mantieni l'ordine" }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(calls).toEqual([]);
  });

  it('cancels a paid order and shows the new status', async () => {
    const user = userEvent.setup();
    serveOrder(json(PAID_ORDER), json(CANCELLED_ORDER));
    const calls = recordCancel(json(CANCELLED_ORDER));

    renderOrder();

    await user.click(await screen.findByRole('button', { name: "Annulla l'ordine" }));
    const dialog = within(await screen.findByRole('dialog', { name: "Vuoi annullare l'ordine?" }));
    await user.click(dialog.getByRole('button', { name: "Annulla l'ordine" }));

    expect(
      await screen.findByText('Ordine annullato: riceverai il rimborso sulla tua carta.'),
    ).toBeInTheDocument();
    expect(calls).toEqual([42]);
    expect(await screen.findByText('Annullato e rimborsato')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Annulla l'ordine" })).not.toBeInTheDocument();
  });

  it('keeps the dialog open with the message when the refund fails', async () => {
    const user = userEvent.setup();
    serveOrder(json(PAID_ORDER));
    recordCancel(() =>
      domainError(502, 'refund_failed', 'Il rimborso non è riuscito. Riprova tra qualche minuto.'),
    );

    renderOrder();

    await user.click(await screen.findByRole('button', { name: "Annulla l'ordine" }));
    const dialogElement = await screen.findByRole('dialog', { name: "Vuoi annullare l'ordine?" });
    await user.click(within(dialogElement).getByRole('button', { name: "Annulla l'ordine" }));

    expect(
      await within(dialogElement).findByText(
        'Il rimborso non è riuscito. Riprova tra qualche minuto.',
      ),
    ).toBeInTheDocument();
    expect(dialogElement).toBeInTheDocument();
    expect(screen.getAllByText('Pagato').length).toBeGreaterThan(0);
  });

  it('reloads the order when it can no longer be cancelled', async () => {
    const user = userEvent.setup();
    const shipped = buildOrder({
      status: { value: 'shipped', label: 'Spedito' },
      can_cancel: false,
      status_history: [PENDING_ROW, PAID_ROW, SHIPPED_ROW],
    });
    serveOrder(json(PAID_ORDER), json(shipped));
    recordCancel(() =>
      domainError(
        422,
        'invalid_order_transition',
        "Lo stato attuale dell'ordine non consente questa operazione.",
      ),
    );

    renderOrder();

    await user.click(await screen.findByRole('button', { name: "Annulla l'ordine" }));
    const dialog = within(await screen.findByRole('dialog', { name: "Vuoi annullare l'ordine?" }));
    await user.click(dialog.getByRole('button', { name: "Annulla l'ordine" }));

    expect(
      await dialog.findByText("Lo stato attuale dell'ordine non consente questa operazione."),
    ).toBeInTheDocument();
    expect(await screen.findByText('Segnato come spedito')).toBeInTheDocument();
    expect(dialog.getByRole('button', { name: "Annulla l'ordine" })).toBeDisabled();
  });

  it('shows a not found state with a link to the orders', async () => {
    serveOrder(() =>
      HttpResponse.json({ message: 'La risorsa richiesta non esiste.' }, { status: 404 }),
    );

    renderOrder();

    expect(await screen.findByText('Non abbiamo trovato questo ordine')).toBeInTheDocument();
    const links = screen.getAllByRole('link', { name: 'I miei ordini' });
    expect(links).toHaveLength(2);
    links.forEach((link) => expect(link).toHaveAttribute('href', '/account/orders'));
  });

  it('shows the shape of the order while loading', async () => {
    serveOrder(async () => {
      await new Promise((resolve) => setTimeout(resolve, 50));
      return HttpResponse.json({ data: PAID_ORDER });
    });

    renderOrder();

    expect(
      await screen.findByRole('status', { name: "Caricamento dell'ordine" }),
    ).toBeInTheDocument();
    expect(await screen.findByText('Ordine ORD-2026-000042')).toBeInTheDocument();
  });

  it('shows an error with retry', async () => {
    const user = userEvent.setup();
    serveOrder(() => HttpResponse.json({ message: 'Errore.' }, { status: 500 }), json(PAID_ORDER));

    renderOrder();

    expect(await screen.findByText("Non è stato possibile caricare l'ordine")).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(await screen.findByText('Ordine ORD-2026-000042')).toBeInTheDocument();
  });
});
