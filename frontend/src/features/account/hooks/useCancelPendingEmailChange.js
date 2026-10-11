import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { cancelPendingEmailChange } from '@/features/account/api/profileApi';

export function useCancelPendingEmailChange() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: cancelPendingEmailChange,
    onSettled: (data, error) => {
      if (!error || error.status === 404) {
        return queryClient.invalidateQueries({ queryKey: queryKeys.me });
      }

      return undefined;
    },
  });
}
