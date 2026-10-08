import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { queryKeys } from '@/api/queryKeys';
import { domainError, recordPost, renderApp, validationError } from '@/test/authHelpers';
import { buildAddress, buildCart, buildCartItem, buildOrder, buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';

const APPROVED = '4242 4242 4242 4242';
const DECLINED = '4000 0000 0000 0002';

const PISA = buildAddress({ id: 12, is_default: true });
const FIRENZE = buildAddress({
  id: 13,
  street: 'Via dei Servi 15',
  city: 'Firenze',
  postal_code: '50122',
  province: 'FI',
  is_default: false,
});

function charger(overrides = {}) {
  return buildCartItem({
    id: 2,
    quantity: 2,
    unit_price: 5900,
    line_total: 11800,
    product: { name: 'Caricatore Brezza 65 W', slug: 'caricatore-brezza-65-w' },
    ...overrides,
  });
}

function readyCart() {
  return buildCart({
    items: [
      buildCartItem({ id: 1, product: { name: 'Cuffie Aria', slug: 'cuffie-aria' } }),
      charger(),
    ],
    summary: {
      items_count: 3,
      subtotal: 46700,
      shipping_cost: 0,
      total: 46700,
      free_shipping_remaining: 0,
    },
    can_checkout: true,
  });
}

function serveCart(...carts) {
  let requests = 0;
  server.use(
    http.get(apiUrl('/api/v1/cart'), () => {
      const cart = carts[Math.min(requests, carts.length - 1)];
      requests += 1;
      return HttpResponse.json({ data: cart });
    }),
  );

  return () => requests;
}

function serveAddresses(addresses = [PISA, FIRENZE]) {
  server.use(http.get(apiUrl('/api/v1/addresses'), () => HttpResponse.json({ data: addresses })));
}

function renderCheckout(user = buildUser()) {
  return renderApp('/checkout', { user });
}

async function enter(user, label, value) {
  await user.click(screen.getByLabelText(label));
  await user.paste(value);
}

async function fillCard(user, card = {}) {
  const values = { holder: 'Giulia Ferri', number: APPROVED, expiry: '08/39', cvc: '123', ...card };
  await enter(user, 'Intestatario della carta', values.holder);
  await enter(user, 'Numero della carta', values.number);
  await enter(user, 'Scadenza', values.expiry);
  await enter(user, 'CVC', values.cvc);
}

async function reachReview(user, card) {
  await user.click(await screen.findByRole('button', { name: 'Continua al pagamento' }));
  await screen.findByRole('heading', { name: 'Dati della carta' });
  await fillCard(user, card);
  await user.click(screen.getByRole('button', { name: 'Continua al riepilogo' }));
  await screen.findByRole('heading', { name: 'Controlla e paga' });
}

async function acceptAndPay(user) {
  await user.click(
    screen.getByRole('checkbox', { name: /Ho letto e accetto le condizioni di vendita/ }),
  );
  await user.click(screen.getByRole('button', { name: /^Paga 467,00\s€$/ }));
}

function cachedSnapshot(queryClient) {
  return JSON.stringify({
    queries: queryClient
      .getQueryCache()
      .getAll()
      .map((query) => query.state),
    mutations: queryClient
      .getMutationCache()
      .getAll()
      .map((mutation) => mutation.state),
  });
}

describe('CheckoutPage', { timeout: 15000 }, () => {
  it('goes through the three steps and opens the order confirmation', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();
    const orders = recordPost('/api/v1/checkout', () =>
      HttpResponse.json({ data: buildOrder({ id: 42 }) }, { status: 201 }),
    );

    const { router } = renderCheckout();

    expect(
      await screen.findByRole('radio', { name: /Giulia Ferri Via San Martino 42/ }),
    ).toBeChecked();
    expect(screen.getByText('Predefinito')).toBeInTheDocument();
    await reachReview(user);

    const review = within(screen.getByRole('region', { name: 'Controlla e paga' }));
    expect(review.getByText('Via San Martino 42, 56125 Pisa (PI), Italia')).toBeInTheDocument();
    expect(review.getByText('Carta che termina con 4242')).toBeInTheDocument();
    expect(review.getByText('Giulia Ferri · scadenza 08/39')).toBeInTheDocument();
    expect(review.getByText(/Quantità 2 · 59,00\s€ cad\./)).toBeInTheDocument();
    expect(
      review.getByRole('link', { name: 'condizioni di vendita (si apre in una nuova scheda)' }),
    ).toHaveAttribute('href', '/note-legali#condizioni-di-vendita');
    expect(review.getByRole('button', { name: /^Paga 467,00\s€$/ })).toBeDisabled();

    await acceptAndPay(user);

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Grazie per il tuo ordine' }),
    ).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/checkout/success/42');
    expect(orders).toEqual([
      {
        address_id: 12,
        payment: { holder: 'Giulia Ferri', number: APPROVED, expiry: '08/39', cvc: '123' },
        terms_accepted: true,
      },
    ]);
  });

  it('shows the shape of the checkout while the cart is loading', async () => {
    server.use(
      http.get(apiUrl('/api/v1/cart'), async () => {
        await new Promise((resolve) => setTimeout(resolve, 50));
        return HttpResponse.json({ data: readyCart() });
      }),
    );
    serveAddresses();

    renderCheckout();

    expect(screen.getByRole('status', { name: 'Caricamento del checkout' })).toBeInTheDocument();
    expect(
      await screen.findByRole('heading', { name: 'Indirizzo di spedizione' }),
    ).toBeInTheDocument();
  });

  it('sends the address chosen by the customer', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();
    const orders = recordPost('/api/v1/checkout', () =>
      HttpResponse.json({ data: buildOrder({ id: 43 }) }, { status: 201 }),
    );

    renderCheckout();

    await user.click(await screen.findByRole('radio', { name: /Via dei Servi 15/ }));
    await reachReview(user);
    expect(screen.getByText('Via dei Servi 15, 50122 Firenze (FI), Italia')).toBeInTheDocument();
    await acceptAndPay(user);

    await waitFor(() => expect(orders).toHaveLength(1));
    expect(orders[0].address_id).toBe(13);
  });

  it('adds a new address with the italian rules and selects it', async () => {
    const user = userEvent.setup();
    const created = buildAddress({ id: 20, street: 'Corso Italia 3', is_default: false });
    let saved = false;
    serveCart(readyCart());
    server.use(
      http.get(apiUrl('/api/v1/addresses'), () =>
        HttpResponse.json({ data: saved ? [PISA, created] : [PISA] }),
      ),
    );
    const bodies = recordPost('/api/v1/addresses', ({ call }) => {
      if (call === 1) {
        return validationError('phone', 'Il numero di telefono non è valido.');
      }
      saved = true;
      return HttpResponse.json({ data: created }, { status: 201 });
    });

    renderCheckout();

    await user.click(await screen.findByRole('button', { name: 'Aggiungi un indirizzo' }));
    const form = within(screen.getByRole('form', { name: 'Nuovo indirizzo' }));
    expect(form.getByLabelText('Paese')).toHaveValue('Italia');
    expect(form.getByLabelText('Paese')).toBeDisabled();
    await user.type(form.getByLabelText('Nome e cognome'), 'Giulia Ferri');
    await user.type(form.getByLabelText('Via e numero civico'), 'Corso Italia 3');
    await user.type(form.getByLabelText('Città'), 'Pisa');
    await user.type(form.getByLabelText('CAP'), '5612');
    await user.type(form.getByLabelText('Provincia'), 'pi');
    await user.type(form.getByLabelText('Telefono'), '333 123 4567');
    await user.click(form.getByRole('button', { name: 'Salva indirizzo' }));

    expect(await form.findByText('Il CAP deve avere 5 cifre')).toBeInTheDocument();
    expect(bodies).toHaveLength(0);

    await user.type(form.getByLabelText('CAP'), '5');
    await user.click(form.getByRole('button', { name: 'Salva indirizzo' }));

    expect(await form.findByText('Il numero di telefono non è valido.')).toBeInTheDocument();
    expect(bodies[0]).toMatchObject({ postal_code: '56125', province: 'PI', is_default: false });

    await user.click(form.getByRole('button', { name: 'Salva indirizzo' }));

    expect(await screen.findByRole('radio', { name: /Corso Italia 3/ })).toBeChecked();
    expect(screen.queryByRole('form', { name: 'Nuovo indirizzo' })).not.toBeInTheDocument();
  });

  it('opens the address form directly when there are no saved addresses', async () => {
    serveCart(readyCart());
    serveAddresses([]);

    renderCheckout();

    expect(await screen.findByRole('form', { name: 'Nuovo indirizzo' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Annulla' })).not.toBeInTheDocument();
  });

  it('validates the card in the browser', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();

    renderCheckout();

    await user.click(await screen.findByRole('button', { name: 'Continua al pagamento' }));
    await fillCard(user, { number: '4242 4242 4242 4241', expiry: '01/20', cvc: '12' });
    await user.click(screen.getByRole('button', { name: 'Continua al riepilogo' }));

    expect(await screen.findByText('Controlla il numero della carta.')).toBeInTheDocument();
    expect(screen.getByText('La carta è scaduta.')).toBeInTheDocument();
    expect(screen.getByText('Il CVC deve avere 3 cifre.')).toBeInTheDocument();
    expect(screen.getByLabelText('Numero della carta')).toHaveAttribute(
      'autocomplete',
      'cc-number',
    );
    expect(screen.getByLabelText('CVC')).toHaveAttribute('inputmode', 'numeric');
    expect(screen.getByText('Ambiente dimostrativo: nessun addebito reale.')).toBeInTheDocument();
  });

  it('goes back to the card with the data kept after a declined payment', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();
    recordPost('/api/v1/checkout', () =>
      domainError(
        402,
        'payment_failed',
        'La carta è stata rifiutata e non ti è stato addebitato nulla. Controlla i dati della carta e riprova.',
      ),
    );

    const { queryClient, router } = renderCheckout();

    await reachReview(user, { number: DECLINED });
    await acceptAndPay(user);

    expect(await screen.findByText('Pagamento non riuscito')).toBeInTheDocument();
    expect(
      screen.getByText(
        'La carta è stata rifiutata e non ti è stato addebitato nulla. Controlla i dati della carta e riprova.',
      ),
    ).toBeInTheDocument();
    expect(cachedSnapshot(queryClient)).not.toContain('0002');
    expect(cachedSnapshot(queryClient)).not.toContain('"cvc"');
    const [mutation] = queryClient.getMutationCache().getAll();
    expect(mutation.state.variables).toBeUndefined();
    expect(mutation.state.error).not.toHaveProperty('config');
    expect(mutation.state.error).not.toHaveProperty('response');
    expect(router.state.location.search).toBe('');
    expect(router.state.location.state).toBeNull();
    expect(window.localStorage).toHaveLength(0);
    expect(window.sessionStorage).toHaveLength(0);

    await user.click(screen.getByRole('button', { name: 'Modifica la carta' }));

    expect(await screen.findByLabelText('Numero della carta')).toHaveValue(DECLINED);
    expect(screen.getByLabelText('Intestatario della carta')).toHaveValue('Giulia Ferri');
    expect(screen.getByLabelText('CVC')).toHaveValue('123');
  });

  it('keeps the card out of caches, storage and address after a successful payment', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();
    recordPost('/api/v1/checkout', () =>
      HttpResponse.json({ data: buildOrder({ id: 42 }) }, { status: 201 }),
    );

    const { queryClient, router } = renderCheckout();

    await reachReview(user);
    await acceptAndPay(user);

    await screen.findByRole('heading', { level: 1, name: 'Grazie per il tuo ordine' });
    expect(cachedSnapshot(queryClient)).not.toContain('4242 4242');
    expect(cachedSnapshot(queryClient)).not.toContain('"cvc"');
    expect(router.state.location.search).toBe('');
    expect(router.state.location.state).toBeNull();
    expect(window.localStorage).toHaveLength(0);
    expect(window.sessionStorage).toHaveLength(0);
  });

  it('starts again from the first step with empty fields when the page is opened again', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();

    const first = renderCheckout();

    await reachReview(user);
    first.unmount();
    renderCheckout();

    await user.click(await screen.findByRole('button', { name: 'Continua al pagamento' }));
    expect(await screen.findByLabelText('Numero della carta')).toHaveValue('');
    expect(screen.getByLabelText('Intestatario della carta')).toHaveValue('');
  });

  it.each([
    [
      [{ product: 'Caricatore Brezza 65 W', requested: 2, available: 1 }],
      ['Caricatore Brezza 65 W: disponibile 1 su 2 richiesti'],
    ],
    [
      [
        { product: 'Radio Onda Portatile', requested: 1, available: 0 },
        { product: 'Cavo Seta USB-C 1 m', requested: 5, available: 3 },
      ],
      [
        'Radio Onda Portatile: non più disponibile',
        'Cavo Seta USB-C 1 m: disponibili 3 su 5 richiesti',
      ],
    ],
  ])('shows the changed availability after a 409', async (products, lines) => {
    const user = userEvent.setup();
    const changed = buildCart({
      ...readyCart(),
      items: [
        charger({
          exceeds_stock: true,
          max_quantity: 1,
          product: {
            name: 'Caricatore Brezza 65 W',
            availability: { status: 'low_stock', label: 'Ultimo pezzo' },
          },
        }),
      ],
      can_checkout: false,
    });
    const cartRequests = serveCart(readyCart(), changed);
    serveAddresses();
    server.use(
      http.post(apiUrl('/api/v1/checkout'), () =>
        HttpResponse.json(
          {
            message:
              "La disponibilità di alcuni prodotti è cambiata: aggiorna il carrello per completare l'ordine.",
            code: 'insufficient_stock',
            products,
          },
          { status: 409 },
        ),
      ),
    );

    renderCheckout();

    await reachReview(user);
    await acceptAndPay(user);

    expect(await screen.findByText('Disponibilità cambiata')).toBeInTheDocument();
    lines.forEach((line) => expect(screen.getByText(line)).toBeInTheDocument());
    expect(screen.getByRole('link', { name: 'Torna al carrello' })).toHaveAttribute(
      'href',
      '/cart',
    );
    await waitFor(() => expect(cartRequests()).toBe(2));
    const review = within(screen.getByRole('region', { name: 'Controlla e paga' }));
    expect(await review.findByText('Ultimo pezzo')).toBeInTheDocument();
    expect(review.getByRole('button', { name: /^Paga/ })).toBeDisabled();
  });

  it('blocks the payment when the cart changes during the checkout', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();

    const { queryClient } = renderCheckout();

    await reachReview(user);
    queryClient.setQueryData(queryKeys.cart, { ...readyCart(), can_checkout: false });
    await user.click(screen.getByRole('checkbox', { name: /Ho letto e accetto/ }));

    expect(await screen.findByText('Disponibilità cambiata')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^Paga/ })).toBeDisabled();
  });

  it('shows the empty cart conflict with a way back to the cart', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();
    recordPost('/api/v1/checkout', () => domainError(409, 'cart_empty', 'Il carrello è vuoto.'));

    const { router } = renderCheckout();

    await reachReview(user);
    await acceptAndPay(user);

    expect(await screen.findByText('Il carrello è vuoto.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Torna al carrello' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/checkout');
  });

  it('maps the card errors of the server on the payment step', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();
    recordPost('/api/v1/checkout', () => validationError('payment.expiry', 'La carta è scaduta.'));

    renderCheckout();

    await reachReview(user);
    await acceptAndPay(user);

    expect(await screen.findByText('La carta è scaduta.')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Dati della carta' })).toBeInTheDocument();
  });

  it('asks to confirm the email when the server answers 403', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();
    server.use(
      http.get(apiUrl('/api/v1/me'), () =>
        HttpResponse.json({ data: buildUser({ email_verified_at: null }) }),
      ),
    );
    recordPost('/api/v1/checkout', () =>
      HttpResponse.json(
        { message: 'Conferma il tuo indirizzo email per continuare.' },
        { status: 403 },
      ),
    );

    renderCheckout();

    await reachReview(user);
    await acceptAndPay(user);

    expect(
      await screen.findByRole('heading', { name: 'Conferma la tua email' }),
    ).toBeInTheDocument();
  });

  it('shows the email confirmation instead of the address for an unverified customer', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();
    const resends = recordPost('/api/v1/email/verification-notification', () =>
      HttpResponse.json(
        { message: 'Ti abbiamo inviato un nuovo link di conferma.' },
        { status: 202 },
      ),
    );

    renderCheckout(buildUser({ email_verified_at: null }));

    expect(
      await screen.findByRole('heading', { name: 'Conferma la tua email' }),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('heading', { name: 'Indirizzo di spedizione' }),
    ).not.toBeInTheDocument();
    const card = within(screen.getByRole('region', { name: 'Conferma la tua email' }));
    expect(
      card.getByText('Non la trovi? Controlla anche nella cartella spam.'),
    ).toBeInTheDocument();

    await user.click(card.getByRole('button', { name: "Invia di nuovo l'email" }));

    await waitFor(() => expect(resends).toHaveLength(1));
  });

  it.each([
    ['an empty cart', buildCart()],
    ['a cart that cannot be checked out', { ...readyCart(), can_checkout: false }],
  ])('sends the customer back to the cart when opened with %s', async (_, cart) => {
    serveCart(cart);
    serveAddresses();

    const { router } = renderCheckout();

    await waitFor(() => expect(router.state.location.pathname).toBe('/cart'));
    expect(router.state.historyAction).toBe('REPLACE');
  });

  it('always reaches the confirmation even if the cart is emptied right after the payment', async () => {
    const user = userEvent.setup();
    let paid = false;
    server.use(
      http.get(apiUrl('/api/v1/cart'), () =>
        HttpResponse.json({ data: paid ? buildCart() : readyCart() }),
      ),
    );
    serveAddresses();
    recordPost('/api/v1/checkout', () => {
      paid = true;
      return HttpResponse.json({ data: buildOrder({ id: 42 }) }, { status: 201 });
    });

    const { router, queryClient } = renderCheckout();
    const visited = [];
    router.subscribe((state) => visited.push(state.location.pathname));

    await reachReview(user);
    await acceptAndPay(user);

    await screen.findByRole('heading', { level: 1, name: 'Grazie per il tuo ordine' });
    await waitFor(() => expect(queryClient.getQueryData(queryKeys.cart).items).toHaveLength(0));
    expect(visited).not.toContain('/cart');
    expect(router.state.location.pathname).toBe('/checkout/success/42');
  });

  it('shows an error with retry when the cart cannot be loaded', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    server.use(
      http.get(apiUrl('/api/v1/cart'), () => {
        attempts += 1;
        return attempts === 1
          ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
          : HttpResponse.json({ data: readyCart() });
      }),
    );
    serveAddresses();

    renderCheckout();

    expect(
      await screen.findByText('Non è stato possibile caricare il carrello'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(
      await screen.findByRole('heading', { name: 'Indirizzo di spedizione' }),
    ).toBeInTheDocument();
  });

  it('shows the addresses loading and an error with retry', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    serveCart(readyCart());
    server.use(
      http.get(apiUrl('/api/v1/addresses'), async () => {
        attempts += 1;
        await new Promise((resolve) => setTimeout(resolve, 20));
        return attempts === 1
          ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
          : HttpResponse.json({ data: [PISA] });
      }),
    );

    renderCheckout();

    expect(
      await screen.findByRole('status', { name: 'Caricamento degli indirizzi' }),
    ).toBeInTheDocument();
    expect(
      await screen.findByText('Non è stato possibile caricare i tuoi indirizzi'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(await screen.findByRole('radio', { name: /Via San Martino 42/ })).toBeChecked();
  });

  it('goes back to the addresses and reloads them when the address is no longer valid', async () => {
    const user = userEvent.setup();
    let removed = false;
    serveCart(readyCart());
    server.use(
      http.get(apiUrl('/api/v1/addresses'), () =>
        HttpResponse.json({ data: removed ? [FIRENZE] : [PISA, FIRENZE] }),
      ),
    );
    recordPost('/api/v1/checkout', () => {
      removed = true;
      return validationError('address_id', 'Scegli un indirizzo valido per la spedizione.');
    });

    renderCheckout();

    await reachReview(user);
    await acceptAndPay(user);

    expect(
      await screen.findByText('Scegli un indirizzo valido per la spedizione.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Indirizzo di spedizione' })).toBeInTheDocument();
    await waitFor(() =>
      expect(screen.queryByRole('radio', { name: /Via San Martino 42/ })).not.toBeInTheDocument(),
    );
    expect(screen.getByRole('radio', { name: /Via dei Servi 15/ })).toBeChecked();
  });

  it('shows the terms error under the checkbox', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();
    recordPost('/api/v1/checkout', () =>
      validationError('terms_accepted', 'Devi accettare le condizioni di vendita.'),
    );

    renderCheckout();

    await reachReview(user);
    await acceptAndPay(user);

    expect(await screen.findByText('Devi accettare le condizioni di vendita.')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Controlla e paga' })).toBeInTheDocument();
  });

  it('shows a generic error and stays on the review after a server failure', async () => {
    const user = userEvent.setup();
    serveCart(readyCart());
    serveAddresses();
    recordPost('/api/v1/checkout', () =>
      HttpResponse.json({ message: 'Server Error' }, { status: 500 }),
    );

    const { router } = renderCheckout();

    await reachReview(user);
    await acceptAndPay(user);

    expect(
      await screen.findByText('Si è verificato un errore imprevisto. Riprova più tardi.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Controlla e paga' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/checkout');
  });

  it('shows the progress and the fixed bar on mobile', async () => {
    const user = userEvent.setup();
    setViewportWidth(MOBILE_WIDTH);
    serveCart(readyCart());
    serveAddresses();

    renderCheckout();

    expect(await screen.findByText('Passo 1 di 3')).toBeInTheDocument();
    expect(screen.getByText('Poi: Pagamento')).toBeInTheDocument();
    expect(screen.getByText('Totale, IVA inclusa')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Riepilogo ordine/ })).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: 'Continua al pagamento' })).toHaveLength(1);

    await user.click(screen.getByRole('button', { name: 'Continua al pagamento' }));

    expect(await screen.findByText('Passo 2 di 3')).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: 'Continua al riepilogo' })).toHaveLength(1);
  });
});
