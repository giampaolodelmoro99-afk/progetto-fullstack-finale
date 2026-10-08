import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchRelatedProducts } from '@/features/catalog/api/catalogApi';

export function useRelatedProducts(slug) {
  return useQuery({
    queryKey: queryKeys.products.related(slug),
    queryFn: () => fetchRelatedProducts(slug),
  });
}
