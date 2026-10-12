import CheckCircleOutline from '@mui/icons-material/CheckCircleOutlineOutlined';
import CreditCardOutlined from '@mui/icons-material/CreditCardOutlined';
import ErrorOutline from '@mui/icons-material/ErrorOutlineOutlined';
import Replay from '@mui/icons-material/Replay';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { AdminSectionCard } from '@/features/admin/components/AdminSectionCard';
import { paymentStatusColors } from '@/theme/statusColors';
import { formatDateTimeShort, formatPrice } from '@/utils/format';

const STATUS_ICONS = {
  succeeded: CheckCircleOutline,
  failed: ErrorOutline,
  refunded: Replay,
};

function PaymentRow({ label, children }) {
  return (
    <Stack direction="row" sx={{ justifyContent: 'space-between', gap: 2 }}>
      <Typography variant="body2" sx={{ color: 'text.secondary' }}>
        {label}
      </Typography>
      {children}
    </Stack>
  );
}

export function OrderPaymentCard({ payment, reason }) {
  if (!payment) {
    return (
      <AdminSectionCard title="Pagamento">
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Nessun pagamento registrato.
        </Typography>
      </AdminSectionCard>
    );
  }

  const StatusIcon = STATUS_ICONS[payment.status.value];

  return (
    <AdminSectionCard title="Pagamento">
      <Stack sx={{ gap: 1.5 }}>
        <Stack direction="row" sx={{ gap: 1, alignItems: 'center' }}>
          <CreditCardOutlined fontSize="small" aria-hidden="true" />
          <Typography variant="body2">Carta che termina con {payment.card_last_four}</Typography>
        </Stack>
        <PaymentRow label="Esito">
          <Stack
            direction="row"
            sx={{
              gap: 0.5,
              alignItems: 'center',
              color: paymentStatusColors[payment.status.value],
            }}
          >
            {StatusIcon ? <StatusIcon fontSize="small" aria-hidden="true" /> : null}
            <Typography variant="subtitle2" component="span" sx={{ color: 'inherit' }}>
              {payment.status.label}
            </Typography>
          </Stack>
        </PaymentRow>
        <PaymentRow label="Importo">
          <Typography variant="subtitle2" component="span">
            {formatPrice(payment.amount)}
          </Typography>
        </PaymentRow>
        <PaymentRow label="Data">
          <Typography variant="body2" component="span">
            {formatDateTimeShort(payment.created_at)}
          </Typography>
        </PaymentRow>
        {payment.status.value === 'failed' && reason ? (
          <PaymentRow label="Motivo">
            <Typography variant="body2" component="span" sx={{ textAlign: 'right' }}>
              {reason}
            </Typography>
          </PaymentRow>
        ) : null}
      </Stack>
    </AdminSectionCard>
  );
}
