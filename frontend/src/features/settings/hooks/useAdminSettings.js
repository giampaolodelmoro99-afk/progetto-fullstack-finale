import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchAdminSettings } from '@/features/settings/api/settingsApi';

export function useAdminSettings() {
  return useQuery({
    queryKey: queryKeys.settings.admin,
    queryFn: fetchAdminSettings,
    staleTime: Infinity,
  });
}
