import Button from '@mui/material/Button';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { AuthResult } from '@/features/auth/components/AuthResult';

export function ExpiredResetLink() {
  return (
    <AuthResult
      title="Il link è scaduto"
      actions={
        <>
          <Button variant="contained" component={RouterLink} to={paths.forgotPassword} fullWidth>
            Richiedi un nuovo link
          </Button>
          <Button component={RouterLink} to={paths.login} fullWidth>
            Torna all'accesso
          </Button>
        </>
      }
    >
      Per sicurezza il link per scegliere una nuova password resta valido 60 minuti. Richiedine uno
      nuovo e aprilo appena arriva.
    </AuthResult>
  );
}
