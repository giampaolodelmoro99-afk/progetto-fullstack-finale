import { httpClient } from '@/api/httpClient';

export async function fetchDashboard() {
  const response = await httpClient.get('/api/v1/admin/dashboard');

  return response.data.data;
}
