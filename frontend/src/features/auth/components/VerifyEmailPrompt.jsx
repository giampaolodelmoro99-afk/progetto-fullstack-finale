import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { LoginRedirect } from '@/app/router/LoginRedirect';
import { paths } from '@/app/router/paths';
import { UserLoadError } from '@/app/router/UserLoadError';
import { PageLoader } from '@/components/feedback/PageLoader';
import { AuthResult } from '@/features/auth/components/AuthResult';
import { EmailConfirmed } from '@/features/auth/components/EmailConfirmed';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useResendVerificationEmail } from '@/features/auth/hooks/useResendVerificationEmail';

export function VerifyEmailPrompt() {
  const { user, isAuthenticated, isEmailVerified, isLoading, isError } = useAuth();
  const resend = useResendVerificationEmail();

  if (isLoading) {
    return <PageLoader />;
  }

  if (!isAuthenticated && isError) {
    return <UserLoadError />;
  }

  if (!isAuthenticated) {
    return <LoginRedirect />;
  }

  if (isEmailVerified) {
    return <EmailConfirmed email={user.email} />;
  }

  return (
    <AuthResult
      title="Controlla la tua email"
      actions={
        <>
          <Button
            variant="contained"
            fullWidth
            loading={resend.isPending}
            onClick={() => resend.mutate()}
          >
            Invia di nuovo il link
          </Button>
          <Typography variant="body2" sx={{ color: 'text.secondary', textAlign: 'center' }}>
            Non la trovi? Controlla anche nella cartella spam.
          </Typography>
          <Button component={RouterLink} to={paths.home} fullWidth>
            Torna al negozio
          </Button>
        </>
      }
    >
      Ti abbiamo inviato un link a {user.email}. Aprilo per confermare il tuo indirizzo: il link
      resta valido per 24 ore.
    </AuthResult>
  );
}
