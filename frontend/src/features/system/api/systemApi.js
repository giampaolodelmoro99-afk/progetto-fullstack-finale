import { ensureCsrfCookie, httpClient } from '@/api/httpClient';

export async function fetchCsrfCookie() {
  await ensureCsrfCookie();

  return { ready: true };
}

export async function fetchHealth() {
  const response = await httpClient.get('/api/v1/health');

  return response.data;
}
