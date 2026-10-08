import { httpClient } from '@/api/httpClient';

export async function fetchMe() {
  try {
    const response = await httpClient.get('/api/v1/me');

    return response.data.data;
  } catch (error) {
    if (error.response?.status === 401) {
      return null;
    }

    throw error;
  }
}

export async function logout() {
  await httpClient.post('/api/v1/logout');
}
