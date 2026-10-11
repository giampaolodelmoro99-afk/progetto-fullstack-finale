import { http, HttpResponse } from 'msw';
import {
  buildAddress,
  buildAdminSettings,
  buildCart,
  buildCategories,
  buildOrder,
  buildOrderPage,
  buildProductDetail,
  buildProductPage,
  buildSecurityEventPage,
  buildSecurityOverview,
  buildShopSettings,
  buildSupportSections,
} from '@/test/factories';

export function apiUrl(path) {
  return `${import.meta.env.VITE_API_URL}${path}`;
}

export function unauthenticated() {
  return HttpResponse.json({ message: 'Devi accedere per continuare.' }, { status: 401 });
}

export const handlers = [
  http.get(apiUrl('/sanctum/csrf-cookie'), () => new HttpResponse(null, { status: 204 })),
  http.get(apiUrl('/api/v1/me'), () => unauthenticated()),
  http.post(apiUrl('/api/v1/logout'), () => new HttpResponse(null, { status: 204 })),
  http.get(apiUrl('/api/v1/categories'), () => HttpResponse.json({ data: buildCategories() })),
  http.get(apiUrl('/api/v1/products'), () => HttpResponse.json(buildProductPage())),
  http.get(apiUrl('/api/v1/products/:slug'), ({ params }) =>
    HttpResponse.json({ data: buildProductDetail({ slug: params.slug }) }),
  ),
  http.get(apiUrl('/api/v1/products/:slug/related'), () => HttpResponse.json({ data: [] })),
  http.get(apiUrl('/api/v1/cart'), () => HttpResponse.json({ data: buildCart() })),
  http.get(apiUrl('/api/v1/addresses'), () => HttpResponse.json({ data: [buildAddress()] })),
  http.get(apiUrl('/api/v1/orders'), () => HttpResponse.json(buildOrderPage([buildOrder()]))),
  http.get(apiUrl('/api/v1/orders/:orderId'), ({ params }) =>
    HttpResponse.json({ data: buildOrder({ id: Number(params.orderId) }) }),
  ),
  http.get(apiUrl('/api/v1/me/security'), () =>
    HttpResponse.json({ data: buildSecurityOverview() }),
  ),
  http.get(apiUrl('/api/v1/me/passkeys'), () => HttpResponse.json({ data: [] })),
  http.get(apiUrl('/api/v1/me/security-events'), () => HttpResponse.json(buildSecurityEventPage())),
  http.get(apiUrl('/api/v1/shop/settings'), () => HttpResponse.json({ data: buildShopSettings() })),
  http.get(apiUrl('/api/v1/shop/support'), () =>
    HttpResponse.json({ data: buildSupportSections() }),
  ),
  http.get(apiUrl('/api/v1/admin/settings'), () =>
    HttpResponse.json({ data: buildAdminSettings() }),
  ),
  http.get(apiUrl('/api/v1/assistant/status'), () =>
    HttpResponse.json({ data: { available: true } }),
  ),
];
