import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchSecurityOverview } from '@/features/security/api/securityApi';

export function useSecurityOverview({ enabled = true } = {}) {
  return useQuery({
    queryKey: queryKeys.security.overview,
    queryFn: fetchSecurityOverview,
    enabled,
  });
}
