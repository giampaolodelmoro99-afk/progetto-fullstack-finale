import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { delay, http, HttpResponse } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { uploadProductImages } from '@/features/admin/products/api/productsApi';
import ProductEditPage from '@/features/admin/products/pages/ProductEditPage';
import { buildAdminCategory, buildAdminProduct, buildAdminProductImage } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { renderAdminPage } from '@/test/renderAdminPage';

vi.mock('@/features/admin/products/api/productsApi', async (importOriginal) => ({
  ...(await importOriginal()),
  uploadProductImages: vi.fn(),
}));

const FRONT = buildAdminProductImage({ id: 101, alt: 'Cuffie Aria, vista frontale', position: 1 });
const SIDE = buildAdminProductImage({ id: 102, alt: 'Cuffie Aria, vista laterale', position: 2 });
const UPLOADED = buildAdminProductImage({ id: 103, alt: 'Cuffie Aria', position: 3 });

const PRODUCT = buildAdminProduct({ id: 9, images: [FRONT, SIDE] });

function serveProduct(product = PRODUCT) {
  server.use(
    http.get(apiUrl('/api/v1/admin/categories'), () =>
      HttpResponse.json({ data: [buildAdminCategory({ id: 1, name: 'Audio', slug: 'audio' })] }),
    ),
    http.get(apiUrl('/api/v1/admin/products/9'), () => HttpResponse.json({ data: product })),
  );
}

function recordUpdate() {
  const bodies = [];
  server.use(
    http.patch(apiUrl('/api/v1/admin/products/9'), async ({ request }) => {
      const body = await request.json();
      bodies.push(body);
      return HttpResponse.json({
        data: { ...PRODUCT, name: body.name, updated_at: '2026-10-01T09:15:00+02:00' },
      });
    }),
  );

  return bodies;
}

function imageFile(name, type = 'image/jpeg') {
  return new File(['immagine'], name, { type });
}

function imageGroup(number) {
  return within(screen.getByRole('group', { name: `Immagine ${number}` }));
}

function renderEdit() {
  return renderAdminPage(<ProductEditPage />, {
    path: '/admin/products/:productId/edit',
    route: '/admin/products/9/edit',
  });
}

