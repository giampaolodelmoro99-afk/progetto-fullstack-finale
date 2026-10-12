import { httpClient } from '@/api/httpClient';
import { withNormalizedError } from '@/utils/normalizeApiError';

export async function fetchAdminProducts(params) {
  const response = await httpClient.get('/api/v1/admin/products', { params });

  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchAdminProduct(productId) {
  const response = await httpClient.get(`/api/v1/admin/products/${encodeURIComponent(productId)}`);

  return response.data.data;
}

export function createProduct(payload) {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/admin/products', payload);

    return response.data.data;
  });
}

export function updateProduct(productId, payload) {
  return withNormalizedError(async () => {
    const response = await httpClient.patch(`/api/v1/admin/products/${productId}`, payload);

    return response.data.data;
  });
}

export function deleteProduct(productId) {
  return withNormalizedError(() => httpClient.delete(`/api/v1/admin/products/${productId}`));
}

export function uploadProductImages(productId, files) {
  const body = new FormData();
  files.forEach((file) => body.append('images[]', file));

  return withNormalizedError(async () => {
    const response = await httpClient.post(`/api/v1/admin/products/${productId}/images`, body);

    return response.data.data;
  });
}

export function deleteProductImage(productId, imageId) {
  return withNormalizedError(() =>
    httpClient.delete(`/api/v1/admin/products/${productId}/images/${imageId}`),
  );
}
