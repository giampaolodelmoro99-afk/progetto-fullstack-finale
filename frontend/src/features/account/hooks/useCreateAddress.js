import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { createAddress } from '@/features/account/api/addressesApi';

export const SAVE_ADDRESS_MUTATION = ['addresses', 'save'];

export function useCreateAddress() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: SAVE_ADDRESS_MUTATION,
    mutationFn: createAddress,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.addresses }),
  });
}
