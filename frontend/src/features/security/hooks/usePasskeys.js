import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchPasskeys } from '@/features/security/api/securityApi';

export function usePasskeys() {
  return useQuery({ queryKey: queryKeys.security.passkeys, queryFn: fetchPasskeys });
}
