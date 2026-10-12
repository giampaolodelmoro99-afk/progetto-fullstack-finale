import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { delay, http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { queryKeys } from '@/api/queryKeys';
import CategoriesPage from '@/features/admin/categories/pages/CategoriesPage';
import { domainError, validationError } from '@/test/authHelpers';
import { buildAdminCategory, buildCategories } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { renderAdminPage } from '@/test/renderAdminPage';

const AUDIO = buildAdminCategory({ id: 1, name: 'Audio', slug: 'audio', products_count: 24 });
const ARCHIVE = buildAdminCategory({
  id: 7,
  name: 'Archivio',
  slug: 'archivio',
  description: null,
  products_count: 0,
  can_delete: false,
});
const TRAVEL = buildAdminCategory({
  id: 6,
  name: 'Viaggio',
  slug: 'viaggio',
  description: 'Custodie e adattatori per chi viaggia',
  products_count: 0,
  can_delete: true,
});

function serveCategories(categories = [AUDIO, ARCHIVE, TRAVEL]) {
  server.use(
    http.get(apiUrl('/api/v1/admin/categories'), () => HttpResponse.json({ data: categories })),
  );
}

function renderCategories() {
  return renderAdminPage(<CategoriesPage />, { path: '/admin/categories' });
}

describe('CategoriesPage', () => {
  it('lists the categories with slug, products and the count', async () => {
    serveCategories();

    renderCategories();

    const table = await screen.findByRole('table', { name: 'Categorie' });
    const rows = within(table).getAllByRole('row');
    expect(within(rows[1]).getByText('Audio')).toBeInTheDocument();
    expect(within(rows[1]).getByText('audio')).toBeInTheDocument();
    expect(within(rows[1]).getByText('24')).toBeInTheDocument();
    expect(
      screen.getByText('3 categorie · una categoria con prodotti non si può eliminare'),
    ).toBeInTheDocument();
  });

  it('disables the deletion with the reason in a tooltip', async () => {
    const user = userEvent.setup();
    serveCategories();

    renderCategories();

    const deleteAudio = await screen.findByRole('button', { name: 'Elimina Audio' });
    expect(deleteAudio).toBeDisabled();
    await user.hover(deleteAudio.parentElement);
    expect(
      await screen.findByRole('tooltip', {
        name: 'Contiene prodotti: spostali prima di eliminarla',
      }),
    ).toBeInTheDocument();

    await user.unhover(deleteAudio.parentElement);
    const deleteArchive = screen.getByRole('button', { name: 'Elimina Archivio' });
    expect(deleteArchive).toBeDisabled();
    await user.hover(deleteArchive.parentElement);
    expect(
      await screen.findByRole('tooltip', {
        name: 'È collegata a prodotti eliminati: non si può eliminare',
      }),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Elimina Viaggio' })).toBeEnabled();
  });

  it('creates a category and refreshes the menu of the shop', async () => {
    const user = userEvent.setup();
    const bodies = [];
    serveCategories();
    server.use(
      http.post(apiUrl('/api/v1/admin/categories'), async ({ request }) => {
        bodies.push(await request.json());
        return HttpResponse.json(
          { data: buildAdminCategory({ name: 'Ufficio', slug: 'ufficio' }) },
          { status: 201 },
        );
      }),
    );

    const { queryClient } = renderCategories();
    queryClient.setQueryData(queryKeys.categories, buildCategories());

    await user.click(await screen.findByRole('button', { name: 'Nuova categoria' }));
    const dialog = screen.getByRole('dialog', { name: 'Nuova categoria' });
    expect(
      within(dialog).getByText(
        "L'indirizzo della categoria si crea dal nome e non si potrà modificare.",
      ),
    ).toBeInTheDocument();
    expect(within(dialog).queryByLabelText('Slug')).not.toBeInTheDocument();
    await user.type(within(dialog).getByRole('textbox', { name: 'Nome' }), 'Ufficio');
    await user.click(within(dialog).getByRole('button', { name: 'Salva' }));

    expect(await screen.findByText('Categoria creata')).toBeInTheDocument();
    expect(bodies).toEqual([{ name: 'Ufficio', description: null }]);
    expect(queryClient.getQueryState(queryKeys.categories).isInvalidated).toBe(true);
  });

  it('edits a category with the slug read only and shows the errors on the name', async () => {
    const user = userEvent.setup();
    serveCategories();
    server.use(
      http.patch(apiUrl('/api/v1/admin/categories/1'), () =>
        validationError('name', 'Esiste già una categoria con questo nome'),
      ),
    );

    renderCategories();

    await user.click(await screen.findByRole('button', { name: 'Modifica Audio' }));
    const dialog = screen.getByRole('dialog', { name: 'Modifica categoria' });
    const slug = within(dialog).getByRole('textbox', { name: 'Slug' });
    expect(slug).toHaveValue('audio');
    expect(slug).toHaveAttribute('readonly');
    expect(
      within(dialog).getByText(
        "Compare nell'indirizzo del catalogo: /products?category=audio. Non si può modificare.",
      ),
    ).toBeInTheDocument();

    const name = within(dialog).getByRole('textbox', { name: 'Nome' });
    await user.clear(name);
    await user.type(name, 'Scrivania');
    await user.click(within(dialog).getByRole('button', { name: 'Salva' }));

    expect(
      await within(dialog).findByText('Esiste già una categoria con questo nome'),
    ).toBeInTheDocument();
  });

  it('asks for a name before saving', async () => {
    const user = userEvent.setup();
    serveCategories();

    renderCategories();

    await user.click(await screen.findByRole('button', { name: 'Nuova categoria' }));
    await user.click(screen.getByRole('button', { name: 'Salva' }));

    expect(await screen.findByText('Inserisci il nome della categoria.')).toBeInTheDocument();
  });

  it('deletes an empty category after the confirmation', async () => {
    const user = userEvent.setup();
    let deleted = false;
    serveCategories();
    server.use(
      http.delete(apiUrl('/api/v1/admin/categories/6'), () => {
        deleted = true;
        return new HttpResponse(null, { status: 204 });
      }),
    );

    renderCategories();

    await user.click(await screen.findByRole('button', { name: 'Elimina Viaggio' }));
    const dialog = screen.getByRole('dialog', { name: 'Eliminare la categoria «Viaggio»?' });
    await user.click(within(dialog).getByRole('button', { name: 'Elimina la categoria' }));

    expect(await screen.findByText('Categoria eliminata')).toBeInTheDocument();
    expect(deleted).toBe(true);
  });

  it('keeps the dialog open with the message of a 409', async () => {
    const user = userEvent.setup();
    serveCategories();
    server.use(
      http.delete(apiUrl('/api/v1/admin/categories/6'), () =>
        domainError(
          409,
          'category_has_products',
          'È collegata a prodotti eliminati: non si può eliminare.',
        ),
      ),
    );

    renderCategories();

    await user.click(await screen.findByRole('button', { name: 'Elimina Viaggio' }));
    await user.click(screen.getByRole('button', { name: 'Elimina la categoria' }));

    const dialog = screen.getByRole('dialog', { name: 'Eliminare la categoria «Viaggio»?' });
    expect(
      await within(dialog).findByText('È collegata a prodotti eliminati: non si può eliminare.'),
    ).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Mantieni la categoria' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  it('shows the skeleton while the categories load', () => {
    server.use(http.get(apiUrl('/api/v1/admin/categories'), () => delay('infinite')));

    renderCategories();

    expect(screen.getByRole('status', { name: 'Caricamento delle categorie' })).toBeInTheDocument();
  });

  it('offers to retry when the categories do not load', async () => {
    const user = userEvent.setup();
    let calls = 0;
    server.use(
      http.get(apiUrl('/api/v1/admin/categories'), () => {
        calls += 1;
        return calls === 1
          ? new HttpResponse(null, { status: 500 })
          : HttpResponse.json({ data: [AUDIO] });
      }),
    );

    renderCategories();

    expect(
      await screen.findByText('Non è stato possibile caricare le categorie'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(await screen.findByRole('table', { name: 'Categorie' })).toBeInTheDocument();
  });

  it('explains an empty list of categories', async () => {
    serveCategories([]);

    renderCategories();

    expect(await screen.findByText('Non ci sono ancora categorie.')).toBeInTheDocument();
    expect(
      screen.getByText('0 categorie · una categoria con prodotti non si può eliminare'),
    ).toBeInTheDocument();
  });
});
