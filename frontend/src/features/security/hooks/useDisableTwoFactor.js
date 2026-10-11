import { useMutation, useQueryClient } from '@tanstack/react-query';
import { disableTwoFactor } from '@/features/security/api/securityApi';
import { refreshSecurityQueries } from '@/features/security/utils/refreshSecurityQueries';

export function useDisableTwoFactor() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: disableTwoFactor,
    onSuccess: () => refreshSecurityQueries(queryClient),
  });
}
