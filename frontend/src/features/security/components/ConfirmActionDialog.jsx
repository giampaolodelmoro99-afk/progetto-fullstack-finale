import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import { useId } from 'react';

export function ConfirmActionDialog({
  title,
  description,
  keepLabel,
  confirmLabel,
  destructive = false,
  pending = false,
  error = null,
  onConfirm,
  onClose,
}) {
  const titleId = useId();
  const descriptionId = useId();

  function close() {
    if (!pending) {
      onClose();
    }
  }

  return (
    <Dialog
      open
      onClose={close}
      aria-labelledby={titleId}
      aria-describedby={descriptionId}
      fullWidth
      maxWidth="xs"
    >
      <DialogTitle id={titleId}>{title}</DialogTitle>
      <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        {error ? <Alert severity="error">{error}</Alert> : null}
        <DialogContentText id={descriptionId} variant="body2">
          {description}
        </DialogContentText>
      </DialogContent>
      <DialogActions>
        <Button onClick={close} disabled={pending}>
          {keepLabel}
        </Button>
        <Button
          variant="contained"
          color={destructive ? 'error' : 'primary'}
          onClick={onConfirm}
          loading={pending}
        >
          {confirmLabel}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
