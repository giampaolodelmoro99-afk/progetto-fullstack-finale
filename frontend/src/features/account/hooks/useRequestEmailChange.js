import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { requestEmailChange } from '@/features/account/api/profileApi';

export function useRequestEmailChange() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: requestEmailChange,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.me }),
  });
}
