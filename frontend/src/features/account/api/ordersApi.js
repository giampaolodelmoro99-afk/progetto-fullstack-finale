import { httpClient } from '@/api/httpClient';

export async function fetchOrder(orderId) {
  const response = await httpClient.get(`/api/v1/orders/${encodeURIComponent(orderId)}`);

  return response.data.data;
}
