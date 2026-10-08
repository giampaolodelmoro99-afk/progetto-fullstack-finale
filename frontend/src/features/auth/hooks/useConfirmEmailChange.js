import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { confirmEmailChange } from '@/features/auth/api/authApi';

export function useConfirmEmailChange() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: confirmEmailChange,
    gcTime: 0,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.me }),
  });
}
