import Dialog from '@mui/material/Dialog';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import { useIsMutating } from '@tanstack/react-query';
import { useId } from 'react';
import { AddressForm } from '@/features/account/components/AddressForm';
import { SAVE_ADDRESS_MUTATION } from '@/features/account/hooks/useCreateAddress';
import { useIsDesktop } from '@/hooks/useIsDesktop';

export function AddressDialog({ address, onClose, onSaved }) {
  const titleId = useId();
  const isDesktop = useIsDesktop();
  const saving = useIsMutating({ mutationKey: SAVE_ADDRESS_MUTATION }) > 0;

  function close() {
    if (!saving) {
      onClose();
    }
  }

  return (
    <Dialog
      open
      onClose={close}
      aria-labelledby={titleId}
      fullWidth
      maxWidth="sm"
      fullScreen={!isDesktop}
    >
      <DialogTitle id={titleId}>{address ? 'Modifica indirizzo' : 'Nuovo indirizzo'}</DialogTitle>
      <DialogContent sx={{ pt: 1 }}>
        <AddressForm address={address} showTitle={false} onSaved={onSaved} onCancel={close} />
      </DialogContent>
    </Dialog>
  );
}
