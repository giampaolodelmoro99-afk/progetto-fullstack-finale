import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchAdminOrders } from '@/features/admin/orders/api/ordersApi';

export function useAdminOrders(params) {
  return useQuery({
    queryKey: queryKeys.admin.orders.list(params),
    queryFn: () => fetchAdminOrders(params),
    placeholderData: keepPreviousData,
  });
}
