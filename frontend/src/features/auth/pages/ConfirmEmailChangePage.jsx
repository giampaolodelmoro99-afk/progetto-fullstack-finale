import Button from '@mui/material/Button';
import { useCallback } from 'react';
import { Link as RouterLink, useSearchParams } from 'react-router';
import { paths } from '@/app/router/paths';
import { ErrorState } from '@/components/feedback/ErrorState';
import { AuthResult } from '@/features/auth/components/AuthResult';
import { AuthResultLoading } from '@/features/auth/components/AuthResultLoading';
import { ExpiredEmailChangeLink } from '@/features/auth/components/ExpiredEmailChangeLink';
import { useConfirmEmailChange } from '@/features/auth/hooks/useConfirmEmailChange';
import { useRunOnce } from '@/features/auth/hooks/useRunOnce';

const EMAIL_CHANGE_UNAVAILABLE = 'email_change_unavailable';

function accountButton() {
  return (
    <Button variant="contained" component={RouterLink} to={paths.accountProfile} fullWidth>
      Vai al tuo account
    </Button>
  );
}

export default function ConfirmEmailChangePage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const confirmation = useConfirmEmailChange();
  const { mutate } = confirmation;

  const confirm = useCallback(() => {
    if (token !== '') {
      mutate(token);
    }
  }, [mutate, token]);

  useRunOnce(confirm);

  if (confirmation.isSuccess) {
    return (
      <AuthResult title="Email aggiornata" actions={accountButton()}>
        D'ora in poi accederai con {confirmation.data.email} e riceverai lì le email sui tuoi
        ordini.
      </AuthResult>
    );
  }

  if (confirmation.error?.code === EMAIL_CHANGE_UNAVAILABLE) {
    return (
      <AuthResult title="Indirizzo non disponibile" actions={accountButton()}>
        {confirmation.error.message} Puoi chiedere il cambio verso un altro indirizzo dal tuo
        profilo.
      </AuthResult>
    );
  }

  if (token === '' || confirmation.error?.status === 422) {
    return <ExpiredEmailChangeLink />;
  }

  if (confirmation.isError) {
    return <ErrorState message={confirmation.error.message} onRetry={confirm} />;
  }

  return <AuthResultLoading />;
}
