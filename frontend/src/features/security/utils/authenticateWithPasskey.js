import { startAuthentication } from '@simplewebauthn/browser';
import { createClientError } from '@/utils/normalizeApiError';

export const PASSKEY_CANCELLED = 'passkey_cancelled';
export const PASSKEY_FAILED = 'passkey_failed';

const CANCELLATION_ERRORS = new Set(['NotAllowedError', 'AbortError']);

export function isBrowserCancellation(error) {
  return CANCELLATION_ERRORS.has(error?.name);
}

export async function authenticateWithPasskey(fetchOptions) {
  const optionsJSON = await fetchOptions();

  try {
    return await startAuthentication({ optionsJSON });
  } catch (error) {
    if (isBrowserCancellation(error)) {
      throw createClientError(PASSKEY_CANCELLED, 'Verifica con la passkey annullata.');
    }

    throw createClientError(
      PASSKEY_FAILED,
      'Il dispositivo non ha completato la verifica: riprova.',
    );
  }
}

export function isPasskeyCancelled(error) {
  return error?.code === PASSKEY_CANCELLED;
}
