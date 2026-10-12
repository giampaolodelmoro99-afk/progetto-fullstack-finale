import { httpClient } from '@/api/httpClient';
import { withNormalizedError } from '@/utils/normalizeApiError';

export async function fetchAdminOrders(params) {
  const response = await httpClient.get('/api/v1/admin/orders', { params });

  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchAdminOrder(orderId) {
  const response = await httpClient.get(`/api/v1/admin/orders/${encodeURIComponent(orderId)}`);

  return response.data.data;
}

export function changeOrderStatus(orderId, status) {
  return withNormalizedError(async () => {
    const response = await httpClient.patch(
      `/api/v1/admin/orders/${encodeURIComponent(orderId)}/status`,
      { status },
    );

    return response.data.data;
  });
}
