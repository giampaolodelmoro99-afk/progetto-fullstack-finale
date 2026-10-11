import { useMutation, useQueryClient } from '@tanstack/react-query';
import { regenerateRecoveryCodes } from '@/features/security/api/securityApi';
import { refreshSecurityQueries } from '@/features/security/utils/refreshSecurityQueries';

export function useRegenerateRecoveryCodes() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: regenerateRecoveryCodes,
    gcTime: 0,
    onSuccess: () => refreshSecurityQueries(queryClient),
  });
}
