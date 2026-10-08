import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { cancelEmailChange } from '@/features/auth/api/authApi';

export function useCancelEmailChange() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: cancelEmailChange,
    gcTime: 0,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.me }),
  });
}
