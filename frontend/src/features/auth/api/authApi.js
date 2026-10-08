import { ensureCsrfCookie, httpClient } from '@/api/httpClient';
import { withNormalizedError } from '@/utils/normalizeApiError';

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

export function login(credentials) {
  return withNormalizedError(async () => {
    await ensureCsrfCookie();
    const response = await httpClient.post('/api/v1/login', credentials);

    return response.data;
  });
}

export function completeTwoFactorLogin(payload) {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/login/two-factor', payload);

    return response.data;
  });
}

export function fetchTwoFactorPasskeyOptions() {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/login/two-factor/passkey-options');

    return response.data.data.options;
  });
}

export function fetchPasskeyLoginOptions() {
  return withNormalizedError(async () => {
    await ensureCsrfCookie();
    const response = await httpClient.post('/api/v1/passkeys/login/options');

    return response.data.data.options;
  });
}

export function loginWithPasskey(credential) {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/passkeys/login', { credential });

    return response.data;
  });
}

export function register(payload) {
  return withNormalizedError(async () => {
    await ensureCsrfCookie();
    const response = await httpClient.post('/api/v1/register', payload);

    return response.data.data;
  });
}

export function requestPasswordReset(payload) {
  return withNormalizedError(async () => {
    await ensureCsrfCookie();
    const response = await httpClient.post('/api/v1/forgot-password', payload);

    return response.data;
  });
}

export function checkPasswordResetToken(payload) {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/reset-password/check', payload);

    return response.data;
  });
}

export function fetchResetPasswordPasskeyOptions(payload) {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/reset-password/passkey-options', payload);

    return response.data.data.options;
  });
}

export function resetPassword(payload) {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/reset-password', payload);

    return response.data;
  });
}

export function verifyEmail({ id, hash, expires, signature }) {
  return withNormalizedError(async () => {
    const response = await httpClient.post(
      `/api/v1/email/verify/${encodeURIComponent(id)}/${encodeURIComponent(hash)}`,
      null,
      { params: { expires, signature } },
    );

    return response.data.data;
  });
}

export function resendVerificationEmail() {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/email/verification-notification');

    return response.data;
  });
}

export function confirmEmailChange(token) {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/email-change/confirm', { token });

    return response.data.data;
  });
}

export function cancelEmailChange(token) {
  return withNormalizedError(async () => {
    await httpClient.post('/api/v1/email-change/cancel', { token });
  });
}
