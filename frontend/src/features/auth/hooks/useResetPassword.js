import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
import { queryKeys } from '@/api/queryKeys';
import { paths } from '@/app/router/paths';
import { fetchResetPasswordPasskeyOptions, resetPassword } from '@/features/auth/api/authApi';
import { useRecoveryCodesNotice } from '@/features/auth/hooks/useRecoveryCodesNotice';
import { authenticateWithPasskey } from '@/features/security/utils/authenticateWithPasskey';
import {
  NO_SECOND_FACTOR,
  SECOND_FACTOR_METHODS,
  secondFactorPayload,
} from '@/features/security/utils/secondFactor';

async function submitNewPassword({ token, email, values }) {
  const credential =
    values.method === SECOND_FACTOR_METHODS.passkey
      ? await authenticateWithPasskey(() => fetchResetPasswordPasskeyOptions({ token, email }))
      : null;

  return resetPassword({
    token,
    email,
    password: values.password,
    password_confirmation: values.password_confirmation,
    ...(values.method === NO_SECOND_FACTOR ? {} : secondFactorPayload(values, credential)),
  });
}

export function useResetPassword() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const showRecoveryCodesNotice = useRecoveryCodesNotice();

  return useMutation({
    mutationFn: submitNewPassword,
    gcTime: 0,
    onSuccess: async (result) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.me });
      await navigate(paths.login, {
        replace: true,
        state: { notice: { severity: 'success', message: result.message } },
      });
      showRecoveryCodesNotice(result.meta);
    },
  });
}
