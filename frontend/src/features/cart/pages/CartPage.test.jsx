import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderApp, validationError } from '@/test/authHelpers';
import {
  buildCart,
  buildCartItem,
  buildProduct,
  buildProductPage,
  buildUser,
} from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';

function headphones(overrides = {}) {
  return buildCartItem({
    id: 1,
    quantity: 1,
    unit_price: 34900,
    line_total: 34900,
    product: { name: 'Cuffie Aria', slug: 'cuffie-aria' },
    ...overrides,
  });
}

function charger(overrides = {}) {
  return buildCartItem({
    id: 2,
    quantity: 2,
    unit_price: 5900,
    line_total: 11800,
    max_quantity: 4,
    product: {
      name: 'Caricatore Brezza 65 W',
      slug: 'caricatore-brezza-65-w',
      category: { name: 'Ricarica ed energia', slug: 'ricarica-ed-energia' },
    },
    ...overrides,
  });
}

function soldOutRadio() {
  return buildCartItem({
    id: 3,
    quantity: 1,
    unit_price: 12900,
    line_total: null,
    max_quantity: 0,
    is_purchasable: false,
    exceeds_stock: true,
    product: {
      name: 'Radio Onda Portatile',
      slug: 'radio-onda-portatile',
      availability: { status: 'out_of_stock', label: 'Esaurito' },
    },
  });
}

function cartWith(items, summary = {}, canCheckout = true) {
  return buildCart({
    items,
    summary: {
      items_count: items.reduce((total, item) => total + item.quantity, 0),
      subtotal: 46700,
      shipping_cost: 0,
      total: 46700,
      free_shipping_remaining: 0,
      ...summary,
    },
    can_checkout: canCheckout,
  });
}

function serveCart(cart) {
  let requests = 0;
  server.use(
    http.get(apiUrl('/api/v1/cart'), () => {
      requests += 1;
      return HttpResponse.json({ data: cart });
    }),
  );

  return () => requests;
}

function renderCart() {
  return renderApp('/cart', { user: buildUser() });
}

