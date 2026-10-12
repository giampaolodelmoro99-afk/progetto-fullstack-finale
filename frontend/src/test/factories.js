let sequence = 0;

function nextId() {
  sequence += 1;

  return sequence;
}

export function buildUser(overrides = {}) {
  return {
    id: nextId(),
    name: 'Giulia Ferri',
    email: 'giulia.ferri@esempio.it',
    email_verified_at: '2026-09-01T10:00:00+02:00',
    role: { value: 'customer', label: 'Cliente' },
    pending_email_change: null,
    created_at: '2026-09-01T09:30:00+02:00',
    ...overrides,
  };
}

export function buildAdmin(overrides = {}) {
  return buildUser({
    name: 'Marta Lenzi',
    email: 'marta.lenzi@esempio.it',
    role: { value: 'admin', label: 'Amministratore' },
    ...overrides,
  });
}

export function buildCategory(overrides = {}) {
  return {
    id: nextId(),
    name: 'Audio',
    slug: 'audio',
    description: 'Cuffie, auricolari, diffusori',
    ...overrides,
  };
}

export function buildCategories() {
  return [
    buildCategory({ name: 'Audio', slug: 'audio', description: 'Cuffie, auricolari, diffusori' }),
    buildCategory({
      name: 'Ricarica ed energia',
      slug: 'ricarica-ed-energia',
      description: 'Caricatori, cavi, batterie',
    }),
    buildCategory({
      name: 'Scrivania',
      slug: 'scrivania',
      description: 'Supporti, tastiere, accessori',
    }),
    buildCategory({ name: 'Indossabili', slug: 'indossabili', description: 'Orologi e cinturini' }),
    buildCategory({ name: 'Casa smart', slug: 'casa-smart', description: 'Luci e clima' }),
  ];
}

export function buildCart(overrides = {}) {
  const { summary, ...rest } = overrides;

  return {
    items: [],
    summary: {
      items_count: 0,
      subtotal: 0,
      shipping_cost: 0,
      total: 0,
      free_shipping_threshold: 9900,
      free_shipping_remaining: null,
      ...summary,
    },
    can_checkout: false,
    ...rest,
  };
}

export function buildCartItem(overrides = {}) {
  const { product, ...rest } = overrides;
  const id = nextId();
  const quantity = rest.quantity ?? 1;
  const unitPrice = rest.unit_price ?? 34900;

  return {
    id,
    quantity,
    max_quantity: 10,
    exceeds_stock: false,
    is_purchasable: true,
    unit_price: unitPrice,
    line_total: unitPrice * quantity,
    product: {
      name: 'Cuffie Aria',
      slug: `cuffie-aria-${id}`,
      category: { name: 'Audio', slug: 'audio' },
      cover: { url: `https://cdn.esempio.it/products/${id}.webp`, alt: 'Cuffie Aria grafite' },
      availability: { status: 'available', label: 'Disponibile' },
      ...product,
    },
    ...rest,
  };
}

export function buildAddress(overrides = {}) {
  return {
    id: nextId(),
    full_name: 'Giulia Ferri',
    street: 'Via San Martino 42',
    city: 'Pisa',
    postal_code: '56125',
    province: 'PI',
    country: 'IT',
    phone: '+39 333 123 4567',
    is_default: true,
    ...overrides,
  };
}

export function buildOrder(overrides = {}) {
  return {
    id: 42,
    number: 'ORD-2026-000042',
    status: { value: 'paid', label: 'Pagato' },
    created_at: '2026-09-28T18:41:00+02:00',
    paid_at: '2026-09-28T18:42:00+02:00',
    subtotal: 49600,
    shipping_cost: 0,
    total: 49600,
    items_count: 4,
    can_cancel: true,
    shipping_address: {
      full_name: 'Giulia Ferri',
      street: 'Via San Martino 42',
      postal_code: '56125',
      city: 'Pisa',
      province: 'PI',
      country: 'IT',
      phone: '+39 333 123 4567',
    },
    payment: { card_last_four: '4242' },
    items: [
      {
        product_name: 'Cuffie Aria',
        product_sku: 'CDS-AUD-001',
        unit_price: 34900,
        quantity: 1,
        line_total: 34900,
        cover: { url: 'https://cdn.esempio.it/products/cuffie-aria.webp', alt: 'Cuffie Aria' },
      },
    ],
    status_history: [],
    ...overrides,
  };
}

export function buildOrderPage(orders = [], meta = {}) {
  return {
    data: orders,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: 1,
      from: orders.length > 0 ? 1 : null,
      last_page: 1,
      per_page: 10,
      to: orders.length > 0 ? orders.length : null,
      total: orders.length,
      path: 'http://localhost:8000/api/v1/orders',
      ...meta,
    },
  };
}

export function buildProduct(overrides = {}) {
  const id = nextId();

  return {
    id,
    name: 'Cuffie Aria',
    slug: `cuffie-aria-${id}`,
    price: 34900,
    is_new: false,
    availability: { status: 'available', label: 'Disponibile' },
    category: { name: 'Audio', slug: 'audio' },
    cover: { url: `https://cdn.esempio.it/products/${id}.webp`, alt: 'Cuffie Aria grafite' },
    ...overrides,
  };
}

