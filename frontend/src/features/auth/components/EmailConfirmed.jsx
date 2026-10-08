import Button from '@mui/material/Button';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { AuthResult } from '@/features/auth/components/AuthResult';

export function EmailConfirmed({ email }) {
  return (
    <AuthResult
      title="Email confermata"
      actions={
        <>
          <Button variant="contained" component={RouterLink} to={paths.home} fullWidth>
            Vai al negozio
          </Button>
          <Button variant="outlined" component={RouterLink} to={paths.accountProfile} fullWidth>
            Vai al tuo account
          </Button>
        </>
      }
    >
      Grazie: l'indirizzo {email} è confermato. Ora puoi completare i tuoi acquisti.
    </AuthResult>
  );
}
