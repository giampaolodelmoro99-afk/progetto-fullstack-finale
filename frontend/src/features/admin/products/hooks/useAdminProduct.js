import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchAdminProduct } from '@/features/admin/products/api/productsApi';

export function useAdminProduct(productId) {
  return useQuery({
    queryKey: queryKeys.admin.products.detail(productId),
    queryFn: () => fetchAdminProduct(productId),
  });
}
