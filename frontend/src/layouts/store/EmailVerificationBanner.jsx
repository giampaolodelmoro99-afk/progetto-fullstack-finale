import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import { useMatches } from 'react-router';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useResendVerificationEmail } from '@/features/auth/hooks/useResendVerificationEmail';

export function EmailVerificationBanner() {
  const { user, isAuthenticated, isEmailVerified } = useAuth();
  const matches = useMatches();
  const resend = useResendVerificationEmail();
  const hidden = matches.some((match) => match.handle?.hideEmailBanner);

  if (!isAuthenticated || isEmailVerified || hidden) {
    return null;
  }

  return (
    <Container maxWidth="lg" sx={{ pt: { xs: 2, md: 3 } }}>
      <Alert
        severity="warning"
        action={
          <Button
            color="inherit"
            size="small"
            loading={resend.isPending}
            onClick={() => resend.mutate()}
          >
            Invia di nuovo l'email
          </Button>
        }
      >
        Conferma il tuo indirizzo email per completare gli acquisti. Ti abbiamo inviato un link a{' '}
        {user.email}.
      </Alert>
    </Container>
  );
}
