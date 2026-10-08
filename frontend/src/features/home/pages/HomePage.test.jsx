import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderApp } from '@/test/authHelpers';
import {
  buildCategories,
  buildCategory,
  buildProduct,
  buildProductDetail,
  buildProductPage,
} from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';

function heroProduct(response) {
  return http.get(apiUrl('/api/v1/products/cuffie-aria'), response);
}

function delayed(response) {
  return async () => {
    await new Promise((resolve) => setTimeout(resolve, 50));
    return response();
  };
}

function hero() {
  return screen.getByRole('region', { name: 'Suono, in silenzio' });
}

describe('HomePage hero', () => {
  it('shows the fixed texts with price and image of the configured product', async () => {
    server.use(
      heroProduct(() =>
        HttpResponse.json({
          data: buildProductDetail({
            name: 'Cuffie Aria',
            slug: 'cuffie-aria',
            price: 34900,
            images: [{ url: 'https://cdn.esempio.it/aria.webp', alt: 'Cuffie Aria grafite' }],
          }),
        }),
      ),
    );

    renderApp('/');

    expect(
      screen.getByRole('heading', { level: 1, name: 'Suono, in silenzio' }),
    ).toBeInTheDocument();
    expect(await within(hero()).findByText('349,00 €')).toBeInTheDocument();
    expect(within(hero()).getByText('Novità · Audio')).toBeInTheDocument();
    expect(
      within(hero()).getByText(
        'Cuffie Aria: padiglioni in alluminio anodizzato, cancellazione del rumore adattiva e 40 ore di ascolto.',
      ),
    ).toBeInTheDocument();
    expect(within(hero()).getByRole('img', { name: 'Cuffie Aria grafite' })).toHaveAttribute(
      'src',
      'https://cdn.esempio.it/aria.webp',
    );
    expect(within(hero()).getByRole('link', { name: 'Scopri le Cuffie Aria' })).toHaveAttribute(
      'href',
      '/products/cuffie-aria',
    );
  });

  it('shows skeletons for price and image while the product loads', async () => {
    server.use(
      heroProduct(
        delayed(() =>
          HttpResponse.json({ data: buildProductDetail({ slug: 'cuffie-aria', price: 34900 }) }),
        ),
      ),
    );

    renderApp('/');

    expect(within(hero()).getByLabelText('Caricamento del prezzo')).toBeInTheDocument();
    expect(within(hero()).getByLabelText("Caricamento dell'immagine")).toBeInTheDocument();
    expect(await within(hero()).findByText('349,00 €')).toBeInTheDocument();
  });

  it.each([
    ['missing', 404],
    ['failing', 500],
  ])('keeps the texts and links to the catalog when the product is %s', async (_, status) => {
    server.use(heroProduct(() => HttpResponse.json({ message: 'Errore.' }, { status })));

    renderApp('/');

    expect(
      await within(hero()).findByRole('link', { name: 'Vedi tutti i prodotti' }),
    ).toHaveAttribute('href', '/products');
    expect(within(hero()).getByRole('heading', { name: 'Suono, in silenzio' })).toBeInTheDocument();
    expect(within(hero()).queryByText(/€/)).not.toBeInTheDocument();
    expect(within(hero()).queryByRole('img')).not.toBeInTheDocument();
    expect(
      within(hero()).queryByRole('link', { name: 'Scopri le Cuffie Aria' }),
    ).not.toBeInTheDocument();
  });
});

describe('HomePage featured categories', () => {
  function section() {
    return screen.getByRole('region', { name: 'Scegli da dove iniziare' });
  }

  it('shows a tile for every category received, with a fallback image', async () => {
    server.use(
      http.get(apiUrl('/api/v1/categories'), () =>
        HttpResponse.json({
          data: [
            ...buildCategories(),
            buildCategory({ name: 'Giardino', slug: 'giardino', description: 'Irrigazione' }),
          ],
        }),
      ),
    );

    renderApp('/');

    const giardino = await within(section()).findByRole('link', { name: /Giardino/ });
    expect(within(section()).getAllByRole('link')).toHaveLength(6);
    expect(giardino).toHaveAttribute('href', '/products?category=giardino');
    expect(within(giardino).getByText('Irrigazione')).toBeInTheDocument();
    expect(giardino.querySelector('img').getAttribute('src')).toMatch(/fallback\.webp$/);
    expect(
      within(section())
        .getByRole('link', { name: /Audio/ })
        .querySelector('img')
        .getAttribute('src'),
    ).toMatch(/audio\.webp$/);
  });

  it('shows the categories as a list on mobile', async () => {
    setViewportWidth(MOBILE_WIDTH);

    renderApp('/');

    expect(await within(section()).findByRole('list')).toBeInTheDocument();
    expect(within(section()).getAllByRole('listitem')).toHaveLength(5);
    expect(within(section()).getByRole('link', { name: /Casa smart/ })).toHaveAttribute(
      'href',
      '/products?category=casa-smart',
    );
  });

  it('hides the section when the categories fail to load', async () => {
    server.use(
      http.get(apiUrl('/api/v1/categories'), () =>
        HttpResponse.json({ message: 'Errore.' }, { status: 500 }),
      ),
    );

    renderApp('/');

    await waitFor(() =>
      expect(
        screen.queryByRole('region', { name: 'Scegli da dove iniziare' }),
      ).not.toBeInTheDocument(),
    );
    expect(
      screen.getByRole('heading', { level: 1, name: 'Suono, in silenzio' }),
    ).toBeInTheDocument();
  });
});

