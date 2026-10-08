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
      shipping: 0,
      total: 0,
      free_shipping_remaining: null,
      can_checkout: false,
      ...summary,
    },
    ...rest,
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
