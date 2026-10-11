import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
import { endUserSession } from '@/api/session';
import { paths } from '@/app/router/paths';
import { deleteAccount } from '@/features/security/api/securityApi';
import { useNotify } from '@/hooks/useNotify';

export function useDeleteAccount() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const notify = useNotify();

  return useMutation({
    mutationFn: deleteAccount,
    onSuccess: async () => {
      await navigate(paths.home, { replace: true, flushSync: true });
      endUserSession(queryClient);
      notify({ message: 'Il tuo account è stato eliminato.' });
    },
  });
}
