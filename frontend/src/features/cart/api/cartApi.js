import { httpClient } from '@/api/httpClient';
import { withNormalizedError } from '@/utils/normalizeApiError';

export async function fetchCart() {
  const response = await httpClient.get('/api/v1/cart');

  return response.data.data;
}

export function addCartItem({ productId, quantity }) {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/cart/items', {
      product_id: productId,
      quantity,
    });

    return response.data.data;
  });
}
