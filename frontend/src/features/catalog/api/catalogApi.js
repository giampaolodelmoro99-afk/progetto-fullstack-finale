import { httpClient } from '@/api/httpClient';

export async function fetchCategories() {
  const response = await httpClient.get('/api/v1/categories');

  return response.data.data;
}
