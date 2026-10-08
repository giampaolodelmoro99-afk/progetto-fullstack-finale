import Button from '@mui/material/Button';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { AuthResult } from '@/features/auth/components/AuthResult';
import { supportSections } from '@/features/info/sections';

export function ExpiredEmailChangeLink({ cancellation = false }) {
  if (cancellation) {
    return (
      <AuthResult
        title="Il link è scaduto"
        actions={
          <>
            <Button
              variant="contained"
              component={RouterLink}
              to={paths.supportSection(supportSections.contacts)}
              fullWidth
            >
              Contatta l'assistenza
            </Button>
            <Button component={RouterLink} to={paths.home} fullWidth>
              Torna al negozio
            </Button>
          </>
        }
      >
        Per sicurezza il link resta valido 60 minuti e si può usare una sola volta. Se il cambio è
        già avvenuto e non l'hai chiesto tu, contatta l'assistenza.
      </AuthResult>
    );
  }

  return (
    <AuthResult
      title="Il link è scaduto"
      actions={
        <Button variant="contained" component={RouterLink} to={paths.accountProfile} fullWidth>
          Vai al tuo account
        </Button>
      }
    >
      Per sicurezza il link resta valido 60 minuti e si può usare una sola volta. Puoi chiedere un
      nuovo cambio dal tuo profilo.
    </AuthResult>
  );
}
