import { httpClient } from '@/api/httpClient';
import { withNormalizedError } from '@/utils/normalizeApiError';

export const IDENTITY_PROMPTS = {
  enableTwoFactor: 'Per attivare la doppia autenticazione, conferma che sei tu.',
  disableTwoFactor: 'Per disattivare la doppia autenticazione, conferma che sei tu.',
  regenerateRecoveryCodes:
    'Per generare nuovi codici di recupero, conferma che sei tu. I codici che hai ora smetteranno di funzionare.',
  addPasskey: 'Per aggiungere una passkey, conferma che sei tu.',
  removePasskey: (name) => `Per rimuovere la passkey «${name}», conferma che sei tu.`,
  deleteAccount: "Prima di eliminare l'account, conferma che sei tu.",
};

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

export function changePassword(payload) {
  return withNormalizedError(async () => {
    const response = await httpClient.put('/api/v1/me/password', payload);

    return response.data;
  });
}

export function fetchPasswordPasskeyOptions() {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/me/password/passkey-options');

    return response.data.data.options;
  });
}

export function startTwoFactorSetup() {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/me/two-factor', null, {
      identityPrompt: IDENTITY_PROMPTS.enableTwoFactor,
    });

    return response.data.data;
  });
}

export function confirmTwoFactor(code) {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/me/two-factor/confirm', { code });

    return response.data.data.recovery_codes;
  });
}

export function regenerateRecoveryCodes() {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/me/two-factor/recovery-codes', null, {
      identityPrompt: IDENTITY_PROMPTS.regenerateRecoveryCodes,
    });

    return response.data.data.recovery_codes;
  });
}

export function disableTwoFactor() {
  return withNormalizedError(async () => {
    await httpClient.delete('/api/v1/me/two-factor', {
      identityPrompt: IDENTITY_PROMPTS.disableTwoFactor,
    });
  });
}

export async function fetchPasskeys() {
  const response = await httpClient.get('/api/v1/me/passkeys');

  return response.data.data;
}

export function fetchPasskeyRegistrationOptions() {
  return withNormalizedError(async () => {
    const response = await httpClient.post('/api/v1/me/passkeys/options', null, {
      identityPrompt: IDENTITY_PROMPTS.addPasskey,
    });

    return response.data.data.options;
  });
}

export function storePasskey({ name, credential }) {
  return withNormalizedError(async () => {
    const response = await httpClient.post(
      '/api/v1/me/passkeys',
      { name, credential },
      { identityPrompt: IDENTITY_PROMPTS.addPasskey },
    );

    return response.data.data;
  });
}

export function renamePasskey({ id, name }) {
  return withNormalizedError(async () => {
    const response = await httpClient.patch(`/api/v1/me/passkeys/${id}`, { name });

    return response.data.data;
  });
}

export function deletePasskey(passkey) {
  return withNormalizedError(async () => {
    await httpClient.delete(`/api/v1/me/passkeys/${passkey.id}`, {
      identityPrompt: IDENTITY_PROMPTS.removePasskey(passkey.name),
    });
  });
}

export async function fetchSecurityEvents(page) {
  const response = await httpClient.get('/api/v1/me/security-events', { params: { page } });

  return response.data;
}

export function deleteAccount() {
  return withNormalizedError(async () => {
    await httpClient.delete('/api/v1/me', { identityPrompt: IDENTITY_PROMPTS.deleteAccount });
  });
}