/*
| The upload goes through a mock of uploadProductImages: with jsdom 30 the
| compatibility layer of Vitest cannot turn a jsdom File into the body of a
| Request, so MSW never receives a multipart request. productsApi.test.js
| checks the FormData sent by uploadProductImages.
*/
describe('ProductEditPage', () => {
  afterEach(() => {
    vi.mocked(uploadProductImages).mockReset();
  });

  it('fills the form with the saved product', async () => {
    serveProduct();

    renderEdit();

    expect(await screen.findByRole('textbox', { name: 'Nome' })).toHaveValue('Cuffie Aria');
    expect(screen.getByRole('textbox', { name: 'Prezzo' })).toHaveValue('349,00');
    expect(screen.getByRole('textbox', { name: 'Stock' })).toHaveValue('18');
    expect(screen.getByRole('switch', { name: 'Visibile nel negozio' })).toBeChecked();
    expect(screen.getByText('Ultima modifica il 26 settembre 2026 alle 14:03')).toBeInTheDocument();
    expect(imageGroup(1).getByText('Copertina')).toBeInTheDocument();
    expect(imageGroup(2).queryByText('Copertina')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Annulla' })).toBeDisabled();
  });

  it('saves the changes with the images in the order of the form', async () => {
    const user = userEvent.setup();
    serveProduct();
    const bodies = recordUpdate();

    renderEdit();

    const name = await screen.findByRole('textbox', { name: 'Nome' });
    await user.clear(name);
    await user.type(name, 'Cuffie Aria Pro');
    const sideAlt = imageGroup(2).getByRole('textbox', { name: 'Testo alternativo' });
    await user.clear(sideAlt);
    await user.type(sideAlt, 'Vista di lato');
    expect(imageGroup(1).getByRole('button', { name: 'Sposta prima' })).toBeDisabled();
    expect(imageGroup(2).getByRole('button', { name: 'Sposta dopo' })).toBeDisabled();
    await user.click(imageGroup(2).getByRole('button', { name: 'Sposta prima' }));
    await user.click(screen.getByRole('button', { name: 'Salva' }));

    expect(await screen.findByText('Modifiche salvate')).toBeInTheDocument();
    expect(bodies[0]).toMatchObject({
      name: 'Cuffie Aria Pro',
      price: 34900,
      stock: 18,
      images: [
        { id: 102, alt: 'Vista di lato' },
        { id: 101, alt: 'Cuffie Aria, vista frontale' },
      ],
    });
    expect(screen.queryByText('Hai modifiche non salvate')).not.toBeInTheDocument();
    expect(screen.getByText('Ultima modifica il 1 ottobre 2026 alle 09:15')).toBeInTheDocument();
  });

  it('brings back the saved values with «Annulla»', async () => {
    const user = userEvent.setup();
    serveProduct();

    renderEdit();

    const price = await screen.findByRole('textbox', { name: 'Prezzo' });
    await user.clear(price);
    await user.type(price, '299,00');
    expect(screen.getByText('Hai modifiche non salvate')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Annulla' }));

    expect(price).toHaveValue('349,00');
    expect(screen.queryByText('Hai modifiche non salvate')).not.toBeInTheDocument();
  });

  it('uploads the images without losing the unsaved text of the others', async () => {
    const user = userEvent.setup({ applyAccept: false });
    serveProduct();
    vi.mocked(uploadProductImages).mockResolvedValue([FRONT, SIDE, UPLOADED]);
    const bodies = recordUpdate();

    renderEdit();

    const frontAlt = await screen.findByDisplayValue('Cuffie Aria, vista frontale');
    await user.clear(frontAlt);
    await user.type(frontAlt, 'Fronte nuovo');
    await user.upload(screen.getByLabelText('Scegli le immagini da caricare'), [
      imageFile('retro.jpg'),
      imageFile('animata.gif', 'image/gif'),
    ]);

    expect(await screen.findByText('Immagine caricata')).toBeInTheDocument();
    expect(uploadProductImages).toHaveBeenCalledTimes(1);
    expect(vi.mocked(uploadProductImages).mock.calls[0][0]).toBe(9);
    expect(vi.mocked(uploadProductImages).mock.calls[0][1].map((file) => file.name)).toEqual([
      'retro.jpg',
    ]);
    expect(
      screen.getByText('animata.gif: Formato non supportato: usa JPG, PNG o WebP'),
    ).toBeInTheDocument();
    expect(imageGroup(3).getByRole('textbox', { name: 'Testo alternativo' })).toHaveValue(
      'Cuffie Aria',
    );
    expect(imageGroup(1).getByRole('textbox', { name: 'Testo alternativo' })).toHaveValue(
      'Fronte nuovo',
    );

    await user.click(screen.getByRole('button', { name: 'Salva' }));

    await waitFor(() => expect(bodies).toHaveLength(1));
    expect(bodies[0].images).toEqual([
      { id: 101, alt: 'Fronte nuovo' },
      { id: 102, alt: 'Cuffie Aria, vista laterale' },
      { id: 103, alt: 'Cuffie Aria' },
    ]);
  });

  it('does not count a new image as an unsaved change', async () => {
    const user = userEvent.setup();
    serveProduct();
    vi.mocked(uploadProductImages).mockResolvedValue([FRONT, SIDE, UPLOADED]);

    renderEdit();

    await user.upload(await screen.findByLabelText('Scegli le immagini da caricare'), [
      imageFile('retro.jpg'),
    ]);

    expect(await screen.findByRole('group', { name: 'Immagine 3' })).toBeInTheDocument();
    expect(screen.queryByText('Hai modifiche non salvate')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Annulla' })).toBeDisabled();
  });

  it('shows the error of a failed upload', async () => {
    const user = userEvent.setup();
    serveProduct();
    vi.mocked(uploadProductImages).mockRejectedValue({
      status: 422,
      message: 'Il file non è un’immagine valida.',
      fieldErrors: {},
      code: null,
    });

    renderEdit();

    await user.upload(await screen.findByLabelText('Scegli le immagini da caricare'), [
      imageFile('rotta.png', 'image/png'),
    ]);

    expect(await screen.findByText('Il file non è un’immagine valida.')).toBeInTheDocument();
  });

  it('sends more than ten images in groups of ten', async () => {
    const user = userEvent.setup();
    serveProduct({ ...PRODUCT, images: [] });
    vi.mocked(uploadProductImages).mockImplementation(async (productId, files) =>
      files.map((file, index) =>
        buildAdminProductImage({
          id: 200 + index + (files.length === 10 ? 0 : 10),
          alt: 'Cuffie Aria',
        }),
      ),
    );

    renderEdit();

    const files = Array.from({ length: 12 }, (_, index) => imageFile(`foto-${index + 1}.jpg`));
    await user.upload(await screen.findByLabelText('Scegli le immagini da caricare'), files);

    expect(await screen.findByText('Immagini caricate')).toBeInTheDocument();
    expect(vi.mocked(uploadProductImages).mock.calls.map(([, group]) => group.length)).toEqual([
      10, 2,
    ]);
    expect(screen.getByRole('group', { name: 'Immagine 12' })).toBeInTheDocument();
  });

  it('deletes an image after the confirmation without making the form dirty', async () => {
    const user = userEvent.setup();
    serveProduct();
    let deleted = false;
    server.use(
      http.delete(apiUrl('/api/v1/admin/products/9/images/101'), () => {
        deleted = true;
        return new HttpResponse(null, { status: 204 });
      }),
    );

    renderEdit();

    await screen.findByRole('group', { name: 'Immagine 1' });
    await user.click(imageGroup(1).getByRole('button', { name: "Elimina l'immagine" }));
    const dialog = screen.getByRole('dialog', { name: "Eliminare l'immagine?" });
    expect(
      within(dialog).getByText("L'immagine sparisce subito dal prodotto e dal negozio."),
    ).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: "Elimina l'immagine" }));

    expect(await screen.findByText('Immagine eliminata')).toBeInTheDocument();
    expect(deleted).toBe(true);
    expect(screen.queryByRole('group', { name: 'Immagine 2' })).not.toBeInTheDocument();
    expect(imageGroup(1).getByRole('textbox', { name: 'Testo alternativo' })).toHaveValue(
      'Cuffie Aria, vista laterale',
    );
    expect(screen.queryByText('Hai modifiche non salvate')).not.toBeInTheDocument();
  });

  it('explains a product without images', async () => {
    serveProduct({ ...PRODUCT, images: [] });

    renderEdit();

    expect(
      await screen.findByText(
        'Non hai ancora aggiunto immagini. Senza immagini il prodotto compare nel negozio con un segnaposto.',
      ),
    ).toBeInTheDocument();
  });

  it('explains a product that does not exist', async () => {
    server.use(
      http.get(apiUrl('/api/v1/admin/products/9'), () =>
        HttpResponse.json({ message: 'Risorsa non trovata.' }, { status: 404 }),
      ),
    );

    renderEdit();

    expect(await screen.findByText('Non abbiamo trovato questo prodotto')).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: 'Tutti i prodotti' })).not.toHaveLength(0);
  });

  it('shows the skeleton while the product loads', () => {
    server.use(http.get(apiUrl('/api/v1/admin/products/9'), () => delay('infinite')));

    renderEdit();

    expect(screen.getByRole('status', { name: 'Caricamento del prodotto' })).toBeInTheDocument();
  });

  it('offers to retry when the product does not load', async () => {
    const user = userEvent.setup();
    let calls = 0;
    server.use(
      http.get(apiUrl('/api/v1/admin/products/9'), () => {
        calls += 1;
        return calls === 1
          ? new HttpResponse(null, { status: 500 })
          : HttpResponse.json({ data: PRODUCT });
      }),
    );

    renderEdit();

    expect(
      await screen.findByText('Non è stato possibile caricare il prodotto'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(await screen.findByRole('textbox', { name: 'Nome' })).toHaveValue('Cuffie Aria');
  });
});
