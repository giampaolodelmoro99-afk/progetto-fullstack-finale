export const queryKeys = {
  me: ['me'],
  cart: ['cart'],
  addresses: ['addresses'],
  categories: ['categories'],
  products: {
    all: ['products'],
    list: (filters) => ['products', 'list', filters],
    detail: (slug) => ['products', 'detail', slug],
    related: (slug) => ['products', 'related', slug],
  },
  orders: {
    all: ['orders'],
    detail: (orderId) => ['orders', 'detail', orderId],
  },
  security: {
    overview: ['security', 'overview'],
    passkeys: ['security', 'passkeys'],
    events: ['security', 'events'],
  },
  settings: {
    shop: ['settings', 'shop'],
    admin: ['settings', 'admin'],
    support: ['settings', 'support'],
  },
  assistant: {
    status: ['assistant', 'status'],
  },
  admin: {
    dashboard: ['admin', 'dashboard'],
    categories: ['admin', 'categories'],
    products: {
      all: ['admin', 'products'],
      list: (filters) => ['admin', 'products', 'list', filters],
      detail: (productId) => ['admin', 'products', 'detail', productId],
    },
    orders: {
      all: ['admin', 'orders'],
      list: (filters) => ['admin', 'orders', 'list', filters],
      detail: (orderId) => ['admin', 'orders', 'detail', orderId],
    },
  },
};
