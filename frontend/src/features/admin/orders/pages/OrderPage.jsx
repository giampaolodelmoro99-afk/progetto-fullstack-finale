import LocalMallOutlined from '@mui/icons-material/LocalMallOutlined';
import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Grid from '@mui/material/Grid';
import Link from '@mui/material/Link';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink, useParams } from 'react-router';
import { paths } from '@/app/router/paths';
import { OrderStatusChip } from '@/components/data-display/OrderStatusChip';
import { EmptyState } from '@/components/feedback/EmptyState';
import { ErrorState } from '@/components/feedback/ErrorState';
import { AdminSectionCard } from '@/features/admin/components/AdminSectionCard';
import { BackLink } from '@/features/admin/components/BackLink';
import { OrderActionsCard } from '@/features/admin/orders/components/OrderActionsCard';
import { OrderPaymentCard } from '@/features/admin/orders/components/OrderPaymentCard';
import { OrderProductsCard } from '@/features/admin/orders/components/OrderProductsCard';
import { StatusHistory } from '@/features/admin/orders/components/StatusHistory';
import { useAdminOrder } from '@/features/admin/orders/hooks/useAdminOrder';
import { formatDate, formatItemCount, formatPostalLine, formatTime } from '@/utils/format';

function failureReason(order) {
  return order.status_history.findLast((row) => row.status.value === 'cancelled')?.reason ?? null;
}

function FailedPaymentAlert({ order, reason }) {
  const card = order.payment ? ` (carta che termina con ${order.payment.card_last_four})` : '';

  return (
    <Alert severity="error">
      <AlertTitle>Pagamento non riuscito</AlertTitle>
      {reason ?? 'Pagamento rifiutato'}
      {card}. Questo tentativo non è visibile al cliente: nel suo account l&apos;ordine non compare.
      Le scorte sono state ripristinate e non c&apos;è niente da spedire né da rimborsare.
    </Alert>
  );
}

function CustomerCard({ order }) {
  return (
    <AdminSectionCard title="Cliente">
      <Stack sx={{ gap: 0.5 }}>
        <Typography variant="subtitle2" component="p">
          {order.customer.name}
        </Typography>
        {order.customer.email ? (
          <Link href={`mailto:${order.customer.email}`} variant="body2" color="inherit">
            {order.customer.email}
          </Link>
        ) : null}
        <Typography variant="body2">{order.shipping_address.phone}</Typography>
      </Stack>
    </AdminSectionCard>
  );
}

function AddressCard({ address }) {
  return (
    <AdminSectionCard title="Indirizzo di spedizione">
      <Stack sx={{ gap: 0.5 }}>
        <Typography variant="body2">{address.full_name}</Typography>
        <Typography variant="body2">{address.street}</Typography>
        <Typography variant="body2">{formatPostalLine(address)}</Typography>
        <Typography variant="caption" sx={{ color: 'text.secondary' }}>
          Spedizione standard, consegna in 24/48 ore
        </Typography>
      </Stack>
    </AdminSectionCard>
  );
}

function OrderDetail({ order }) {
  const placedAt = order.paid_at ?? order.created_at;
  const reason = failureReason(order);

  return (
    <Stack sx={{ gap: 3 }}>
      <Stack sx={{ gap: 0.5 }}>
        <Stack direction="row" sx={{ gap: 1.5, alignItems: 'center', flexWrap: 'wrap' }}>
          <Typography variant="h3" component="h2">
            {order.number}
          </Typography>
          <OrderStatusChip status={order.status} />
        </Stack>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Effettuato il {formatDate(placedAt)} alle {formatTime(placedAt)} ·{' '}
          {formatItemCount(order.items_count)}
        </Typography>
      </Stack>
      {order.failed_payment ? <FailedPaymentAlert order={order} reason={reason} /> : null}
      <Grid container spacing={3} sx={{ alignItems: 'flex-start' }}>
        <Grid size={{ xs: 12, md: 8 }}>
          <Stack sx={{ gap: 3 }}>
            <OrderProductsCard order={order} />
            <StatusHistory history={order.status_history} />
          </Stack>
        </Grid>
        <Grid size={{ xs: 12, md: 4 }}>
          <Stack sx={{ gap: 3 }}>
            <OrderActionsCard order={order} />
            <CustomerCard order={order} />
            <AddressCard address={order.shipping_address} />
            <OrderPaymentCard payment={order.payment} reason={reason} />
          </Stack>
        </Grid>
      </Grid>
    </Stack>
  );
}

function OrderDetailSkeleton() {
  return (
    <Stack sx={{ gap: 3 }} role="status" aria-label="Caricamento dell'ordine">
      <Skeleton variant="text" width="40%" sx={{ typography: 'h3' }} />
      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 8 }}>
          <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(48) }} />
        </Grid>
        <Grid size={{ xs: 12, md: 4 }}>
          <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(36) }} />
        </Grid>
      </Grid>
    </Stack>
  );
}

export default function OrderPage() {
  const { orderId } = useParams();
  const orderQuery = useAdminOrder(orderId);
  const notFound = orderQuery.isError && orderQuery.error.response?.status === 404;

  return (
    <Stack sx={{ gap: 3 }}>
      <Stack direction="row">
        <BackLink to={paths.adminOrders}>Tutti gli ordini</BackLink>
      </Stack>
      {orderQuery.isPending ? <OrderDetailSkeleton /> : null}
      {notFound ? (
        <Card>
          <EmptyState
            icon={<LocalMallOutlined />}
            title="Non abbiamo trovato questo ordine"
            description="Controlla il link oppure cerca l'ordine nell'elenco."
            action={
              <Button component={RouterLink} to={paths.adminOrders} variant="contained">
                Tutti gli ordini
              </Button>
            }
          />
        </Card>
      ) : null}
      {orderQuery.isError && !notFound ? (
        <ErrorState
          title="Non è stato possibile caricare l'ordine"
          message="Controlla la connessione e riprova."
          onRetry={() => orderQuery.refetch()}
        />
      ) : null}
      {orderQuery.isSuccess ? <OrderDetail order={orderQuery.data} /> : null}
    </Stack>
  );
}
