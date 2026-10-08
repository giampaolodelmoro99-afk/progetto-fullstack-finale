import { useMutation, useQueryClient } from '@tanstack/react-query';
import { startUserSession } from '@/api/session';
import { fetchPasskeyLoginOptions, loginWithPasskey } from '@/features/auth/api/authApi';
import { authenticateWithPasskey } from '@/features/security/utils/authenticateWithPasskey';

async function signInWithPasskey() {
  const credential = await authenticateWithPasskey(fetchPasskeyLoginOptions);

  return loginWithPasskey(credential);
}

export function usePasskeyLogin() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: signInWithPasskey,
    gcTime: 0,
    onSuccess: (result) => {
      startUserSession(queryClient, result.data);
    },
  });
}
