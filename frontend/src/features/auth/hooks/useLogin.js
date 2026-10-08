import { useMutation, useQueryClient } from '@tanstack/react-query';
import { startUserSession } from '@/api/session';
import { login } from '@/features/auth/api/authApi';

export function useLogin() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: login,
    gcTime: 0,
    onSuccess: (result) => {
      if (result.data) {
        startUserSession(queryClient, result.data);
      }
    },
  });
}
