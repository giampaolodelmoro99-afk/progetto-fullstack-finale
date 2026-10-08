import { createContext, useContext } from 'react';

export const IdentityConfirmationContext = createContext(null);

export function useIdentityConfirmation() {
  const context = useContext(IdentityConfirmationContext);

  if (context === null) {
    throw new Error('useIdentityConfirmation must be used inside IdentityConfirmationProvider');
  }

  return context;
}
