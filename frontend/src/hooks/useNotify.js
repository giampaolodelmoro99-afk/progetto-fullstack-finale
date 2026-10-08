import { createContext, useContext } from 'react';

export const NotificationContext = createContext(null);

export function useNotify() {
  const notify = useContext(NotificationContext);

  if (notify === null) {
    throw new Error('useNotify must be used inside NotificationProvider');
  }

  return notify;
}