describe('CartPage', () => {
  it('shows the rows, the item count with sold out rows and the totals', async () => {
    serveCart(
      cartWith(
        [headphones(), charger(), soldOutRadio()],
        { subtotal: 46700, shipping_cost: 0, total: 46700 },
        false,
      ),
    );

    renderCart();

    expect(await screen.findByRole('heading', { level: 1, name: 'Il tuo carrello' })).toBeVisible();
    expect(await screen.findByText('4 articoli')).toBeInTheDocument();
    const rows = within(screen.getByRole('list', { name: 'Prodotti nel carrello' }));
    expect(rows.getByRole('link', { name: 'Cuffie Aria' })).toHaveAttribute(
      'href',
      '/products/cuffie-aria',
    );
    expect(rows.getByText('59,00 € cad.')).toBeInTheDocument();
    expect(rows.getByText('118,00 €')).toBeInTheDocument();
    const summary = within(screen.getByRole('region', { name: 'Riepilogo' }));
    expect(summary.getByText('Gratuita')).toBeInTheDocument();
    expect(summary.getAllByText('467,00 €')).toHaveLength(2);
    expect(summary.queryByRole('progressbar')).not.toBeInTheDocument();
  });

  it('shows a sold out row with a dash and a way to remove it, and blocks the checkout', async () => {
    const user = userEvent.setup();
    const deleted = [];
    serveCart(cartWith([headphones(), soldOutRadio()], {}, false));
    server.use(
      http.delete(apiUrl('/api/v1/cart/items/:id'), ({ params }) => {
        deleted.push(params.id);
        return HttpResponse.json({ data: cartWith([headphones()]) });
      }),
    );

    renderCart();

    const row = within(await screen.findByRole('listitem', { name: 'Radio Onda Portatile' }));
    expect(row.getByText('Esaurito')).toBeInTheDocument();
    expect(row.getByText('—')).toBeInTheDocument();
    expect(row.queryByRole('combobox', { name: /^Quantità/ })).not.toBeInTheDocument();
    expect(row.getByText('Prodotto esaurito')).toBeInTheDocument();
    expect(
      row.getByText(
        'Questo prodotto non è più disponibile. Rimuovilo dal carrello per procedere al checkout.',
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Rimuovi i prodotti esauriti per procedere al checkout.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Procedi al checkout' })).toHaveAttribute(
      'aria-disabled',
      'true',
    );

    await user.click(row.getByRole('button', { name: 'Rimuovi dal carrello' }));

    await waitFor(() =>
      expect(
        screen.queryByRole('listitem', { name: 'Radio Onda Portatile' }),
      ).not.toBeInTheDocument(),
    );
    expect(deleted).toEqual(['3']);
    expect(screen.getByRole('link', { name: 'Procedi al checkout' })).toHaveAttribute(
      'href',
      '/checkout',
    );
    expect(screen.getByRole('link', { name: 'Procedi al checkout' })).not.toHaveAttribute(
      'aria-disabled',
    );
  });

  it('names a deactivated product not available instead of sold out', async () => {
    serveCart(
      cartWith(
        [
          buildCartItem({
            id: 5,
            line_total: null,
            is_purchasable: false,
            product: { name: 'Lampada Faro', slug: 'lampada-faro' },
          }),
        ],
        { subtotal: 0, total: 0, free_shipping_remaining: null },
        false,
      ),
    );

    renderCart();

    const row = within(await screen.findByRole('listitem', { name: 'Lampada Faro' }));
    expect(row.getByText('Prodotto non disponibile')).toBeInTheDocument();
    expect(row.queryByText('Prodotto esaurito')).not.toBeInTheDocument();
    expect(row.getByText('—')).toBeInTheDocument();
  });

  it.each([
    [1, 2, 'È disponibile solo 1 pezzo: riduci la quantità per procedere al checkout.'],
    [3, 5, 'Sono disponibili solo 3 pezzi: riduci la quantità per procedere al checkout.'],
  ])(
    'asks to reduce a row over the stock with %i pieces left',
    async (available, quantity, message) => {
      serveCart(
        cartWith(
          [
            charger({
              quantity,
              max_quantity: available,
              exceeds_stock: true,
              product: {
                name: 'Caricatore Brezza 65 W',
                availability: {
                  status: 'low_stock',
                  label: available === 1 ? 'Ultimo pezzo' : `Ultimi ${available} pezzi`,
                },
              },
            }),
          ],
          {},
          false,
        ),
      );

      renderCart();

      const row = within(await screen.findByRole('listitem', { name: 'Caricatore Brezza 65 W' }));
      expect(row.getByText(message)).toBeInTheDocument();
      expect(
        row.getByText(available === 1 ? 'Ultimo pezzo' : `Ultimi ${available} pezzi`),
      ).toBeInTheDocument();
      expect(
        row.getByRole('combobox', { name: 'Quantità di Caricatore Brezza 65 W' }),
      ).toHaveTextContent(String(quantity));
      expect(
        screen.getByText('Riduci le quantità indicate per procedere al checkout.'),
      ).toBeInTheDocument();
    },
  );

  it('offers quantities up to the maximum and replaces the cart with the response', async () => {
    const user = userEvent.setup();
    const bodies = [];
    const requests = serveCart(cartWith([charger()], { subtotal: 11800, total: 11800 }));
    server.use(
      http.patch(apiUrl('/api/v1/cart/items/:id'), async ({ request }) => {
        bodies.push(await request.json());
        return HttpResponse.json({
          data: cartWith([charger({ quantity: 3, line_total: 17700 })], {
            items_count: 3,
            subtotal: 17700,
            total: 17700,
          }),
        });
      }),
    );

    renderCart();

    await user.click(
      await screen.findByRole('combobox', { name: 'Quantità di Caricatore Brezza 65 W' }),
    );
    const options = screen.getAllByRole('option').map((option) => option.textContent);
    expect(options).toEqual(['1', '2', '3', '4']);
    await user.click(screen.getByRole('option', { name: '3' }));

    expect(await screen.findByText('3 articoli')).toBeInTheDocument();
    expect(bodies).toEqual([{ quantity: 3 }]);
    expect(screen.getByRole('link', { name: 'Carrello, 3 articoli' })).toBeInTheDocument();
    expect(requests()).toBe(1);
  });

  it('removes a row and shows the empty cart when it was the last one', async () => {
    const user = userEvent.setup();
    serveCart(cartWith([headphones()]));
    server.use(
      http.delete(apiUrl('/api/v1/cart/items/:id'), () => HttpResponse.json({ data: buildCart() })),
    );

    renderCart();

    await user.click(await screen.findByRole('button', { name: 'Rimuovi Cuffie Aria' }));

    expect(await screen.findByText('Il tuo carrello è vuoto')).toBeInTheDocument();
  });

  it('shows the error of a quantity change in a snackbar', async () => {
    const user = userEvent.setup();
    serveCart(cartWith([charger()]));
    server.use(
      http.patch(apiUrl('/api/v1/cart/items/:id'), () =>
        validationError(
          'quantity',
          'Puoi avere al massimo 3 pezzi di questo prodotto nel carrello.',
        ),
      ),
    );

    renderCart();

    await user.click(
      await screen.findByRole('combobox', { name: 'Quantità di Caricatore Brezza 65 W' }),
    );
    await user.click(screen.getByRole('option', { name: '4' }));

    expect(
      await screen.findByText('Puoi avere al massimo 3 pezzi di questo prodotto nel carrello.'),
    ).toBeInTheDocument();
  });

  it('shows how much is missing for free shipping', async () => {
    serveCart(
      cartWith([charger({ quantity: 2, unit_price: 2900, line_total: 5800 })], {
        subtotal: 5800,
        shipping_cost: 690,
        total: 6490,
        free_shipping_remaining: 4100,
      }),
    );

    renderCart();

    await screen.findByText('6,90 €');
    const progress = screen.getByRole('progressbar', {
      name: /^Ti mancano 41,00\s€ per la spedizione gratuita$/,
    });
    expect(progress).toHaveAttribute('aria-valuenow', '59');
  });

  it('hides the free shipping bar when nothing can be shipped', async () => {
    serveCart(
      cartWith([soldOutRadio()], { subtotal: 0, total: 0, free_shipping_remaining: null }, false),
    );

    renderCart();

    await screen.findByRole('listitem', { name: 'Radio Onda Portatile' });
    expect(screen.queryByText(/per la spedizione gratuita/)).not.toBeInTheDocument();
  });

  it('shows the empty cart with some suggestions', async () => {
    const searches = [];
    server.use(
      http.get(apiUrl('/api/v1/products'), ({ request }) => {
        searches.push(Object.fromEntries(new URL(request.url).searchParams));
        return HttpResponse.json(
          buildProductPage([
            buildProduct({ name: 'Lampada Faro' }),
            buildProduct({ name: 'Cuffie Aria' }),
          ]),
        );
      }),
    );

    renderCart();

    expect(await screen.findByText('Il tuo carrello è vuoto')).toBeInTheDocument();
    expect(screen.getByText('Quando aggiungi un prodotto, lo ritrovi qui.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Scopri il negozio' })).toHaveAttribute(
      'href',
      '/products',
    );
    expect(await screen.findByRole('heading', { name: 'Potrebbero piacerti' })).toBeInTheDocument();
    expect(await screen.findByText('Lampada Faro')).toBeInTheDocument();
    expect(searches).toEqual([{ in_stock: '1', sort: 'newest', per_page: '4' }]);
  });

  it('shows a skeleton while loading and an error with retry', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    server.use(
      http.get(apiUrl('/api/v1/cart'), async () => {
        attempts += 1;
        await new Promise((resolve) => setTimeout(resolve, 20));
        return attempts === 1
          ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
          : HttpResponse.json({ data: cartWith([headphones()]) });
      }),
    );

    renderCart();

    expect(
      await screen.findByRole('status', { name: 'Caricamento del carrello' }),
    ).toBeInTheDocument();
    expect(
      await screen.findByText('Non è stato possibile caricare il carrello'),
    ).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(await screen.findByRole('listitem', { name: 'Cuffie Aria' })).toBeInTheDocument();
  });

  it('shows the fixed bar with total and checkout on mobile', async () => {
    setViewportWidth(MOBILE_WIDTH);
    serveCart(cartWith([headphones()], { subtotal: 34900, total: 34900 }));

    renderCart();

    expect(await screen.findByText('Totale, IVA inclusa')).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: 'Procedi al checkout' })).toHaveLength(2);
  });
});
