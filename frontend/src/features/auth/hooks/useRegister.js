import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
import { startUserSession } from '@/api/session';
import { paths } from '@/app/router/paths';
import { register } from '@/features/auth/api/authApi';

export function useRegister() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();

  return useMutation({
    mutationFn: register,
    gcTime: 0,
    onSuccess: async (user) => {
      await navigate('.', {
        replace: true,
        state: { from: { pathname: paths.verifyEmail } },
        flushSync: true,
      });
      startUserSession(queryClient, user);
    },
  });
}
