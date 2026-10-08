import { useCallback } from 'react';
import { useNavigate } from 'react-router';
import { paths } from '@/app/router/paths';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useNotify } from '@/hooks/useNotify';

export function useLogoutToHome() {
  const { logout } = useAuth();
  const navigate = useNavigate();
  const notify = useNotify();

  return useCallback(async () => {
    await navigate(paths.home);

    try {
      await logout();
    } catch {
      notify({ message: 'Non è stato possibile uscire. Riprova.', severity: 'error' });
    }
  }, [logout, navigate, notify]);
}
