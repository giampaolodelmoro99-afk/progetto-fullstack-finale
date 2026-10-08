import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchSupportContent } from '@/features/settings/api/settingsApi';

export function useSupportContent() {
  return useQuery({
    queryKey: queryKeys.settings.support,
    queryFn: fetchSupportContent,
    staleTime: Infinity,
  });
}
