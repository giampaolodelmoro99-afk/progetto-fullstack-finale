import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { renamePasskey } from '@/features/security/api/securityApi';

export function useRenamePasskey() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: renamePasskey,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.security.passkeys }),
  });
}
