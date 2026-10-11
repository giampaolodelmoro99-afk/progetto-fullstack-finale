import { httpClient } from '@/api/httpClient';
import { withNormalizedError } from '@/utils/normalizeApiError';

export const EMAIL_CHANGE_PROMPT = "Prima di cambiare l'email, conferma che sei tu.";

export function updateProfile(profile) {
  return withNormalizedError(async () => {
    const response = await httpClient.patch('/api/v1/me', profile);

    return response.data.data;
  });
}

export function requestEmailChange(email) {
  return withNormalizedError(async () => {
    const response = await httpClient.post(
      '/api/v1/me/email',
      { email },
      { identityPrompt: EMAIL_CHANGE_PROMPT },
    );

    return response.data.data;
  });
}

export function cancelPendingEmailChange() {
  return withNormalizedError(async () => {
    await httpClient.delete('/api/v1/me/email-change');
  });
}
