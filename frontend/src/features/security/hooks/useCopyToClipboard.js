import { useCallback } from 'react';
import { useNotify } from '@/hooks/useNotify';

export function useCopyToClipboard() {
  const notify = useNotify();

  return useCallback(
    async (text, successMessage) => {
      try {
        await navigator.clipboard.writeText(text);
        notify({ message: successMessage });
      } catch {
        notify({ message: 'Non è stato possibile copiare il testo.', severity: 'error' });
      }
    },
    [notify],
  );
}
