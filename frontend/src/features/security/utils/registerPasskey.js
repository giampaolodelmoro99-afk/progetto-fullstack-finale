import { startRegistration } from '@simplewebauthn/browser';
import {
  PASSKEY_CANCELLED,
  PASSKEY_FAILED,
  isBrowserCancellation,
} from '@/features/security/utils/authenticateWithPasskey';
import { createClientError } from '@/utils/normalizeApiError';

export async function registerPasskey(fetchOptions) {
  const optionsJSON = await fetchOptions();

  try {
    return await startRegistration({ optionsJSON });
  } catch (error) {
    if (isBrowserCancellation(error)) {
      throw createClientError(PASSKEY_CANCELLED, 'Registrazione della passkey annullata.');
    }

    throw createClientError(
      PASSKEY_FAILED,
      'Il dispositivo non ha completato la registrazione: riprova.',
    );
  }
}
