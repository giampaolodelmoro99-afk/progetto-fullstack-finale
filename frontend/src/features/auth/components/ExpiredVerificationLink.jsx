import Button from '@mui/material/Button';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { AuthResult } from '@/features/auth/components/AuthResult';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useResendVerificationEmail } from '@/features/auth/hooks/useResendVerificationEmail';

export function ExpiredVerificationLink() {
  const { user, isAuthenticated } = useAuth();
  const resend = useResendVerificationEmail();

  return (
    <AuthResult
      title="Il link è scaduto"
      actions={
        isAuthenticated ? (
          <Button
            variant="contained"
            fullWidth
            loading={resend.isPending}
            onClick={() => resend.mutate()}
          >
            Invia un nuovo link
          </Button>
        ) : (
          <Button
            variant="contained"
            fullWidth
            component={RouterLink}
            to={paths.login}
            state={{ from: { pathname: paths.verifyEmail } }}
          >
            Invia un nuovo link
          </Button>
        )
      }
    >
      Per sicurezza i link di conferma restano validi 24 ore.{' '}
      {isAuthenticated
        ? `Richiedine uno nuovo: lo invieremo a ${user.email}.`
        : 'Accedi e richiedine uno nuovo.'}
    </AuthResult>
  );
}
