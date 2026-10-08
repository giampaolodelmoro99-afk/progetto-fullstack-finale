import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchOrder } from '@/features/account/api/ordersApi';

export function useOrder(orderId) {
  return useQuery({
    queryKey: queryKeys.orders.detail(orderId),
    queryFn: () => fetchOrder(orderId),
  });
}
