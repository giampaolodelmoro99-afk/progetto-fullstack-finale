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
