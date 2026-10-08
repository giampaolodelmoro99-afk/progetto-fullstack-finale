import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchProduct } from '@/features/catalog/api/catalogApi';

export function useProduct(slug) {
  return useQuery({
    queryKey: queryKeys.products.detail(slug),
    queryFn: () => fetchProduct(slug),
  });
}
