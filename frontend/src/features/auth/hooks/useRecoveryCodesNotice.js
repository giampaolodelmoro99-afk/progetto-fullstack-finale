import { useCallback } from 'react';
import { useNavigate } from 'react-router';
import { paths } from '@/app/router/paths';
import { recoveryCodesNotice } from '@/features/security/utils/secondFactor';
import { useNotify } from '@/hooks/useNotify';

export function useRecoveryCodesNotice() {
  const navigate = useNavigate();
  const notify = useNotify();

  return useCallback(
    (meta) => {
      const message = recoveryCodesNotice(meta);

      if (message) {
        notify({
          message,
          severity: 'warning',
          action: { label: 'Vai a Sicurezza', onClick: () => navigate(paths.accountSecurity) },
        });
      }
    },
    [navigate, notify],
  );
}
