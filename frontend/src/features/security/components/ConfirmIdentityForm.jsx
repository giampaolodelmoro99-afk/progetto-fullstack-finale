import DialogContent from '@mui/material/DialogContent';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { ErrorState } from '@/components/feedback/ErrorState';
import { ConfirmIdentityFields } from '@/features/security/components/ConfirmIdentityFields';
import { useSecurityOverview } from '@/features/security/hooks/useSecurityOverview';

export function ConfirmIdentityForm({ prompt, onConfirmed, onCancel }) {
  const overview = useSecurityOverview();

  if (overview.isPending) {
    return (
      <DialogContent sx={{ pt: 0 }}>
        <Stack sx={{ gap: 2 }} role="status" aria-busy="true" aria-label="Caricamento in corso">
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            {prompt}
          </Typography>
          <Skeleton variant="text" />
          <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(7) }} />
        </Stack>
      </DialogContent>
    );
  }

  if (overview.isError) {
    return (
      <DialogContent sx={{ pt: 0 }}>
        <Typography variant="body2" sx={{ color: 'text.secondary', mb: 2 }}>
          {prompt}
        </Typography>
        <ErrorState
          message="Non è stato possibile caricare i dati di sicurezza del tuo account."
          onRetry={() => overview.refetch()}
        />
      </DialogContent>
    );
  }

  return (
    <ConfirmIdentityFields
      overview={overview.data}
      prompt={prompt}
      onConfirmed={onConfirmed}
      onCancel={onCancel}
    />
  );
}
