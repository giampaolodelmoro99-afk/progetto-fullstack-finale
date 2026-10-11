import { useInfiniteQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchSecurityEvents } from '@/features/security/api/securityApi';

export function useSecurityEvents() {
  return useInfiniteQuery({
    queryKey: queryKeys.security.events,
    queryFn: ({ pageParam }) => fetchSecurityEvents(pageParam),
    initialPageParam: 1,
    getNextPageParam: ({ meta }) =>
      meta.current_page < meta.last_page ? meta.current_page + 1 : undefined,
  });
}
