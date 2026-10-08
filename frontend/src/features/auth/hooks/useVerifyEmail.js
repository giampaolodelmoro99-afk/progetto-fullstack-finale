import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { verifyEmail } from '@/features/auth/api/authApi';

export function useVerifyEmail() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: verifyEmail,
    gcTime: 0,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.me }),
  });
}
