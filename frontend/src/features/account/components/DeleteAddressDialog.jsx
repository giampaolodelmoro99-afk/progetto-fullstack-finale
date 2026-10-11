import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import { useId } from 'react';
import { useDeleteAddress } from '@/features/account/hooks/useDeleteAddress';
import { useNotify } from '@/hooks/useNotify';
import { formatPostalLine } from '@/utils/format';

export function DeleteAddressDialog({ address, onClose }) {
  const titleId = useId();
  const deleteAddress = useDeleteAddress();
  const notify = useNotify();

  function close() {
    if (!deleteAddress.isPending) {
      onClose();
    }
  }

  function confirm() {
    deleteAddress.mutate(address.id, {
      onSuccess: () => {
        onClose();
        notify({ message: 'Indirizzo eliminato' });
      },
    });
  }

  return (
    <Dialog open onClose={close} aria-labelledby={titleId} fullWidth maxWidth="xs">
      <DialogTitle id={titleId}>Vuoi eliminare questo indirizzo?</DialogTitle>
      <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        {deleteAddress.isError ? (
          <Alert severity="error">{deleteAddress.error.message}</Alert>
        ) : null}
        <DialogContentText variant="body2">
          {address.full_name}, {address.street}, {formatPostalLine(address)}. Gli ordini già
          effettuati non cambiano.
        </DialogContentText>
        {address.is_default ? (
          <DialogContentText variant="body2">
            Diventerà predefinito l&apos;indirizzo aggiunto più di recente.
          </DialogContentText>
        ) : null}
      </DialogContent>
      <DialogActions>
        <Button onClick={close} disabled={deleteAddress.isPending}>
          Mantieni l&apos;indirizzo
        </Button>
        <Button
          variant="contained"
          color="error"
          onClick={confirm}
          loading={deleteAddress.isPending}
        >
          Elimina l&apos;indirizzo
        </Button>
      </DialogActions>
    </Dialog>
  );
}
