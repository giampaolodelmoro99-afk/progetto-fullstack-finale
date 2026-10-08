import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchProducts } from '@/features/catalog/api/catalogApi';

export function useProducts(params) {
  return useQuery({
    queryKey: queryKeys.products.list(params),
    queryFn: () => fetchProducts(params),
    placeholderData: keepPreviousData,
  });
}
