import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchAdminProducts } from '@/features/admin/products/api/productsApi';

export function useAdminProducts(params) {
  return useQuery({
    queryKey: queryKeys.admin.products.list(params),
    queryFn: () => fetchAdminProducts(params),
    placeholderData: keepPreviousData,
  });
}
