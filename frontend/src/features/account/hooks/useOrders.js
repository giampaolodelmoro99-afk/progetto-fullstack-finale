import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchOrders } from '@/features/account/api/ordersApi';

export function useOrders(page) {
  return useQuery({
    queryKey: queryKeys.orders.list(page),
    queryFn: () => fetchOrders(page),
    placeholderData: keepPreviousData,
  });
}
