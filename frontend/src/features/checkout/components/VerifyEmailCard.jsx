import MailOutlined from '@mui/icons-material/MailOutlined';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useResendVerificationEmail } from '@/features/auth/hooks/useResendVerificationEmail';

export function VerifyEmailCard() {
  const { user } = useAuth();
  const resend = useResendVerificationEmail();

  return (
    <Card component="section" aria-labelledby="verify-email-title">
      <CardContent>
        <Stack sx={{ gap: 2, alignItems: 'flex-start' }}>
          <MailOutlined sx={{ color: 'warning.main' }} />
          <Typography id="verify-email-title" variant="h4" component="h2">
            Conferma la tua email
          </Typography>
          <Typography variant="body2">
            Prima di acquistare devi confermare il tuo indirizzo email. Ti abbiamo inviato un link a{' '}
            <strong>{user.email}</strong>: aprilo e poi torna qui.
          </Typography>
          <Button variant="contained" loading={resend.isPending} onClick={() => resend.mutate()}>
            Invia di nuovo l'email
          </Button>
          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
            Non la trovi? Controlla anche nella cartella spam.
          </Typography>
        </Stack>
      </CardContent>
    </Card>
  );
}
