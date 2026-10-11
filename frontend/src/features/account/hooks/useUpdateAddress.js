import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { updateAddress } from '@/features/account/api/addressesApi';
import { SAVE_ADDRESS_MUTATION } from '@/features/account/hooks/useCreateAddress';

export function useUpdateAddress() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: SAVE_ADDRESS_MUTATION,
    mutationFn: updateAddress,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.addresses }),
  });
}
