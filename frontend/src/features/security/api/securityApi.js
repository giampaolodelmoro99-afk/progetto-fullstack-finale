import { httpClient } from '@/api/httpClient';
import { withNormalizedError } from '@/utils/normalizeApiError';

export async function fetchSecurityOverview() {
  const response = await httpClient.get('/api/v1/me/security');

  return response.data.data;
}

export function confirmIdentity(payload) {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/me/confirm-identity', payload);

    return response.data;
  });
}

export function fetchConfirmIdentityPasskeyOptions() {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/me/confirm-identity/passkey-options');

    return response.data.data.options;
  });
}
