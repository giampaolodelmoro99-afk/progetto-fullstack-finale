import { http, HttpResponse } from 'msw';
import {
  buildAdminSettings,
  buildCart,
  buildCategories,
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
  http.get(apiUrl('/api/v1/cart'), () => HttpResponse.json({ data: buildCart() })),
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
