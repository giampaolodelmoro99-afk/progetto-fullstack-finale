import { useCallback } from 'react';
import { useNavigate } from 'react-router';
import { paths } from '@/app/router/paths';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useLeaveGuard } from '@/hooks/useLeaveGuard';
import { useNotify } from '@/hooks/useNotify';

export function useLogoutToHome() {
  const { logout } = useAuth();
  const navigate = useNavigate();
  const notify = useNotify();
  const leaveGuard = useLeaveGuard();

  const performLogout = useCallback(async () => {
    try {
      await logout();
    } catch {
      notify({ message: 'Non è stato possibile uscire. Riprova.', severity: 'error' });
    }
  }, [logout, notify]);

  return useCallback(async () => {
    if (leaveGuard?.isBlocking()) {
      leaveGuard.setPendingLogout(performLogout);
      await navigate(paths.home);

      return;
    }

    await navigate(paths.home);
    await performLogout();
  }, [leaveGuard, navigate, performLogout]);
}
