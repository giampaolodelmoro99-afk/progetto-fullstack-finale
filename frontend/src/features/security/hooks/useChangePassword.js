import { useMutation, useQueryClient } from '@tanstack/react-query';
import { changePassword, fetchPasswordPasskeyOptions } from '@/features/security/api/securityApi';
import { authenticateWithPasskey } from '@/features/security/utils/authenticateWithPasskey';
import { refreshSecurityQueries } from '@/features/security/utils/refreshSecurityQueries';
import {
  NO_SECOND_FACTOR,
  SECOND_FACTOR_METHODS,
  secondFactorPayload,
} from '@/features/security/utils/secondFactor';

async function submitPasswordChange(values) {
  const credential =
    values.method === SECOND_FACTOR_METHODS.passkey
      ? await authenticateWithPasskey(fetchPasswordPasskeyOptions)
      : null;

  return changePassword({
    current_password: values.current_password,
    password: values.password,
    password_confirmation: values.password_confirmation,
    ...(values.method === NO_SECOND_FACTOR ? {} : secondFactorPayload(values, credential)),
  });
}

export function useChangePassword() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: submitPasswordChange,
    gcTime: 0,
    onSuccess: () => refreshSecurityQueries(queryClient),
  });
}
