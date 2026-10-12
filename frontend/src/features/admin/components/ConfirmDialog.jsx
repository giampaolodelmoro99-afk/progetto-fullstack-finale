import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import { useId } from 'react';

export function ConfirmDialog({
  title,
  description,
  children,
  cancelLabel,
  confirmLabel,
  confirmColor = 'error',
  onConfirm,
  onClose,
  pending = false,
  error = null,
}) {
  const titleId = useId();

  function close() {
    if (!pending) {
      onClose();
    }
  }

  return (
    <Dialog open onClose={close} aria-labelledby={titleId} fullWidth maxWidth="xs">
      <DialogTitle id={titleId}>{title}</DialogTitle>
      <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        {error ? <Alert severity="error">{error}</Alert> : null}
        {description ? (
          <DialogContentText variant="body2" sx={{ color: 'text.secondary' }}>
            {description}
          </DialogContentText>
        ) : null}
        {children}
      </DialogContent>
      <DialogActions>
        <Button onClick={close} disabled={pending}>
          {cancelLabel}
        </Button>
        <Button variant="contained" color={confirmColor} onClick={onConfirm} loading={pending}>
          {confirmLabel}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
