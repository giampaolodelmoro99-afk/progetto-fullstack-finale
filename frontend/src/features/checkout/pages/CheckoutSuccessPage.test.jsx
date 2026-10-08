import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderApp } from '@/test/authHelpers';
import { buildOrder, buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';

function serveOrder(response) {
  server.use(http.get(apiUrl('/api/v1/orders/42'), response));
}

function renderSuccess() {
  return renderApp('/checkout/success/42', { user: buildUser() });
}

describe('CheckoutSuccessPage', () => {
  it('shows the order read from the API', async () => {
    serveOrder(() =>
      HttpResponse.json({
        data: buildOrder({
          items: [
            {
              product_name: 'Cuffie Aria',
              product_sku: 'CDS-AUD-001',
              unit_price: 34900,
              quantity: 1,
              line_total: 34900,
              cover: { url: 'https://cdn.esempio.it/cuffie.webp', alt: 'Cuffie Aria grafite' },
            },
            {
              product_name: 'Radio Onda',
              product_sku: 'CDS-AUD-009',
              unit_price: 14700,
              quantity: 1,
              line_total: 14700,
              cover: null,
            },
          ],
        }),
      }),
    );

    renderSuccess();

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Grazie per il tuo ordine' }),
    ).toBeInTheDocument();
    expect(screen.getByText('giulia.ferri@esempio.it')).toBeInTheDocument();
    const details = within(screen.getByRole('region', { name: "Dati dell'ordine" }));
    expect(details.getByText('ORD-2026-000042')).toBeInTheDocument();
    expect(details.getByText('28 settembre 2026')).toBeInTheDocument();
    expect(details.getByText('Pagato')).toBeInTheDocument();
    expect(details.getByText('Carta che termina con 4242')).toBeInTheDocument();
    expect(details.getByText('Via San Martino 42, 56125 Pisa (PI), Italia')).toBeInTheDocument();
    const recap = within(screen.getByRole('region', { name: "Riepilogo dell'ordine" }));
    expect(recap.getByRole('img', { name: 'Radio Onda' }).getAttribute('src')).toMatch(
      /fallback\.webp$/,
    );
    expect(recap.getAllByText(/^496,00\s€$/)).toHaveLength(2);
    expect(screen.getByRole('link', { name: 'Vedi il tuo ordine' })).toHaveAttribute(
      'href',
      '/account/orders/42',
    );
    expect(screen.getByRole('link', { name: 'Continua lo shopping' })).toHaveAttribute(
      'href',
      '/products',
    );
  });

  it('shows the shape of the confirmation while loading', async () => {
    serveOrder(async () => {
      await new Promise((resolve) => setTimeout(resolve, 50));
      return HttpResponse.json({ data: buildOrder() });
    });

    renderSuccess();

    expect(
      await screen.findByRole('status', { name: "Caricamento dell'ordine" }),
    ).toBeInTheDocument();
    expect(await screen.findByText('ORD-2026-000042')).toBeInTheDocument();
  });

  it('shows a not found state with a link to the orders', async () => {
    serveOrder(() =>
      HttpResponse.json({ message: 'La risorsa richiesta non esiste.' }, { status: 404 }),
    );

    renderSuccess();

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Non abbiamo trovato questo ordine' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'I miei ordini' })).toHaveAttribute(
      'href',
      '/account/orders',
    );
  });

  it('shows an error with retry', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    serveOrder(() => {
      attempts += 1;
      return attempts === 1
        ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
        : HttpResponse.json({ data: buildOrder() });
    });

    renderSuccess();

    expect(await screen.findByText("Non è stato possibile caricare l'ordine")).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(await screen.findByText('ORD-2026-000042')).toBeInTheDocument();
  });
});
