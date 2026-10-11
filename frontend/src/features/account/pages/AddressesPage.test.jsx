import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderApp, validationError } from '@/test/authHelpers';
import { buildAddress, buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';

const PISA = buildAddress({ id: 1, is_default: true });
const FIRENZE = buildAddress({
  id: 2,
  street: 'Via dei Servi 15',
  city: 'Firenze',
  postal_code: '50122',
  province: 'FI',
  is_default: false,
});

function serveAddresses(...lists) {
  let call = 0;
  server.use(
    http.get(apiUrl('/api/v1/addresses'), () => {
      const list = lists[Math.min(call, lists.length - 1)];
      call += 1;
      return HttpResponse.json({ data: list });
    }),
  );
}

function record(method, path, respond) {
  const requests = [];
  server.use(
    http[method](apiUrl(path), async ({ request, params }) => {
      const text = await request.text();
      requests.push({ params, body: text === '' ? null : JSON.parse(text) });
      return respond();
    }),
  );

  return requests;
}

function renderAddresses() {
  return renderApp('/account/addresses', { user: buildUser() });
}

function card(street, city) {
  return within(screen.getByRole('article', { name: `Indirizzo ${street}, ${city}` }));
}

describe('AddressesPage', () => {
  it('shows the addresses with the default one first', async () => {
    serveAddresses([PISA, FIRENZE]);

    renderAddresses();

    const pisa = within(
      await screen.findByRole('article', { name: 'Indirizzo Via San Martino 42, Pisa' }),
    );
    expect(pisa.getByText('Predefinito')).toBeInTheDocument();
    expect(pisa.getByText('56125 Pisa (PI), Italia')).toBeInTheDocument();
    expect(pisa.getByText('+39 333 123 4567')).toBeInTheDocument();
    expect(
      pisa.queryByRole('button', { name: /Imposta come predefinito/ }),
    ).not.toBeInTheDocument();
    const firenze = card('Via dei Servi 15', 'Firenze');
    expect(firenze.queryByText('Predefinito')).not.toBeInTheDocument();
    expect(
      firenze.getByRole('button', {
        name: "Imposta come predefinito l'indirizzo Via dei Servi 15, Firenze",
      }),
    ).toBeInTheDocument();
    const articles = screen.getAllByRole('article');
    expect(articles[0]).toHaveAccessibleName('Indirizzo Via San Martino 42, Pisa');
  });

  it('adds an address from the dialog', async () => {
    const user = userEvent.setup();
    const lucca = buildAddress({
      id: 3,
      street: 'Via Fillungo 88',
      city: 'Lucca',
      postal_code: '55100',
      province: 'LU',
      is_default: false,
    });
    serveAddresses([PISA], [PISA, lucca]);
    const requests = record('post', '/api/v1/addresses', () =>
      HttpResponse.json({ data: lucca }, { status: 201 }),
    );

    renderAddresses();

    await user.click(await screen.findByRole('button', { name: 'Aggiungi un indirizzo' }));
    const dialog = within(await screen.findByRole('dialog', { name: 'Nuovo indirizzo' }));
    expect(dialog.getByLabelText('Paese')).toBeDisabled();
    await user.type(dialog.getByLabelText('Nome e cognome'), 'Giulia Ferri');
    await user.type(dialog.getByLabelText('Via e numero civico'), 'Via Fillungo 88');
    await user.type(dialog.getByLabelText('Città'), 'Lucca');
    await user.type(dialog.getByLabelText('CAP'), '55100');
    await user.type(dialog.getByLabelText('Provincia'), 'lu');
    await user.type(dialog.getByLabelText('Telefono'), '+39 333 123 4567');
    await user.click(dialog.getByRole('button', { name: 'Salva indirizzo' }));

    expect(await screen.findByText('Indirizzo salvato')).toBeInTheDocument();
    expect(requests.map((request) => request.body)).toEqual([
      {
        full_name: 'Giulia Ferri',
        street: 'Via Fillungo 88',
        city: 'Lucca',
        postal_code: '55100',
        province: 'LU',
        phone: '+39 333 123 4567',
        is_default: false,
      },
    ]);
    expect(
      await screen.findByRole('article', { name: 'Indirizzo Via Fillungo 88, Lucca' }),
    ).toBeInTheDocument();
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  it('validates the new address before sending it', async () => {
    const user = userEvent.setup();
    serveAddresses([PISA]);
    const requests = record('post', '/api/v1/addresses', () =>
      HttpResponse.json({ data: PISA }, { status: 201 }),
    );

    renderAddresses();

    await user.click(await screen.findByRole('button', { name: 'Aggiungi un indirizzo' }));
    const dialog = within(await screen.findByRole('dialog', { name: 'Nuovo indirizzo' }));
    await user.type(dialog.getByLabelText('CAP'), '561');
    await user.click(dialog.getByRole('button', { name: 'Salva indirizzo' }));

    expect(await dialog.findByText('Il CAP deve avere 5 cifre')).toBeInTheDocument();
    expect(dialog.getByText('Inserisci nome e cognome.')).toBeInTheDocument();
    expect(requests).toEqual([]);
  });

  it('edits an address with the saved values', async () => {
    const user = userEvent.setup();
    serveAddresses([PISA, FIRENZE]);
    const requests = record('patch', '/api/v1/addresses/:addressId', () =>
      HttpResponse.json({ data: { ...FIRENZE, phone: '+39 055 123 456' } }),
    );

    renderAddresses();

    await screen.findByRole('article', { name: 'Indirizzo Via dei Servi 15, Firenze' });
    await user.click(
      card('Via dei Servi 15', 'Firenze').getByRole('button', {
        name: "Modifica l'indirizzo Via dei Servi 15, Firenze",
      }),
    );
    const dialog = within(await screen.findByRole('dialog', { name: 'Modifica indirizzo' }));
    expect(dialog.getByLabelText('Via e numero civico')).toHaveValue('Via dei Servi 15');
    expect(dialog.getByLabelText('Imposta come predefinito')).not.toBeChecked();
    await user.clear(dialog.getByLabelText('Telefono'));
    await user.type(dialog.getByLabelText('Telefono'), '+39 055 123 456');
    await user.click(dialog.getByRole('button', { name: 'Salva indirizzo' }));

    expect(await screen.findByText('Indirizzo aggiornato')).toBeInTheDocument();
    expect(requests).toEqual([
      {
        params: { addressId: '2' },
        body: {
          full_name: 'Giulia Ferri',
          street: 'Via dei Servi 15',
          city: 'Firenze',
          postal_code: '50122',
          province: 'FI',
          phone: '+39 055 123 456',
          is_default: false,
        },
      },
    ]);
  });

  it('hides the default checkbox when editing the default address', async () => {
    const user = userEvent.setup();
    serveAddresses([PISA, FIRENZE]);

    renderAddresses();

    await screen.findByRole('article', { name: 'Indirizzo Via San Martino 42, Pisa' });
    await user.click(
      screen.getByRole('button', { name: "Modifica l'indirizzo Via San Martino 42, Pisa" }),
    );
    const dialog = within(await screen.findByRole('dialog', { name: 'Modifica indirizzo' }));

    expect(dialog.getByLabelText('Nome e cognome')).toHaveValue('Giulia Ferri');
    expect(dialog.queryByLabelText('Imposta come predefinito')).not.toBeInTheDocument();
  });

  it('shows the server validation errors in the dialog', async () => {
    const user = userEvent.setup();
    serveAddresses([PISA]);
    server.use(
      http.patch(apiUrl('/api/v1/addresses/:addressId'), () =>
        validationError('province', 'Usa la sigla di due lettere della provincia, per esempio PI.'),
      ),
    );

    renderAddresses();

    await user.click(
      await screen.findByRole('button', { name: "Modifica l'indirizzo Via San Martino 42, Pisa" }),
    );
    const dialog = within(await screen.findByRole('dialog', { name: 'Modifica indirizzo' }));
    await user.click(dialog.getByRole('button', { name: 'Salva indirizzo' }));

    expect(
      await dialog.findByText('Usa la sigla di due lettere della provincia, per esempio PI.'),
    ).toBeInTheDocument();
  });

  it('makes an address the default one', async () => {
    const user = userEvent.setup();
    serveAddresses(
      [PISA, FIRENZE],
      [
        { ...FIRENZE, is_default: true },
        { ...PISA, is_default: false },
      ],
    );
    const requests = record('patch', '/api/v1/addresses/:addressId', () =>
      HttpResponse.json({ data: { ...FIRENZE, is_default: true } }),
    );

    renderAddresses();

    await user.click(
      await screen.findByRole('button', {
        name: "Imposta come predefinito l'indirizzo Via dei Servi 15, Firenze",
      }),
    );

    expect(await screen.findByText('Indirizzo predefinito aggiornato')).toBeInTheDocument();
    expect(requests).toEqual([{ params: { addressId: '2' }, body: { is_default: true } }]);
    await waitFor(() =>
      expect(card('Via dei Servi 15', 'Firenze').getByText('Predefinito')).toBeInTheDocument(),
    );
  });

  it('keeps the address when the deletion is not confirmed', async () => {
    const user = userEvent.setup();
    serveAddresses([PISA, FIRENZE]);
    const requests = record(
      'delete',
      '/api/v1/addresses/:addressId',
      () => new HttpResponse(null, { status: 204 }),
    );

    renderAddresses();

    await user.click(
      await screen.findByRole('button', { name: "Elimina l'indirizzo Via dei Servi 15, Firenze" }),
    );
    const dialog = within(
      await screen.findByRole('dialog', { name: 'Vuoi eliminare questo indirizzo?' }),
    );
    expect(
      dialog.getByText(/Giulia Ferri, Via dei Servi 15, 50122 Firenze \(FI\), Italia/),
    ).toBeInTheDocument();
    expect(
      dialog.queryByText("Diventerà predefinito l'indirizzo aggiunto più di recente."),
    ).not.toBeInTheDocument();
    await user.click(dialog.getByRole('button', { name: "Mantieni l'indirizzo" }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(requests).toEqual([]);
  });

  it('deletes the default address after the confirmation', async () => {
    const user = userEvent.setup();
    serveAddresses([PISA, FIRENZE], [{ ...FIRENZE, is_default: true }]);
    const requests = record(
      'delete',
      '/api/v1/addresses/:addressId',
      () => new HttpResponse(null, { status: 204 }),
    );

    renderAddresses();

    await user.click(
      await screen.findByRole('button', { name: "Elimina l'indirizzo Via San Martino 42, Pisa" }),
    );
    const dialog = within(
      await screen.findByRole('dialog', { name: 'Vuoi eliminare questo indirizzo?' }),
    );
    expect(
      dialog.getByText("Diventerà predefinito l'indirizzo aggiunto più di recente."),
    ).toBeInTheDocument();
    await user.click(dialog.getByRole('button', { name: "Elimina l'indirizzo" }));

    expect(await screen.findByText('Indirizzo eliminato')).toBeInTheDocument();
    expect(requests).toEqual([{ params: { addressId: '1' }, body: null }]);
    await waitFor(() =>
      expect(
        screen.queryByRole('article', { name: 'Indirizzo Via San Martino 42, Pisa' }),
      ).not.toBeInTheDocument(),
    );
  });

  it('shows the error of a failed deletion in the dialog', async () => {
    const user = userEvent.setup();
    serveAddresses([PISA]);
    server.use(
      http.delete(apiUrl('/api/v1/addresses/:addressId'), () =>
        HttpResponse.json({ message: 'Errore.' }, { status: 500 }),
      ),
    );

    renderAddresses();

    await user.click(
      await screen.findByRole('button', { name: "Elimina l'indirizzo Via San Martino 42, Pisa" }),
    );
    const dialog = within(
      await screen.findByRole('dialog', { name: 'Vuoi eliminare questo indirizzo?' }),
    );
    await user.click(dialog.getByRole('button', { name: "Elimina l'indirizzo" }));

    expect(
      await dialog.findByText('Si è verificato un errore imprevisto. Riprova più tardi.'),
    ).toBeInTheDocument();
  });

  it('opens the address dialog full screen on mobile', async () => {
    const user = userEvent.setup();
    setViewportWidth(MOBILE_WIDTH);
    serveAddresses([PISA]);

    renderAddresses();

    await user.click(await screen.findByRole('button', { name: 'Aggiungi un indirizzo' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nuovo indirizzo' });

    expect(dialog.className).toMatch(/MuiDialog-paperFullScreen/);
  });

  it('shows the shape of the addresses while loading', async () => {
    server.use(
      http.get(apiUrl('/api/v1/addresses'), async () => {
        await new Promise((resolve) => setTimeout(resolve, 50));
        return HttpResponse.json({ data: [PISA] });
      }),
    );

    renderAddresses();

    expect(
      await screen.findByRole('status', { name: 'Caricamento degli indirizzi' }),
    ).toBeInTheDocument();
    expect(
      await screen.findByRole('article', { name: 'Indirizzo Via San Martino 42, Pisa' }),
    ).toBeInTheDocument();
  });

  it('shows an error with retry', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    server.use(
      http.get(apiUrl('/api/v1/addresses'), () => {
        attempts += 1;
        return attempts === 1
          ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
          : HttpResponse.json({ data: [PISA] });
      }),
    );

    renderAddresses();

    expect(
      await screen.findByText('Non è stato possibile caricare i tuoi indirizzi'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(
      await screen.findByRole('article', { name: 'Indirizzo Via San Martino 42, Pisa' }),
    ).toBeInTheDocument();
  });

  it('invites to add the first address', async () => {
    const user = userEvent.setup();
    serveAddresses([]);

    renderAddresses();

    expect(await screen.findByText('Non hai ancora salvato indirizzi')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Aggiungi un indirizzo' }));

    expect(await screen.findByRole('dialog', { name: 'Nuovo indirizzo' })).toBeInTheDocument();
  });
});
