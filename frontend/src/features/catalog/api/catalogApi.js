import { httpClient } from '@/api/httpClient';

export async function fetchCategories() {
  const response = await httpClient.get('/api/v1/categories');

  return response.data.data;
}

export async function fetchProducts(params) {
  const response = await httpClient.get('/api/v1/products', { params });

  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchProduct(slug) {
  const response = await httpClient.get(`/api/v1/products/${encodeURIComponent(slug)}`);

  return response.data.data;
}

export async function fetchRelatedProducts(slug) {
  const response = await httpClient.get(`/api/v1/products/${encodeURIComponent(slug)}/related`);

  return response.data.data;
}
