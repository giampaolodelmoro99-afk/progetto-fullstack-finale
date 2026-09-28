import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchCsrfCookie } from '@/features/system/api/systemApi';

export function useCsrfCookie() {
  return useQuery({
    queryKey: queryKeys.system.csrf,
    queryFn: fetchCsrfCookie,
  });
}
