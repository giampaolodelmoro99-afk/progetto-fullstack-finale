import Close from '@mui/icons-material/Close';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import { useIsMutating } from '@tanstack/react-query';
import { useId } from 'react';
import { ConfirmIdentityForm } from '@/features/security/components/ConfirmIdentityForm';
import { CONFIRM_IDENTITY_MUTATION } from '@/features/security/hooks/useConfirmIdentity';

const DEFAULT_IDENTITY_PROMPT = 'Per continuare, conferma che sei tu.';

export function ConfirmIdentityDialog({ prompt, onConfirmed, onCancel }) {
  const titleId = useId();
  const sending = useIsMutating({ mutationKey: CONFIRM_IDENTITY_MUTATION }) > 0;

  function cancel() {
    if (!sending) {
      onCancel();
    }
  }

  return (
    <Dialog open onClose={cancel} aria-labelledby={titleId} fullWidth maxWidth="xs">
      <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between', pr: 1 }}>
        <DialogTitle id={titleId}>Conferma la tua identità</DialogTitle>
        <IconButton aria-label="Chiudi" onClick={cancel} disabled={sending}>
          <Close />
        </IconButton>
      </Stack>
      <ConfirmIdentityForm
        prompt={prompt ?? DEFAULT_IDENTITY_PROMPT}
        onConfirmed={onConfirmed}
        onCancel={cancel}
      />
    </Dialog>
  );
}
