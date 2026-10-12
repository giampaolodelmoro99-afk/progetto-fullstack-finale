import { useCallback, useEffect, useLayoutEffect, useRef } from 'react';
import { useBeforeUnload, useBlocker } from 'react-router';
import { useLeaveGuard } from '@/hooks/useLeaveGuard';

export function useUnsavedChangesGuard(shouldBlock) {
  const leaveGuard = useLeaveGuard();
  const shouldBlockRef = useRef(shouldBlock);
  const bypassRef = useRef(false);

  useLayoutEffect(() => {
    shouldBlockRef.current = shouldBlock;
  });

  const blocker = useBlocker(
    useCallback(
      ({ currentLocation, nextLocation }) =>
        !bypassRef.current &&
        shouldBlockRef.current &&
        currentLocation.pathname !== nextLocation.pathname,
      [],
    ),
  );

  useBeforeUnload(
    useCallback((event) => {
      if (!bypassRef.current && shouldBlockRef.current) {
        event.preventDefault();
        event.returnValue = '';
      }
    }, []),
  );

  useEffect(() => {
    if (!leaveGuard) {
      return undefined;
    }

    leaveGuard.setGuard(() => !bypassRef.current && shouldBlockRef.current);

    return () => {
      leaveGuard.setGuard(null);
      leaveGuard.takePendingLogout();
    };
  }, [leaveGuard]);

  const stay = useCallback(() => {
    leaveGuard?.takePendingLogout();
    blocker.reset?.();
  }, [blocker, leaveGuard]);

  const leave = useCallback(() => {
    const pendingLogout = leaveGuard?.takePendingLogout();
    blocker.proceed?.();
    pendingLogout?.();
  }, [blocker, leaveGuard]);

  const allowNextNavigation = useCallback(() => {
    bypassRef.current = true;
  }, []);

  return { isBlocked: blocker.state === 'blocked', stay, leave, allowNextNavigation };
}
