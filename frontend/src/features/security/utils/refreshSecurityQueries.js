import { queryKeys } from '@/api/queryKeys';

export function refreshSecurityQueries(queryClient) {
  return Promise.all([
    queryClient.invalidateQueries({ queryKey: queryKeys.security.all }),
    queryClient.invalidateQueries({ queryKey: queryKeys.me }),
  ]);
}
