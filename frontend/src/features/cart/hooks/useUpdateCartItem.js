import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { updateCartItem } from '@/features/cart/api/cartApi';
import { useNotify } from '@/hooks/useNotify';

export function useUpdateCartItem() {
  const queryClient = useQueryClient();
  const notify = useNotify();

  return useMutation({
    mutationFn: updateCartItem,
    onSuccess: (cart) => {
      queryClient.setQueryData(queryKeys.cart, cart);
    },
    onError: (error) =>
      notify({
        message: Object.values(error.fieldErrors ?? {})[0] ?? error.message,
        severity: 'error',
      }),
  });
}
