import { createContext, useContext } from 'react';

export const AssistantContext = createContext(null);

export function useAssistant() {
  const context = useContext(AssistantContext);

  if (context === null) {
    throw new Error('useAssistant must be used inside AssistantProvider');
  }

  return context;
}