export function buildProductDetail(overrides = {}) {
  const product = buildProduct(overrides);

  return {
    short_description: 'Cuffie wireless con cancellazione del rumore adattiva.',
    description: 'Padiglioni in alluminio anodizzato.\n\nFino a 40 ore di ascolto.',
    specifications: [
      { label: 'Peso', value: '250 g' },
      { label: 'Autonomia', value: '40 ore' },
    ],
    images: [
      { url: `https://cdn.esempio.it/products/${product.id}-front.webp`, alt: 'Vista frontale' },
      {
        url: `https://cdn.esempio.it/products/${product.id}-angle.webp`,
        alt: 'Vista di tre quarti',
      },
    ],
    max_quantity: 10,
    ...product,
    ...overrides,
  };
}

export function buildProductPage(products = [], meta = {}) {
  return {
    data: products,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: 1,
      from: products.length > 0 ? 1 : null,
      last_page: 1,
      per_page: 12,
      to: products.length > 0 ? products.length : null,
      total: products.length,
      path: 'http://localhost:8000/api/v1/products',
      price_range: { min: 1900, max: 149900 },
      ...meta,
    },
  };
}

export function buildShopSettings(overrides = {}) {
  return {
    shipping_cost: 690,
    free_shipping_threshold: 9900,
    ...overrides,
  };
}

export function buildAdminSettings(overrides = {}) {
  return {
    ...buildShopSettings(),
    low_stock_threshold: 5,
    order_statuses: [
      { value: 'pending', label: 'In attesa' },
      { value: 'paid', label: 'Pagato' },
      { value: 'shipped', label: 'Spedito' },
      { value: 'delivered', label: 'Consegnato' },
      { value: 'cancelled', label: 'Annullato' },
    ],
    ...overrides,
  };
}

function buildPage(data, path, meta) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: 1,
      from: data.length > 0 ? 1 : null,
      last_page: 1,
      per_page: 10,
      to: data.length > 0 ? data.length : null,
      total: data.length,
      path: `http://localhost:8000${path}`,
      ...meta,
    },
  };
}

export function buildAdminCategory(overrides = {}) {
  return {
    ...buildCategory(),
    products_count: 24,
    can_delete: false,
    ...overrides,
  };
}

export function buildAdminCategories() {
  return [
    buildAdminCategory({ name: 'Audio', slug: 'audio', products_count: 24 }),
    buildAdminCategory({
      name: 'Ricarica ed energia',
      slug: 'ricarica-ed-energia',
      description: 'Caricatori, cavi, batterie',
      products_count: 8,
    }),
    buildAdminCategory({
      name: 'Viaggio',
      slug: 'viaggio',
      description: 'Custodie e adattatori per chi viaggia',
      products_count: 0,
      can_delete: true,
    }),
  ];
}

export function buildAdminProductImage(overrides = {}) {
  const id = nextId();

  return {
    id,
    url: `https://cdn.esempio.it/products/${id}.webp`,
    alt: 'Cuffie Aria',
    position: 1,
    ...overrides,
  };
}

export function buildAdminProduct(overrides = {}) {
  const id = overrides.id ?? nextId();

  return {
    id,
    name: 'Cuffie Aria',
    slug: `cuffie-aria-${id}`,
    sku: 'CDS-AUD-001',
    category: { id: 1, name: 'Audio', slug: 'audio' },
    short_description: 'Cuffie over-ear con cancellazione del rumore adattiva.',
    description: 'Padiglioni in alluminio anodizzato.',
    specifications: [
      { label: 'Driver', value: '40 mm' },
      { label: 'Autonomia', value: '40 ore' },
    ],
    price: 34900,
    stock: 18,
    stock_status: { status: 'available', label: 'Disponibile' },
    is_active: true,
    cover: { url: `https://cdn.esempio.it/products/${id}.webp`, alt: 'Cuffie Aria' },
    images: [],
    updated_at: '2026-09-26T14:03:00+02:00',
    ...overrides,
  };
}

export function buildAdminProductPage(products = [], meta = {}) {
  return buildPage(products, '/api/v1/admin/products', meta);
}

export function buildAdminOrderSummary(overrides = {}) {
  return {
    id: 42,
    number: 'ORD-2026-000042',
    customer: { name: 'Giulia Ferri', email: 'giulia.ferri@esempio.it' },
    created_at: '2026-09-28T18:41:00+02:00',
    items_count: 4,
    total: 49600,
    status: { value: 'paid', label: 'Pagato' },
    failed_payment: false,
    ...overrides,
  };
}

export function buildAdminOrderPage(orders = [], meta = {}) {
  return buildPage(orders, '/api/v1/admin/orders', meta);
}

