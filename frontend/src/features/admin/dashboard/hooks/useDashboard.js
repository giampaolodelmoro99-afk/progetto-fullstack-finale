import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchDashboard } from '@/features/admin/dashboard/api/dashboardApi';

export function useDashboard() {
  return useQuery({
    queryKey: queryKeys.admin.dashboard,
    queryFn: fetchDashboard,
  });
}
