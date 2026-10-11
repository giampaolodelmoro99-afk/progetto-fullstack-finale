import { useMutation, useQueryClient } from '@tanstack/react-query';
import { deletePasskey } from '@/features/security/api/securityApi';
import { refreshSecurityQueries } from '@/features/security/utils/refreshSecurityQueries';

export function useRemovePasskey() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: deletePasskey,
    onSuccess: () => refreshSecurityQueries(queryClient),
  });
}
