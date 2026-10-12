import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { queryKeys } from '@/api/queryKeys';
import ProductNewPage from '@/features/admin/products/pages/ProductNewPage';
import { buildAdminCategory, buildAdminProduct } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { OUTSIDE_ADMIN_PAGE, renderAdminPage } from '@/test/renderAdminPage';

const AUDIO = buildAdminCategory({ id: 1, name: 'Audio', slug: 'audio' });

function serveCategories() {
  server.use(
    http.get(apiUrl('/api/v1/admin/categories'), () => HttpResponse.json({ data: [AUDIO] })),
  );
}

function recordCreation(respond) {
  const bodies = [];
  server.use(
    http.post(apiUrl('/api/v1/admin/products'), async ({ request }) => {
      bodies.push(await request.json());
      return respond();
    }),
  );

  return bodies;
}

function recordLogout() {
  const calls = [];
  server.use(
    http.post(apiUrl('/api/v1/logout'), () => {
      calls.push('logout');
      return new HttpResponse(null, { status: 204 });
    }),
  );

  return calls;
}

function renderNewProduct() {
  serveCategories();

  return renderAdminPage(<ProductNewPage />, { path: '/admin/products/new' });
}

async function fillRequiredFields(user) {
  await user.type(await screen.findByRole('textbox', { name: 'Nome' }), 'Cuffie Aria Mini');
  await user.type(screen.getByRole('textbox', { name: 'SKU' }), 'CDS-AUD-003');
  await user.click(screen.getByRole('combobox', { name: 'Categoria' }));
  await user.click(await screen.findByRole('option', { name: 'Audio' }));
  await user.type(
    screen.getByRole('textbox', { name: 'Descrizione breve' }),
    'Cuffie over-ear compatte, pieghevoli.',
  );
  await user.type(screen.getByRole('textbox', { name: 'Prezzo' }), '1249,5');
}

