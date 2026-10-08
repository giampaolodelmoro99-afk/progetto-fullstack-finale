import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { recordPost, renderApp, validationError } from '@/test/authHelpers';
import {
  buildAdmin,
  buildCart,
  buildProduct,
  buildProductDetail,
  buildUser,
} from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';

const SLUG = 'diffusore-eco';

function diffusore(overrides = {}) {
  return buildProductDetail({
    id: 12,
    name: 'Diffusore Eco',
    slug: SLUG,
    price: 124900,
    short_description: 'Diffusore wireless multistanza in alluminio anodizzato.',
    description: 'Riempie la stanza con un suono ampio.\n\nSi collega in Wi-Fi o Bluetooth.',
    specifications: [
      { label: 'Dimensioni', value: '18 × 18 × 34 cm' },
      { label: 'Potenza', value: '2 × 40 W' },
    ],
    category: { name: 'Audio', slug: 'audio' },
    availability: { status: 'available', label: 'Disponibile' },
    images: [
      { url: 'https://cdn.esempio.it/eco-front.webp', alt: 'Diffusore Eco, vista frontale' },
      { url: 'https://cdn.esempio.it/eco-detail.webp', alt: 'Diffusore Eco, anello superiore' },
    ],
    max_quantity: 3,
    ...overrides,
  });
}

function serveProduct(product = diffusore(), related = []) {
  server.use(
    http.get(apiUrl(`/api/v1/products/${SLUG}`), () => HttpResponse.json({ data: product })),
    http.get(apiUrl(`/api/v1/products/${SLUG}/related`), () =>
      HttpResponse.json({ data: related }),
    ),
  );
}

function outOfStock() {
  return diffusore({
    availability: { status: 'out_of_stock', label: 'Esaurito' },
    max_quantity: 0,
  });
}

