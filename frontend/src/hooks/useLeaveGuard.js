import { createContext, useContext } from 'react';

export const LeaveGuardContext = createContext(null);

export function useLeaveGuard() {
  return useContext(LeaveGuardContext);
}
