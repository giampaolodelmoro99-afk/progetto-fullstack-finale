import { useMutation, useQueryClient } from '@tanstack/react-query';
import { confirmTwoFactor } from '@/features/security/api/securityApi';
import { refreshSecurityQueries } from '@/features/security/utils/refreshSecurityQueries';

export function useConfirmTwoFactor() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: confirmTwoFactor,
    gcTime: 0,
    onSuccess: () => refreshSecurityQueries(queryClient),
  });
}
