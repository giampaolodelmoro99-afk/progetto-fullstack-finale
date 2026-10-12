import CheckCircleOutline from '@mui/icons-material/CheckCircleOutlineOutlined';
import LocalShippingOutlined from '@mui/icons-material/LocalShippingOutlined';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { AdminSectionCard } from '@/features/admin/components/AdminSectionCard';
import { CancelOrderDialog } from '@/features/admin/orders/components/CancelOrderDialog';
import { useChangeOrderStatus } from '@/features/admin/orders/hooks/useChangeOrderStatus';
import { useNotify } from '@/hooks/useNotify';

const SUCCESS_MESSAGES = {
  shipped: 'Ordine segnato come spedito',
  delivered: 'Ordine segnato come consegnato',
  cancelled: 'Ordine annullato e rimborsato',
};

function actionNote(transitions) {
  if (transitions.includes('shipped')) {
    return "Dopo la spedizione l'ordine non si può più annullare.";
  }

  if (transitions.includes('delivered')) {
    return 'Un ordine spedito non si può annullare. Consegnato è lo stato finale.';
  }

  return null;
}

export function OrderActionsCard({ order }) {
  const changeStatus = useChangeOrderStatus(String(order.id));
  const notify = useNotify();
  const [cancelling, setCancelling] = useState(false);
  const [pendingStatus, setPendingStatus] = useState(null);
  const transitions = order.allowed_transitions;
  const note = actionNote(transitions);

  function change(status) {
    setPendingStatus(status);
    changeStatus.mutate(status, {
      onSuccess: () => {
        setCancelling(false);
        notify({ message: SUCCESS_MESSAGES[status] });
      },
      onSettled: () => setPendingStatus(null),
    });
  }

  function openCancel() {
    changeStatus.reset();
    setCancelling(true);
  }

  function closeCancel() {
    setCancelling(false);
    changeStatus.reset();
  }

  const inlineError = changeStatus.isError && !cancelling ? changeStatus.error.message : null;

  return (
    <AdminSectionCard title="Azioni">
      <Stack sx={{ gap: 1.5 }}>
        {inlineError ? <Alert severity="error">{inlineError}</Alert> : null}
        {transitions.length === 0 ? (
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Nessuna azione disponibile per questo ordine.
          </Typography>
        ) : null}
        {transitions.includes('shipped') ? (
          <Button
            variant="contained"
            fullWidth
            startIcon={<LocalShippingOutlined />}
            loading={pendingStatus === 'shipped'}
            disabled={changeStatus.isPending}
            onClick={() => change('shipped')}
          >
            Segna come spedito
          </Button>
        ) : null}
        {transitions.includes('delivered') ? (
          <Button
            variant="contained"
            fullWidth
            startIcon={<CheckCircleOutline />}
            loading={pendingStatus === 'delivered'}
            disabled={changeStatus.isPending}
            onClick={() => change('delivered')}
          >
            Segna come consegnato
          </Button>
        ) : null}
        {transitions.includes('cancelled') ? (
          <Button
            variant="outlined"
            color="error"
            fullWidth
            disabled={changeStatus.isPending}
            onClick={openCancel}
          >
            Annulla l&apos;ordine
          </Button>
        ) : null}
        {note ? (
          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
            {note}
          </Typography>
        ) : null}
      </Stack>
      {cancelling ? (
        <CancelOrderDialog
          order={order}
          pending={changeStatus.isPending}
          error={changeStatus.isError ? changeStatus.error.message : null}
          onConfirm={() => change('cancelled')}
          onClose={closeCancel}
        />
      ) : null}
    </AdminSectionCard>
  );
}
