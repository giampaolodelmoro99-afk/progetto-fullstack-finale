import { httpClient } from '@/api/httpClient';
import { normalizeApiError } from '@/utils/normalizeApiError';

function stockProducts(error) {
  const products = error?.response?.data?.products;

  return Array.isArray(products)
    ? products.map(({ product, requested, available }) => ({ product, requested, available }))
    : [];
}

export async function placeOrder(payload) {
  try {
    const response = await httpClient.post('/api/v1/checkout', payload);

    return response.data.data;
  } catch (error) {
    const normalized = normalizeApiError(error);

    if (normalized.code === 'insufficient_stock') {
      normalized.products = stockProducts(error);
    }

    throw normalized;
  }
}
