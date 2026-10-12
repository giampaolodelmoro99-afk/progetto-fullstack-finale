import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchAdminOrder } from '@/features/admin/orders/api/ordersApi';

export function useAdminOrder(orderId) {
  return useQuery({
    queryKey: queryKeys.admin.orders.detail(orderId),
    queryFn: () => fetchAdminOrder(orderId),
  });
}
