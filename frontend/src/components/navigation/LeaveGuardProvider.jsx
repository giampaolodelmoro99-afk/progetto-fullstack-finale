import { useMemo, useRef } from 'react';
import { LeaveGuardContext } from '@/hooks/useLeaveGuard';

export function LeaveGuardProvider({ children }) {
  const guardRef = useRef(null);
  const pendingLogoutRef = useRef(null);

  const value = useMemo(
    () => ({
      setGuard: (guard) => {
        guardRef.current = guard;
      },
      isBlocking: () => (guardRef.current ? guardRef.current() : false),
      setPendingLogout: (logout) => {
        pendingLogoutRef.current = logout;
      },
      takePendingLogout: () => {
        const logout = pendingLogoutRef.current;
        pendingLogoutRef.current = null;

        return logout;
      },
    }),
    [],
  );

  return <LeaveGuardContext.Provider value={value}>{children}</LeaveGuardContext.Provider>;
}