describe('ProductNewPage', () => {
  it('starts with no stock, hidden from the shop and without images', async () => {
    renderNewProduct();

    expect(await screen.findByRole('textbox', { name: 'Stock' })).toHaveValue('0');
    expect(screen.getByRole('switch', { name: 'Visibile nel negozio' })).not.toBeChecked();
    expect(
      screen.getByText('Il prodotto resta in archivio e non compare nel negozio.'),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Potrai aggiungere le immagini dopo il primo salvataggio.'),
    ).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Scegli i file' })).not.toBeInTheDocument();
    expect(
      await screen.findByText('Con 5 pezzi o meno il prodotto entra tra le scorte basse.'),
    ).toBeInTheDocument();
  });

  it('counts the fields to correct when the form is not valid', async () => {
    const user = userEvent.setup();
    const bodies = recordCreation(() => HttpResponse.json({}, { status: 500 }));
    renderNewProduct();

    await user.type(await screen.findByRole('textbox', { name: 'Nome' }), 'Cuffie Aria Mini');
    await user.type(screen.getByRole('textbox', { name: 'SKU' }), 'cds-aud-003');
    await user.type(screen.getByRole('textbox', { name: 'Prezzo' }), '1.249');
    await user.click(screen.getByRole('button', { name: 'Salva' }));

    expect(await screen.findByText('Il prodotto non è stato salvato')).toBeInTheDocument();
    expect(
      screen.getByText('Correggi i 4 campi evidenziati e salva di nuovo.'),
    ).toBeInTheDocument();
    expect(screen.getByText('Usa solo lettere maiuscole, numeri e trattini')).toBeInTheDocument();
    expect(screen.getByText('Scegli una categoria')).toBeInTheDocument();
    expect(screen.getByText('Inserisci la descrizione breve.')).toBeInTheDocument();
    expect(screen.getByText('Inserisci un prezzo, ad esempio 249,00')).toBeInTheDocument();
    expect(bodies).toEqual([]);
  });

  it('shows the counter of the short description', async () => {
    const user = userEvent.setup();
    renderNewProduct();

    await user.type(
      await screen.findByRole('textbox', { name: 'Descrizione breve' }),
      'Cuffie compatte',
    );

    expect(screen.getByText('15/160')).toBeInTheDocument();
  });

  it('creates the product with the price in cents and opens its form without asking', async () => {
    const user = userEvent.setup();
    const bodies = recordCreation(() =>
      HttpResponse.json(
        { data: buildAdminProduct({ id: 77, name: 'Cuffie Aria Mini', images: [] }) },
        { status: 201 },
      ),
    );
    const { router } = renderNewProduct();

    await fillRequiredFields(user);
    await user.click(screen.getByRole('button', { name: 'Aggiungi una riga' }));
    await user.type(screen.getByRole('textbox', { name: 'Etichetta' }), 'Peso');
    await user.type(screen.getByRole('textbox', { name: 'Valore' }), '180 g');
    await user.click(screen.getByRole('button', { name: 'Salva' }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/admin/products/77/edit'));
    expect(router.state.historyAction).toBe('REPLACE');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(await screen.findByText('Prodotto creato')).toBeInTheDocument();
    expect(bodies).toEqual([
      {
        name: 'Cuffie Aria Mini',
        sku: 'CDS-AUD-003',
        category_id: 1,
        short_description: 'Cuffie over-ear compatte, pieghevoli.',
        description: null,
        specifications: [{ label: 'Peso', value: '180 g' }],
        price: 124950,
        stock: 0,
        is_active: false,
      },
    ]);
  });

  it('shows the errors of the server on the fields, nested rows included', async () => {
    const user = userEvent.setup();
    recordCreation(() =>
      HttpResponse.json(
        {
          message: 'Questo SKU è già in uso (e un altro errore)',
          errors: {
            sku: ['Questo SKU è già in uso'],
            'specifications.0.value': ['Il campo valore non può superare 255 caratteri.'],
          },
        },
        { status: 422 },
      ),
    );
    renderNewProduct();

    await fillRequiredFields(user);
    await user.click(screen.getByRole('button', { name: 'Aggiungi una riga' }));
    await user.type(screen.getByRole('textbox', { name: 'Etichetta' }), 'Peso');
    await user.type(screen.getByRole('textbox', { name: 'Valore' }), '180 g');
    await user.click(screen.getByRole('button', { name: 'Salva' }));

    expect(await screen.findByText('Questo SKU è già in uso')).toBeInTheDocument();
    expect(screen.getByText('Il campo valore non può superare 255 caratteri.')).toBeInTheDocument();
    expect(
      screen.getByText('Correggi i 2 campi evidenziati e salva di nuovo.'),
    ).toBeInTheDocument();
  });

  it('moves the rows of the specifications in the order sent to the API', async () => {
    const user = userEvent.setup();
    const bodies = recordCreation(() =>
      HttpResponse.json({ data: buildAdminProduct({ id: 78 }) }, { status: 201 }),
    );
    renderNewProduct();

    await fillRequiredFields(user);
    await user.click(screen.getByRole('button', { name: 'Aggiungi una riga' }));
    await user.click(screen.getByRole('button', { name: 'Aggiungi una riga' }));
    const first = within(screen.getByRole('listitem', { name: 'Riga 1' }));
    const second = within(screen.getByRole('listitem', { name: 'Riga 2' }));
    await user.type(first.getByRole('textbox', { name: 'Etichetta' }), 'Driver');
    await user.type(first.getByRole('textbox', { name: 'Valore' }), '40 mm');
    await user.type(second.getByRole('textbox', { name: 'Etichetta' }), 'Peso');
    await user.type(second.getByRole('textbox', { name: 'Valore' }), '180 g');

    expect(first.getByRole('button', { name: 'Sposta su' })).toBeDisabled();
    expect(second.getByRole('button', { name: 'Sposta giù' })).toBeDisabled();
    await user.click(second.getByRole('button', { name: 'Sposta su' }));
    await user.click(screen.getByRole('button', { name: 'Salva' }));

    await waitFor(() => expect(bodies).toHaveLength(1));
    expect(bodies[0].specifications).toEqual([
      { label: 'Peso', value: '180 g' },
      { label: 'Driver', value: '40 mm' },
    ]);
  });

  it('asks before leaving with unsaved changes and stays on the page', async () => {
    const user = userEvent.setup();
    const { router } = renderNewProduct();

    await user.type(await screen.findByRole('textbox', { name: 'Nome' }), 'Cuffie');
    expect(screen.getByText('Hai modifiche non salvate')).toBeInTheDocument();
    await user.click(screen.getByRole('link', { name: 'Tutti i prodotti' }));

    const dialog = screen.getByRole('dialog', { name: 'Uscire senza salvare?' });
    expect(
      within(dialog).getByText('Le modifiche che non hai salvato andranno perse.'),
    ).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Resta sulla pagina' }));

    expect(router.state.location.pathname).toBe('/admin/products/new');
    expect(screen.getByRole('textbox', { name: 'Nome' })).toHaveValue('Cuffie');
  });

  it('leaves without saving after the confirmation', async () => {
    const user = userEvent.setup();
    const { router } = renderNewProduct();

    await user.type(await screen.findByRole('textbox', { name: 'Nome' }), 'Cuffie');
    await user.click(screen.getByRole('button', { name: 'Annulla' }));
    await user.click(screen.getByRole('button', { name: 'Esci senza salvare' }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/admin/products'));
  });

  it('goes back to the list with «Annulla» when nothing changed', async () => {
    const user = userEvent.setup();
    const { router } = renderNewProduct();

    await user.click(await screen.findByRole('button', { name: 'Annulla' }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/admin/products'));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('protects the unsaved changes when the tab closes or reloads', async () => {
    const user = userEvent.setup();
    renderNewProduct();

    const pristine = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(pristine);
    expect(pristine.defaultPrevented).toBe(false);

    await user.type(await screen.findByRole('textbox', { name: 'Nome' }), 'Cuffie');
    const dirty = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(dirty);
    expect(dirty.defaultPrevented).toBe(true);
  });

  it('logs out after «Esci» only when the admin confirms to leave the changes', async () => {
    const user = userEvent.setup();
    const logouts = recordLogout();
    const { router, queryClient } = renderNewProduct();

    await user.type(await screen.findByRole('textbox', { name: 'Nome' }), 'Cuffie');
    await user.click(screen.getByRole('button', { name: 'Esci' }));

    expect(screen.getByRole('dialog', { name: 'Uscire senza salvare?' })).toBeInTheDocument();
    expect(logouts).toEqual([]);
    await user.click(screen.getByRole('button', { name: 'Esci senza salvare' }));

    expect(await screen.findByText(OUTSIDE_ADMIN_PAGE)).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/');
    await waitFor(() => expect(queryClient.getQueryData(queryKeys.me)).toBeNull());
    expect(logouts).toEqual(['logout']);
  });

  it('forgets the logout when the admin stays on the page', async () => {
    const user = userEvent.setup();
    const logouts = recordLogout();
    const { router } = renderNewProduct();

    await user.type(await screen.findByRole('textbox', { name: 'Nome' }), 'Cuffie');
    await user.click(screen.getByRole('button', { name: 'Esci' }));
    await user.click(screen.getByRole('button', { name: 'Resta sulla pagina' }));

    expect(router.state.location.pathname).toBe('/admin/products/new');
    await user.click(
      within(screen.getByRole('navigation', { name: 'Amministrazione' })).getByRole('link', {
        name: 'Prodotti',
      }),
    );
    await user.click(screen.getByRole('button', { name: 'Esci senza salvare' }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/admin/products'));
    expect(logouts).toEqual([]);
    expect(screen.getByText('Marta Lenzi')).toBeInTheDocument();
  });
});
