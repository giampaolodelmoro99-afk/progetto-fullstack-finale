import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchHealth } from '@/features/system/api/systemApi';

export function useHealth({ enabled = true } = {}) {
  return useQuery({
    queryKey: queryKeys.system.health,
    queryFn: fetchHealth,
    enabled,
  });
}
