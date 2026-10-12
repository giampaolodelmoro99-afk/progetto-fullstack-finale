import { httpClient } from '@/api/httpClient';
import { withNormalizedError } from '@/utils/normalizeApiError';

export async function fetchAdminCategories() {
  const response = await httpClient.get('/api/v1/admin/categories');

  return response.data.data;
}

export function createCategory(values) {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/admin/categories', values);

    return response.data.data;
  });
}

export function updateCategory({ id, ...values }) {
  return withNormalizedError(async () => {
    const response = await httpClient.patch(`/api/v1/admin/categories/${id}`, values);

    return response.data.data;
  });
}

export function deleteCategory(id) {
  return withNormalizedError(() => httpClient.delete(`/api/v1/admin/categories/${id}`));
}
