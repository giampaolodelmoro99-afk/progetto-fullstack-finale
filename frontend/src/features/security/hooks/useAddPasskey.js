import { useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchPasskeyRegistrationOptions, storePasskey } from '@/features/security/api/securityApi';
import { refreshSecurityQueries } from '@/features/security/utils/refreshSecurityQueries';
import { registerPasskey } from '@/features/security/utils/registerPasskey';

async function addPasskey(name) {
  const credential = await registerPasskey(fetchPasskeyRegistrationOptions);

  return storePasskey({ name, credential });
}

export function useAddPasskey() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: addPasskey,
    gcTime: 0,
    onSuccess: () => refreshSecurityQueries(queryClient),
  });
}
