import CreditCardOutlined from '@mui/icons-material/CreditCardOutlined';
import Inventory2Outlined from '@mui/icons-material/Inventory2Outlined';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { ConfirmDialog } from '@/features/admin/components/ConfirmDialog';
import { formatPrice } from '@/utils/format';

function restockedItems(order) {
  return order.items.map((item) => `${item.product_name} +${item.quantity}`).join(', ');
}

function DialogRow({ icon: Icon, children }) {
  return (
    <Stack direction="row" sx={{ gap: 1.5, alignItems: 'flex-start' }}>
      <Icon fontSize="small" aria-hidden="true" sx={{ mt: 0.25 }} />
      <Typography variant="body2">{children}</Typography>
    </Stack>
  );
}

export function CancelOrderDialog({ order, pending, error, onConfirm, onClose }) {
  return (
    <ConfirmDialog
      title={`Annullare l'ordine ${order.number}?`}
      cancelLabel="Mantieni l'ordine"
      confirmLabel="Annulla l'ordine"
      onConfirm={onConfirm}
      onClose={onClose}
      pending={pending}
      error={error}
    >
      <DialogRow icon={CreditCardOutlined}>
        <strong>Rimborso di {formatPrice(order.total)}</strong>
        {order.payment ? ` sulla carta che termina con ${order.payment.card_last_four}` : ''}.
      </DialogRow>
      <DialogRow icon={Inventory2Outlined}>
        <strong>Scorte ripristinate:</strong> {restockedItems(order)}.
      </DialogRow>
      <Typography variant="body2" sx={{ color: 'text.secondary' }}>
        L&apos;ordine passa ad Annullato. L&apos;operazione non si può annullare.
      </Typography>
    </ConfirmDialog>
  );
}
