import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { setIdentityConfirmationHandler } from '@/api/httpClient';
import { ConfirmIdentityDialog } from '@/features/security/components/ConfirmIdentityDialog';
import { IdentityConfirmationContext } from '@/features/security/hooks/useIdentityConfirmation';
import { IDENTITY_CONFIRMATION_CANCELLED, createClientError } from '@/utils/normalizeApiError';

export function IdentityConfirmationProvider({ children }) {
  const [request, setRequest] = useState(null);
  const pending = useRef(null);
  const sequence = useRef(0);

  const confirmIdentity = useCallback(({ prompt = null } = {}) => {
    if (pending.current) {
      return pending.current.promise;
    }

    let resolve;
    let reject;
    const promise = new Promise((resolvePromise, rejectPromise) => {
      resolve = resolvePromise;
      reject = rejectPromise;
    });

    sequence.current += 1;
    pending.current = { promise, resolve, reject };
    setRequest({ id: sequence.current, prompt });

    return promise;
  }, []);

  useEffect(() => {
    setIdentityConfirmationHandler(confirmIdentity);

    return () => setIdentityConfirmationHandler(null);
  }, [confirmIdentity]);

  const settle = useCallback((confirmed) => {
    const current = pending.current;

    pending.current = null;
    setRequest(null);

    if (confirmed) {
      current?.resolve();
    } else {
      current?.reject(
        createClientError(IDENTITY_CONFIRMATION_CANCELLED, "Conferma dell'identità annullata."),
      );
    }
  }, []);

  const value = useMemo(() => ({ confirmIdentity }), [confirmIdentity]);

  return (
    <IdentityConfirmationContext.Provider value={value}>
      {children}
      {request ? (
        <ConfirmIdentityDialog
          key={request.id}
          prompt={request.prompt}
          onConfirmed={() => settle(true)}
          onCancel={() => settle(false)}
        />
      ) : null}
    </IdentityConfirmationContext.Provider>
  );
}
