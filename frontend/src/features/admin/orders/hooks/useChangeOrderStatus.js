import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { changeOrderStatus } from '@/features/admin/orders/api/ordersApi';

export function useChangeOrderStatus(orderId) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (status) => changeOrderStatus(orderId, status),
    onSuccess: (order, status) => {
      queryClient.setQueryData(queryKeys.admin.orders.detail(orderId), order);
      const invalidations = [
        queryClient.invalidateQueries({ queryKey: queryKeys.admin.orders.lists }),
        queryClient.invalidateQueries({ queryKey: queryKeys.admin.dashboard }),
      ];

      if (status === 'cancelled') {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: queryKeys.admin.products.all }),
          queryClient.invalidateQueries({ queryKey: queryKeys.products.all }),
        );
      }

      return Promise.all(invalidations);
    },
    onError: (error) => {
      if (error.code === 'invalid_order_transition') {
        return queryClient.invalidateQueries({
          queryKey: queryKeys.admin.orders.detail(orderId),
        });
      }

      return undefined;
    },
  });
}
