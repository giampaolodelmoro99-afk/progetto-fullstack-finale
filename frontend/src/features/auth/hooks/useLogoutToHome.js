import { useCallback } from 'react';
import { useNavigate } from 'react-router';
import { paths } from '@/app/router/paths';
import { useAuth } from '@/features/auth/hooks/useAuth';

export function useLogoutToHome() {
  const { logout } = useAuth();
  const navigate = useNavigate();

  return useCallback(async () => {
    await navigate(paths.home);

    try {
      await logout();
    } catch {
      return;
    }
  }, [logout, navigate]);
}
