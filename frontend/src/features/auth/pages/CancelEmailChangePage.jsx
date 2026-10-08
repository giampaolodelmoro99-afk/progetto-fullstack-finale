import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import { useCallback } from 'react';
import { Link as RouterLink, useSearchParams } from 'react-router';
import { paths } from '@/app/router/paths';
import { ErrorState } from '@/components/feedback/ErrorState';
import { AuthResult } from '@/features/auth/components/AuthResult';
import { AuthResultLoading } from '@/features/auth/components/AuthResultLoading';
import { ExpiredEmailChangeLink } from '@/features/auth/components/ExpiredEmailChangeLink';
import { useCancelEmailChange } from '@/features/auth/hooks/useCancelEmailChange';
import { useRunOnce } from '@/features/auth/hooks/useRunOnce';

export default function CancelEmailChangePage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const cancellation = useCancelEmailChange();
  const { mutate } = cancellation;

  const cancel = useCallback(() => {
    if (token !== '') {
      mutate(token);
    }
  }, [mutate, token]);

  useRunOnce(cancel);

  if (cancellation.isSuccess) {
    return (
      <AuthResult
        title="Richiesta annullata"
        actions={
          <>
            <Button variant="contained" component={RouterLink} to={paths.accountSecurity} fullWidth>
              Cambia la password
            </Button>
            <Button component={RouterLink} to={paths.home} fullWidth>
              Torna al negozio
            </Button>
          </>
        }
      >
        <Stack sx={{ gap: 1 }}>
          <span>
            Il cambio dell'indirizzo email è stato annullato: il tuo account resta collegato
            all'indirizzo di prima.
          </span>
          <span>Se non hai chiesto tu la modifica, ti consigliamo di cambiare la password.</span>
        </Stack>
      </AuthResult>
    );
  }

  if (token === '' || cancellation.error?.status === 422) {
    return <ExpiredEmailChangeLink cancellation />;
  }

  if (cancellation.isError) {
    return <ErrorState message={cancellation.error.message} onRetry={cancel} />;
  }

  return <AuthResultLoading />;
}
