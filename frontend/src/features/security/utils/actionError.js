import { IDENTITY_CONFIRMATION_CANCELLED } from '@/utils/normalizeApiError';

export function visibleActionError(mutation) {
  if (!mutation.isError || mutation.error.code === IDENTITY_CONFIRMATION_CANCELLED) {
    return null;
  }

  return mutation.error.message;
}
