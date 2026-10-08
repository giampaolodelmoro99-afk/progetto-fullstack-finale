import { httpClient } from '@/api/httpClient';

export async function fetchCart() {
  const response = await httpClient.get('/api/v1/cart');

  return response.data.data;
}