describe('ProductPage', () => {
  it('shows breadcrumb, details, availability and technical sheet', async () => {
    serveProduct();

    renderApp(`/products/${SLUG}`);

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Diffusore Eco' }),
    ).toBeInTheDocument();
    const breadcrumb = screen.getByRole('navigation', { name: 'Percorso' });
    expect(within(breadcrumb).getByRole('link', { name: 'Audio' })).toHaveAttribute(
      'href',
      '/products?category=audio',
    );
    expect(within(breadcrumb).getByText('Diffusore Eco')).toHaveAttribute('aria-current', 'page');
    expect(
      screen.getByText('Diffusore wireless multistanza in alluminio anodizzato.'),
    ).toBeInTheDocument();
    expect(screen.getByText('1.249,00 €')).toBeInTheDocument();
    expect(screen.getByText('Disponibile')).toBeInTheDocument();
    expect(screen.getByText('Riempie la stanza con un suono ampio.')).toBeInTheDocument();
    expect(screen.getByText('Si collega in Wi-Fi o Bluetooth.')).toBeInTheDocument();
    const sheet = screen.getByRole('table', { name: 'Scheda tecnica' });
    expect(
      within(sheet)
        .getAllByRole('row')
        .map((row) => row.textContent),
    ).toEqual(['Dimensioni18 × 18 × 34 cm', 'Potenza2 × 40 W']);
    expect(
      await screen.findByText('Spedizione gratuita sopra 99,00 €, consegna in 24/48 ore'),
    ).toBeInTheDocument();
  });

  it('uses the product name as document title', async () => {
    serveProduct();

    renderApp(`/products/${SLUG}`);

    expect(document.title).toBe('Prodotto · Casa del Silicio');
    await screen.findByRole('heading', { level: 1, name: 'Diffusore Eco' });
    await waitFor(() => expect(document.title).toBe('Diffusore Eco · Casa del Silicio'));
  });

  it('switches the main image from the thumbnails', async () => {
    const user = userEvent.setup();
    serveProduct();

    renderApp(`/products/${SLUG}`);

    expect(
      await screen.findByRole('img', { name: 'Diffusore Eco, vista frontale' }),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: "Mostra l'immagine 2" }));

    expect(screen.getByRole('img', { name: 'Diffusore Eco, anello superiore' })).toHaveAttribute(
      'src',
      'https://cdn.esempio.it/eco-detail.webp',
    );
    expect(screen.getByRole('button', { name: "Mostra l'immagine 2" })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
  });

  it('shows a fallback image for a product without images', async () => {
    serveProduct(diffusore({ images: [] }));

    renderApp(`/products/${SLUG}`);

    const image = await screen.findByRole('img', { name: 'Diffusore Eco' });
    expect(image.getAttribute('src')).toMatch(/fallback\.webp$/);
    expect(screen.queryByRole('button', { name: /Mostra l'immagine/ })).not.toBeInTheDocument();
  });

  it.each([
    [12000, 124900, 'IVA inclusa · Spedizione gratuita'],
    [12000, 5900, 'IVA inclusa'],
  ])(
    'mentions free shipping from the threshold %i for a price of %i',
    async (threshold, price, text) => {
      server.use(
        http.get(apiUrl('/api/v1/shop/settings'), () =>
          HttpResponse.json({ data: { shipping_cost: 690, free_shipping_threshold: threshold } }),
        ),
      );
      serveProduct(diffusore({ price }));

      renderApp(`/products/${SLUG}`);

      expect(await screen.findByText(text)).toBeInTheDocument();
      expect(
        await screen.findByText('Spedizione gratuita sopra 120,00 €, consegna in 24/48 ore'),
      ).toBeInTheDocument();
    },
  );

  it('offers quantities from 1 to the maximum of the product', async () => {
    const user = userEvent.setup();
    serveProduct();

    renderApp(`/products/${SLUG}`, { user: buildUser() });

    await user.click(await screen.findByRole('combobox', { name: 'Quantità' }));

    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual([
      '1',
      '2',
      '3',
    ]);
  });

  it('adds the product to the cart, updates the badge and links to the cart', async () => {
    const user = userEvent.setup();
    serveProduct();
    const bodies = recordPost('/api/v1/cart/items', () =>
      HttpResponse.json({ data: buildCart({ summary: { items_count: 2 } }) }, { status: 201 }),
    );
    const { router } = renderApp(`/products/${SLUG}`, { user: buildUser() });

    await user.click(await screen.findByRole('combobox', { name: 'Quantità' }));
    await user.click(screen.getByRole('option', { name: '2' }));
    await user.click(screen.getByRole('button', { name: 'Aggiungi al carrello' }));

    expect(await screen.findByText('Aggiunto al carrello')).toBeInTheDocument();
    expect(bodies).toEqual([{ product_id: 12, quantity: 2 }]);
    expect(screen.getByRole('link', { name: 'Carrello, 2 articoli' })).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Vai al carrello' }));

    expect(router.state.location.pathname).toBe('/cart');
  });

  it('shows the message of a rejected addition', async () => {
    const user = userEvent.setup();
    serveProduct();
    recordPost('/api/v1/cart/items', () =>
      validationError('quantity', 'Puoi avere al massimo 3 pezzi di questo prodotto nel carrello.'),
    );

    renderApp(`/products/${SLUG}`, { user: buildUser() });

    await user.click(await screen.findByRole('button', { name: 'Aggiungi al carrello' }));

    expect(
      await screen.findByText('Puoi avere al massimo 3 pezzi di questo prodotto nel carrello.'),
    ).toBeInTheDocument();
  });

  it('sends a guest to the login and back to the product', async () => {
    const user = userEvent.setup();
    serveProduct();
    const bodies = recordPost('/api/v1/cart/items', () => HttpResponse.json({}, { status: 201 }));
    const { router } = renderApp(`/products/${SLUG}`);

    await user.click(await screen.findByRole('button', { name: 'Aggiungi al carrello' }));

    expect(await screen.findByRole('heading', { level: 1, name: 'Accedi' })).toBeInTheDocument();
    expect(router.state.location.state.from).toMatchObject({ pathname: `/products/${SLUG}` });
    expect(bodies).toHaveLength(0);
  });

  it('shows an admin the notice instead of the purchase controls', async () => {
    let cartRequests = 0;
    server.use(
      http.get(apiUrl('/api/v1/cart'), () => {
        cartRequests += 1;
        return HttpResponse.json({ data: buildCart() });
      }),
    );
    serveProduct();

    renderApp(`/products/${SLUG}`, { user: buildAdmin() });

    expect(
      await screen.findByText('Gli account di amministrazione non possono fare acquisti.'),
    ).toBeInTheDocument();
    expect(screen.getByText('1.249,00 €')).toBeInTheDocument();
    expect(screen.getByText('Disponibile')).toBeInTheDocument();
    expect(screen.getByText('Reso entro 30 giorni')).toBeInTheDocument();
    expect(screen.queryByRole('combobox', { name: 'Quantità' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Aggiungi al carrello' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /^Carrello/ })).not.toBeInTheDocument();
    expect(cartRequests).toBe(0);
  });

  it('disables the purchase of a sold out product and points to the related ones', async () => {
    serveProduct(outOfStock(), [buildProduct({ name: 'Diffusore Eco Mini' })]);

    renderApp(`/products/${SLUG}`, { user: buildUser() });

    expect(await screen.findByText('Prodotto esaurito')).toBeInTheDocument();
    expect(
      await screen.findByText(
        'Al momento questo prodotto non è disponibile. Più in basso trovi alcuni prodotti simili.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Aggiungi al carrello' })).toBeDisabled();
    expect(screen.getByRole('combobox', { name: 'Quantità' })).toHaveAttribute(
      'aria-disabled',
      'true',
    );
  });

  it('does not mention related products that are not there', async () => {
    serveProduct(outOfStock(), []);

    renderApp(`/products/${SLUG}`, { user: buildUser() });

    expect(
      await screen.findByText('Al momento questo prodotto non è disponibile.'),
    ).toBeInTheDocument();
    expect(screen.queryByText(/Più in basso/)).not.toBeInTheDocument();
  });

  it('shows the related products and hides the section when there are none', async () => {
    serveProduct(diffusore(), [
      buildProduct({ name: 'Diffusore Eco Mini' }),
      buildProduct({ name: 'Radio Onda' }),
    ]);

    const { unmount } = renderApp(`/products/${SLUG}`);

    const related = await screen.findByRole('region', { name: 'Prodotti correlati' });
    expect(await within(related).findAllByRole('listitem')).toHaveLength(2);
    unmount();

    serveProduct(diffusore(), []);
    renderApp(`/products/${SLUG}`);

    await screen.findByRole('heading', { level: 1, name: 'Diffusore Eco' });
    await expect
      .poll(() => screen.queryByRole('region', { name: 'Prodotti correlati' }))
      .toBeNull();
  });

  it('shows the not found page for a missing product', async () => {
    server.use(
      http.get(apiUrl(`/api/v1/products/${SLUG}`), () =>
        HttpResponse.json({ message: 'Risorsa non trovata.' }, { status: 404 }),
      ),
      http.get(apiUrl(`/api/v1/products/${SLUG}/related`), () =>
        HttpResponse.json({ message: 'Risorsa non trovata.' }, { status: 404 }),
      ),
    );

    renderApp(`/products/${SLUG}`);

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Pagina non trovata' }),
    ).toBeInTheDocument();
    await waitFor(() => expect(document.title).toBe('Pagina non trovata · Casa del Silicio'));
  });

  it('retries after an error', async () => {
    const user = userEvent.setup();
    let calls = 0;
    server.use(
      http.get(apiUrl(`/api/v1/products/${SLUG}`), () => {
        calls += 1;
        return calls === 1
          ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
          : HttpResponse.json({ data: diffusore() });
      }),
      http.get(apiUrl(`/api/v1/products/${SLUG}/related`), () => HttpResponse.json({ data: [] })),
    );

    renderApp(`/products/${SLUG}`);

    expect(
      await screen.findByText('Non è stato possibile caricare il prodotto'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Diffusore Eco' }),
    ).toBeInTheDocument();
  });
});
