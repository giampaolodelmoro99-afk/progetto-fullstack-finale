import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import {
  confirmIdentity,
  fetchConfirmIdentityPasskeyOptions,
} from '@/features/security/api/securityApi';
import { authenticateWithPasskey } from '@/features/security/utils/authenticateWithPasskey';
import {
  SECOND_FACTOR_METHODS,
  recoveryCodesNotice,
  secondFactorPayload,
} from '@/features/security/utils/secondFactor';
import { useNotify } from '@/hooks/useNotify';

export const CONFIRM_IDENTITY_MUTATION = ['security', 'confirm-identity'];

async function submitConfirmation(values) {
  const credential =
    values.method === SECOND_FACTOR_METHODS.passkey
      ? await authenticateWithPasskey(fetchConfirmIdentityPasskeyOptions)
      : null;

  return confirmIdentity({
    password: values.password,
    ...secondFactorPayload(values, credential),
  });
}

export function useConfirmIdentity() {
  const queryClient = useQueryClient();
  const notify = useNotify();

  return useMutation({
    mutationKey: CONFIRM_IDENTITY_MUTATION,
    mutationFn: submitConfirmation,
    gcTime: 0,
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.security.overview });

      const notice = recoveryCodesNotice(result?.meta);

      if (notice) {
        notify({ message: notice, severity: 'warning' });
      }
    },
  });
}
