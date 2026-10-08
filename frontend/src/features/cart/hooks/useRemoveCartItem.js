import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { removeCartItem } from '@/features/cart/api/cartApi';
import { useNotify } from '@/hooks/useNotify';

export function useRemoveCartItem() {
  const queryClient = useQueryClient();
  const notify = useNotify();

  return useMutation({
    mutationFn: removeCartItem,
    onSuccess: (cart) => {
      queryClient.setQueryData(queryKeys.cart, cart);
    },
    onError: (error) => notify({ message: error.message, severity: 'error' }),
  });
}
