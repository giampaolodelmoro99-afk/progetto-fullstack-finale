import { httpClient } from '@/api/httpClient';
import { withNormalizedError } from '@/utils/normalizeApiError';

export async function fetchOrders(page) {
  const response = await httpClient.get('/api/v1/orders', {
    params: page > 1 ? { page } : {},
  });

  return response.data;
}

export async function fetchOrder(orderId) {
  const response = await httpClient.get(`/api/v1/orders/${encodeURIComponent(orderId)}`);

  return response.data.data;
}

export function cancelOrder(orderId) {
  return withNormalizedError(async () => {
    const response = await httpClient.post(`/api/v1/orders/${encodeURIComponent(orderId)}/cancel`);

    return response.data.data;
  });
}
