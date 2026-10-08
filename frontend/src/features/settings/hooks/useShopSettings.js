import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchShopSettings } from '@/features/settings/api/settingsApi';

export function useShopSettings() {
  return useQuery({
    queryKey: queryKeys.settings.shop,
    queryFn: fetchShopSettings,
    staleTime: Infinity,
  });
}
