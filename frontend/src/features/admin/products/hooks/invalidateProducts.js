import { queryKeys } from '@/api/queryKeys';

export function invalidateProducts(queryClient, { exceptDetail = null } = {}) {
  return Promise.all([
    queryClient.invalidateQueries({
      queryKey: queryKeys.admin.products.all,
      predicate: (query) =>
        exceptDetail === null ||
        JSON.stringify(query.queryKey) !==
          JSON.stringify(queryKeys.admin.products.detail(exceptDetail)),
    }),
    queryClient.invalidateQueries({ queryKey: queryKeys.admin.dashboard }),
    queryClient.invalidateQueries({ queryKey: queryKeys.admin.categories }),
    queryClient.invalidateQueries({ queryKey: queryKeys.products.all }),
    queryClient.invalidateQueries({ queryKey: queryKeys.categories }),
  ]);
}
