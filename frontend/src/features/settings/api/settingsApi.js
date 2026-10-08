import { httpClient } from '@/api/httpClient';

export async function fetchShopSettings() {
  const response = await httpClient.get('/api/v1/shop/settings');

  return response.data.data;
}

export async function fetchAdminSettings() {
  const response = await httpClient.get('/api/v1/admin/settings');

  return response.data.data;
}

export async function fetchSupportContent() {
  const response = await httpClient.get('/api/v1/shop/support');

  return response.data.data;
}