describe('HomePage new arrivals', () => {
  function section() {
    return screen.getByRole('region', { name: 'Novità' });
  }

  it('previews four new products and links to all of them', async () => {
    const requests = [];
    server.use(
      http.get(apiUrl('/api/v1/products'), ({ request }) => {
        requests.push(new URL(request.url).searchParams.toString());
        return HttpResponse.json(
          buildProductPage([
            buildProduct({
              name: 'Orologio Meridiana',
              price: 42900,
              is_new: true,
              availability: { status: 'low_stock', label: 'Ultimi 2 pezzi' },
              category: { name: 'Indossabili', slug: 'indossabili' },
            }),
            buildProduct({ name: 'Caricatore Brezza 65 W', price: 5900, is_new: true }),
            buildProduct({ name: 'Lampada Faro', price: 14900, is_new: true }),
            buildProduct({ name: 'Supporto Colle in alluminio', price: 8900, is_new: true }),
          ]),
        );
      }),
    );

    renderApp('/');

    const card = await within(section()).findByRole('link', { name: /Orologio Meridiana/ });
    expect(requests).toEqual(['new=1&sort=newest&per_page=4']);
    expect(within(section()).getAllByRole('heading', { level: 3 })).toHaveLength(4);
    expect(card).toHaveAttribute('href', expect.stringMatching(/^\/products\//));
    expect(within(card).getByText('Novità')).toBeInTheDocument();
    expect(within(card).getByText('Indossabili')).toBeInTheDocument();
    expect(within(card).getByText('429,00 €')).toBeInTheDocument();
    expect(within(card).getByText('Ultimi 2 pezzi')).toBeInTheDocument();
    expect(
      within(within(section()).getByRole('link', { name: /Lampada Faro/ })).queryByText(
        'Disponibile',
      ),
    ).not.toBeInTheDocument();
    expect(within(section()).getByRole('link', { name: 'Vedi tutte le novità' })).toHaveAttribute(
      'href',
      '/products?new=1',
    );
  });

  it('retries after an error', async () => {
    const user = userEvent.setup();
    let calls = 0;
    server.use(
      http.get(apiUrl('/api/v1/products'), () => {
        calls += 1;
        return calls === 1
          ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
          : HttpResponse.json(buildProductPage([buildProduct({ name: 'Lampada Faro' })]));
      }),
    );

    renderApp('/');

    const retry = await within(section()).findByRole('button', { name: 'Riprova' });
    expect(
      within(section()).getByText('Non è stato possibile caricare le novità.'),
    ).toBeInTheDocument();
    await user.click(retry);
    expect(
      await within(section()).findByRole('link', { name: /Lampada Faro/ }),
    ).toBeInTheDocument();
  });

  it('hides the section without new products', async () => {
    renderApp('/');

    await screen.findByRole('region', { name: 'Scegli da dove iniziare' });
    await within(screen.getByRole('region', { name: 'Scegli da dove iniziare' })).findAllByRole(
      'link',
    );
    expect(screen.queryByRole('region', { name: 'Novità' })).not.toBeInTheDocument();
  });
});

describe('HomePage reassurances', () => {
  function section() {
    return screen.getByRole('region', { name: 'I nostri servizi' });
  }

  it('reads the free shipping threshold from the shop settings', async () => {
    server.use(
      http.get(apiUrl('/api/v1/shop/settings'), () =>
        HttpResponse.json({ data: { shipping_cost: 500, free_shipping_threshold: 12000 } }),
      ),
    );

    renderApp('/');

    expect(
      await within(section()).findByText('Spedizione gratuita sopra 120,00 €'),
    ).toBeInTheDocument();
    expect(within(section()).getByText('Reso entro 30 giorni')).toBeInTheDocument();
    expect(within(section()).getByText('Garanzia di 2 anni')).toBeInTheDocument();
  });

  it('shows a skeleton while the settings load', async () => {
    server.use(
      http.get(
        apiUrl('/api/v1/shop/settings'),
        delayed(() =>
          HttpResponse.json({ data: { shipping_cost: 690, free_shipping_threshold: 9900 } }),
        ),
      ),
    );

    renderApp('/');

    expect(within(section()).getByLabelText("Caricamento dell'importo")).toBeInTheDocument();
    expect(
      await within(section()).findByText('Spedizione gratuita sopra 99,00 €'),
    ).toBeInTheDocument();
  });

  it('omits the free shipping item when the settings fail to load', async () => {
    server.use(
      http.get(apiUrl('/api/v1/shop/settings'), () =>
        HttpResponse.json({ message: 'Errore.' }, { status: 500 }),
      ),
    );

    renderApp('/');

    await within(section()).findByText('Reso entro 30 giorni');
    await expect.poll(() => within(section()).queryByText(/Spedizione gratuita/)).toBeNull();
    expect(within(section()).getAllByRole('listitem')).toHaveLength(2);
  });
});
