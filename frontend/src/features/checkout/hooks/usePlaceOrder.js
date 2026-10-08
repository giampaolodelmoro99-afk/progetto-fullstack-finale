import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { placeOrder } from '@/features/checkout/api/checkoutApi';

export function usePlaceOrder(getPayload, { onPlaced } = {}) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => placeOrder(getPayload()),
    onSuccess: (order) => {
      onPlaced?.(order);
      queryClient.invalidateQueries({ queryKey: queryKeys.cart });
      queryClient.invalidateQueries({ queryKey: queryKeys.orders.all });
    },
    onError: (error) => {
      if (error.status === 409) {
        queryClient.invalidateQueries({ queryKey: queryKeys.cart });
      }
    },
  });
}
