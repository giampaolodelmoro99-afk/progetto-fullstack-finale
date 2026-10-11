import Check from '@mui/icons-material/Check';
import MailOutlined from '@mui/icons-material/MailOutlined';
import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Chip from '@mui/material/Chip';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { NewEmailDialog } from '@/features/account/components/NewEmailDialog';
import { EMAIL_CHANGE_PROMPT } from '@/features/account/api/profileApi';
import { useCancelPendingEmailChange } from '@/features/account/hooks/useCancelPendingEmailChange';
import { useIdentityConfirmation } from '@/features/security/hooks/useIdentityConfirmation';
import { useNotify } from '@/hooks/useNotify';
import { formatDateTime } from '@/utils/format';

function PendingEmailChange({ user }) {
  const cancelRequest = useCancelPendingEmailChange();
  const notify = useNotify();
  const { new_email: newEmail, expires_at: expiresAt } = user.pending_email_change;

  function cancel() {
    cancelRequest.mutate(undefined, {
      onSuccess: () => notify({ message: 'Richiesta annullata' }),
      onError: (error) => {
        if (error.status !== 404) {
          notify({ message: error.message, severity: 'error' });
        }
      },
    });
  }

  return (
    <Alert severity="info" icon={<MailOutlined fontSize="inherit" />} sx={{ py: 2 }}>
      <AlertTitle>Cambio email in attesa di conferma</AlertTitle>
      <Stack sx={{ gap: 1, alignItems: 'flex-start' }}>
        <Typography variant="body2">
          Abbiamo inviato un link a <strong>{newEmail}</strong>. Finché non lo apri, continuerai a
          usare {user.email}.
        </Typography>
        <Typography variant="caption" sx={{ color: 'text.secondary' }}>
          Scadenza del link: {formatDateTime(expiresAt)}.
        </Typography>
        <Button
          size="small"
          onClick={cancel}
          loading={cancelRequest.isPending}
          sx={{ color: 'info.main', px: 0 }}
        >
          Annulla la richiesta
        </Button>
      </Stack>
    </Alert>
  );
}

export function EmailCard({ user }) {
  const { confirmIdentity } = useIdentityConfirmation();
  const [dialogOpen, setDialogOpen] = useState(false);
  const verified = Boolean(user.email_verified_at);
  const pending = user.pending_email_change !== null && user.pending_email_change !== undefined;

  function startEmailChange() {
    confirmIdentity({ prompt: EMAIL_CHANGE_PROMPT }).then(
      () => setDialogOpen(true),
      () => {},
    );
  }

  return (
    <Card component="section" aria-labelledby="email-card-title">
      <CardContent>
        <Stack sx={{ gap: 3 }}>
          <Typography id="email-card-title" variant="h5" component="h3">
            Email
          </Typography>
          <Stack
            direction={{ xs: 'column', sm: 'row' }}
            sx={{ gap: 2, justifyContent: 'space-between', alignItems: { sm: 'center' } }}
          >
            <Stack sx={{ gap: 0.5, minWidth: 0 }}>
              <Stack direction="row" sx={{ gap: 1, alignItems: 'center', flexWrap: 'wrap' }}>
                <Typography variant="body1" sx={{ overflowWrap: 'anywhere' }}>
                  {user.email}
                </Typography>
                {verified ? (
                  <Chip
                    size="small"
                    variant="soft"
                    color="success"
                    icon={<Check fontSize="small" />}
                    label="Confermata"
                  />
                ) : (
                  <Chip size="small" variant="soft" color="warning" label="Da confermare" />
                )}
              </Stack>
              <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                La usi per accedere e per ricevere le email sui tuoi ordini.
              </Typography>
            </Stack>
            {pending ? null : (
              <Button
                variant="outlined"
                onClick={startEmailChange}
                sx={{ flexShrink: 0, width: { xs: 1, sm: 'auto' } }}
              >
                Cambia email
              </Button>
            )}
          </Stack>
          {pending ? <PendingEmailChange user={user} /> : null}
        </Stack>
      </CardContent>
      <NewEmailDialog open={dialogOpen} onClose={() => setDialogOpen(false)} />
    </Card>
  );
}
