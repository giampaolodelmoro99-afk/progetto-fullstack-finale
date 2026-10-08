import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { resendVerificationEmail } from '@/features/auth/api/authApi';
import { useNotify } from '@/hooks/useNotify';

export function useResendVerificationEmail() {
  const queryClient = useQueryClient();
  const notify = useNotify();

  return useMutation({
    mutationFn: resendVerificationEmail,
    onSuccess: (result) => {
      notify({ message: result.message });
      queryClient.invalidateQueries({ queryKey: queryKeys.me });
    },
    onError: (error) => notify({ message: error.message, severity: 'error' }),
  });
}
