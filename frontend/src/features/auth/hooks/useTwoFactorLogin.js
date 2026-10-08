import { useMutation, useQueryClient } from '@tanstack/react-query';
import { startUserSession } from '@/api/session';
import { completeTwoFactorLogin, fetchTwoFactorPasskeyOptions } from '@/features/auth/api/authApi';
import { useRecoveryCodesNotice } from '@/features/auth/hooks/useRecoveryCodesNotice';
import { authenticateWithPasskey } from '@/features/security/utils/authenticateWithPasskey';
import { SECOND_FACTOR_METHODS, secondFactorPayload } from '@/features/security/utils/secondFactor';

async function submitSecondFactor(values) {
  const credential =
    values.method === SECOND_FACTOR_METHODS.passkey
      ? await authenticateWithPasskey(fetchTwoFactorPasskeyOptions)
      : null;

  return completeTwoFactorLogin(secondFactorPayload(values, credential));
}

export function useTwoFactorLogin() {
  const queryClient = useQueryClient();
  const showRecoveryCodesNotice = useRecoveryCodesNotice();

  return useMutation({
    mutationFn: submitSecondFactor,
    gcTime: 0,
    onSuccess: (result) => {
      showRecoveryCodesNotice(result.meta);
      startUserSession(queryClient, result.data);
    },
  });
}
