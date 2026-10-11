import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import { useId } from 'react';
import { useCancelOrder } from '@/features/account/hooks/useCancelOrder';
import { useNotify } from '@/hooks/useNotify';
import { formatPrice } from '@/utils/format';

export function CancelOrderDialog({ order, onClose, onStale }) {
  const titleId = useId();
  const cancelOrder = useCancelOrder(String(order.id));
  const notify = useNotify();

  function close() {
    if (!cancelOrder.isPending) {
      onClose();
    }
  }

  function confirm() {
    cancelOrder.mutate(undefined, {
      onSuccess: () => {
        onClose();
        notify({ message: 'Ordine annullato: riceverai il rimborso sulla tua carta.' });
      },
      onError: (error) => {
        if (error.code === 'invalid_order_transition') {
          onStale();
        }
      },
    });
  }

  return (
    <Dialog open onClose={close} aria-labelledby={titleId} fullWidth maxWidth="xs">
      <DialogTitle id={titleId}>Vuoi annullare l&apos;ordine?</DialogTitle>
      <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        {cancelOrder.isError ? <Alert severity="error">{cancelOrder.error.message}</Alert> : null}
        <DialogContentText variant="body2" sx={{ color: 'text.primary' }}>
          L&apos;ordine {order.number} non verrà spedito e riceverai il rimborso di{' '}
          <strong>{formatPrice(order.total)}</strong>
          {order.payment ? ` sulla carta che termina con ${order.payment.card_last_four}` : ''}.
        </DialogContentText>
        <DialogContentText variant="caption" sx={{ color: 'text.secondary' }}>
          Ti invieremo una conferma via email.
        </DialogContentText>
      </DialogContent>
      <DialogActions>
        <Button onClick={close} disabled={cancelOrder.isPending}>
          Mantieni l&apos;ordine
        </Button>
        <Button
          variant="contained"
          color="error"
          onClick={confirm}
          disabled={!order.can_cancel}
          loading={cancelOrder.isPending}
        >
          Annulla l&apos;ordine
        </Button>
      </DialogActions>
    </Dialog>
  );
}
