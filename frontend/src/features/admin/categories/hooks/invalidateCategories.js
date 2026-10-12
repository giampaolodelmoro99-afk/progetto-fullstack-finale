import { queryKeys } from '@/api/queryKeys';

export function invalidateCategories(queryClient) {
  return Promise.all([
    queryClient.invalidateQueries({ queryKey: queryKeys.admin.categories }),
    queryClient.invalidateQueries({ queryKey: queryKeys.categories }),
    queryClient.invalidateQueries({ queryKey: queryKeys.admin.products.all }),
    queryClient.invalidateQueries({ queryKey: queryKeys.products.all }),
  ]);
}
