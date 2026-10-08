import { httpClient } from '@/api/httpClient';
import { withNormalizedError } from '@/utils/normalizeApiError';

export async function fetchAddresses() {
  const response = await httpClient.get('/api/v1/addresses');

  return response.data.data;
}

export function createAddress(address) {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/addresses', address);

    return response.data.data;
  });
}