export function buildAdminOrder(overrides = {}) {
  return {
    ...buildAdminOrderSummary(),
    paid_at: '2026-09-28T18:42:00+02:00',
    subtotal: 49600,
    shipping_cost: 0,
    can_cancel: true,
    allowed_transitions: ['shipped', 'cancelled'],
    shipping_address: {
      full_name: 'Giulia Ferri',
      street: 'Via San Martino 42',
      postal_code: '56125',
      city: 'Pisa',
      province: 'PI',
      country: 'IT',
      phone: '+39 333 123 4567',
    },
    payment: {
      card_last_four: '4242',
      status: { value: 'succeeded', label: 'Riuscito' },
      amount: 49600,
      created_at: '2026-09-28T18:42:00+02:00',
    },
    items: [
      {
        product_name: 'Cuffie Aria',
        product_sku: 'CDS-AUD-001',
        unit_price: 34900,
        quantity: 1,
        line_total: 34900,
        cover: { url: 'https://cdn.esempio.it/products/cuffie-aria.webp', alt: 'Cuffie Aria' },
      },
      {
        product_name: 'Caricatore Brezza 65 W',
        product_sku: 'CDS-RIC-001',
        unit_price: 5900,
        quantity: 2,
        line_total: 11800,
        cover: null,
      },
    ],
    status_history: [
      {
        status: { value: 'pending', label: 'In attesa' },
        description: 'Ordine ricevuto, in attesa del pagamento',
        changed_by: null,
        reason: null,
        created_at: '2026-09-28T18:41:00+02:00',
      },
      {
        status: { value: 'paid', label: 'Pagato' },
        description: 'Pagamento con carta riuscito',
        changed_by: null,
        reason: null,
        created_at: '2026-09-28T18:42:00+02:00',
      },
    ],
    ...overrides,
  };
}

export function buildDashboard(overrides = {}) {
  return {
    period: { month: '2026-09' },
    revenue_month: 874200,
    orders_month: 24,
    orders_to_ship: 6,
    low_stock_count: 4,
    orders_by_status: [
      { status: { value: 'paid', label: 'Pagato' }, count: 6 },
      { status: { value: 'shipped', label: 'Spedito' }, count: 5 },
      { status: { value: 'delivered', label: 'Consegnato' }, count: 11 },
      { status: { value: 'cancelled', label: 'Annullato' }, count: 2 },
    ],
    latest_orders: [
      {
        id: 47,
        number: 'ORD-2026-000047',
        customer: { name: 'Marco Bellini', email: 'marco.bellini@esempio.it' },
        created_at: '2026-09-29T15:10:00+02:00',
        total: 69800,
        status: { value: 'paid', label: 'Pagato' },
      },
    ],
    low_stock_products: [
      {
        id: 31,
        name: 'Radio Onda Portatile',
        stock: 0,
        stock_status: { status: 'out_of_stock', label: 'Esaurito' },
        cover: null,
      },
    ],
    ...overrides,
  };
}

export function buildSupportSections() {
  return [
    {
      id: 'spedizioni',
      title: 'Spedizioni e consegne',
      paragraphs: ['Consegna in 24/48 ore in tutta Italia.'],
    },
    { id: 'resi', title: 'Resi e rimborsi', paragraphs: ['Hai 30 giorni per il reso.'] },
    { id: 'garanzia', title: 'Garanzia', paragraphs: ['Due anni su tutti i prodotti.'] },
    { id: 'contatti', title: 'Contatti', paragraphs: ['Scrivici quando vuoi.'] },
  ];
}

export function buildSecurityOverview(overrides = {}) {
  return {
    two_factor_enabled: false,
    two_factor_confirmed_at: null,
    recovery_codes_remaining: 0,
    recovery_codes_total: 0,
    passkeys_count: 0,
    email_verified: true,
    password_changed_at: '2026-09-12T21:31:00+02:00',
    ...overrides,
  };
}

export function buildTwoFactorOverview(overrides = {}) {
  return buildSecurityOverview({
    two_factor_enabled: true,
    two_factor_confirmed_at: '2026-09-12T21:40:00+02:00',
    recovery_codes_remaining: 6,
    recovery_codes_total: 8,
    ...overrides,
  });
}

export function buildPasskey(overrides = {}) {
  return {
    id: nextId(),
    name: 'MacBook di Giulia',
    created_at: '2026-09-20T18:02:00+02:00',
    last_used_at: '2026-09-29T09:14:00+02:00',
    ...overrides,
  };
}

export function buildSecurityEvent(overrides = {}) {
  return {
    id: nextId(),
    type: 'login',
    label: 'Accesso effettuato',
    device: 'Chrome su macOS',
    ip_address: '93.45.112.18',
    passkey_name: null,
    created_at: '2026-09-29T09:14:00+02:00',
    ...overrides,
  };
}

export function buildSecurityEventPage(events = [], meta = {}) {
  return {
    data: events,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: 1,
      from: events.length > 0 ? 1 : null,
      last_page: 1,
      per_page: 10,
      to: events.length > 0 ? events.length : null,
      total: events.length,
      path: 'http://localhost:8000/api/v1/me/security-events',
      ...meta,
    },
  };
}
