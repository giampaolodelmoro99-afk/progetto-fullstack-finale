import ArrowBack from '@mui/icons-material/ArrowBack';
import CreditCardOutlined from '@mui/icons-material/CreditCardOutlined';
import Inventory2Outlined from '@mui/icons-material/Inventory2Outlined';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Divider from '@mui/material/Divider';
import Grid from '@mui/material/Grid';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { Link as RouterLink, useParams } from 'react-router';
import { paths } from '@/app/router/paths';
import { OrderStatusChip } from '@/components/data-display/OrderStatusChip';
import { EmptyState } from '@/components/feedback/EmptyState';
import { ErrorState } from '@/components/feedback/ErrorState';
import { CancelOrderDialog } from '@/features/account/components/CancelOrderDialog';
import { OrderItemsCard } from '@/features/account/components/OrderItemsCard';
import { OrderStatusTimeline } from '@/features/account/components/OrderStatusTimeline';
import { useOrder } from '@/features/account/hooks/useOrder';
import { formatDate, formatPostalLine, formatTime } from '@/utils/format';

function SideCard({ id, title, children }) {
  return (
    <Card component="section" aria-labelledby={id}>
      <CardContent>
        <Stack sx={{ gap: 2 }}>
          <Typography id={id} variant="h5" component="h3">
            {title}
          </Typography>
          {children}
        </Stack>
      </CardContent>
    </Card>
  );
}

function OrderDetail({ order, onRefresh }) {
  const [cancelling, setCancelling] = useState(false);
  const address = order.shipping_address;
  const placedAt = order.paid_at ?? order.created_at;

  return (
    <Stack sx={{ gap: 3 }}>
      <Stack sx={{ gap: 0.5 }}>
        <Stack direction="row" sx={{ gap: 1.5, alignItems: 'center', flexWrap: 'wrap' }}>
          <Typography variant="h3" component="h2">
            Ordine {order.number}
          </Typography>
          <OrderStatusChip status={order.status} />
        </Stack>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Effettuato il {formatDate(placedAt)} alle {formatTime(placedAt)}
        </Typography>
      </Stack>
      <Grid container spacing={3} sx={{ alignItems: 'flex-start' }}>
        <Grid size={{ xs: 12, md: 8 }}>
          <OrderItemsCard order={order} />
        </Grid>
        <Grid size={{ xs: 12, md: 4 }}>
          <Stack sx={{ gap: 3 }}>
            <SideCard id="order-status-title" title="Stato dell'ordine">
              <OrderStatusTimeline order={order} />
              {order.can_cancel ? (
                <>
                  <Divider />
                  <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                    Puoi annullare l&apos;ordine finché non viene spedito.
                  </Typography>
                  <Button variant="outlined" fullWidth onClick={() => setCancelling(true)}>
                    Annulla l&apos;ordine
                  </Button>
                </>
              ) : null}
            </SideCard>
            <SideCard id="order-address-title" title="Indirizzo di spedizione">
              <Stack sx={{ gap: 0.5 }}>
                <Typography variant="body2">{address.full_name}</Typography>
                <Typography variant="body2">
                  {address.street}, {formatPostalLine(address)}
                </Typography>
                <Typography variant="body2">{address.phone}</Typography>
                <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                  Spedizione standard, consegna in 24/48 ore
                </Typography>
              </Stack>
            </SideCard>
            {order.payment ? (
              <SideCard id="order-payment-title" title="Pagamento">
                <Stack direction="row" sx={{ gap: 1, alignItems: 'center' }}>
                  <CreditCardOutlined fontSize="small" />
                  <Typography variant="body2">
                    Carta che termina con {order.payment.card_last_four}
                  </Typography>
                </Stack>
              </SideCard>
            ) : null}
          </Stack>
        </Grid>
      </Grid>
      {cancelling ? (
        <CancelOrderDialog order={order} onClose={() => setCancelling(false)} onStale={onRefresh} />
      ) : null}
    </Stack>
  );
}

function OrderDetailSkeleton() {
  return (
    <Stack sx={{ gap: 3 }} role="status" aria-label="Caricamento dell'ordine">
      <Skeleton variant="text" width="50%" sx={{ typography: 'h3' }} />
      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 8 }}>
          <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(40) }} />
        </Grid>
        <Grid size={{ xs: 12, md: 4 }}>
          <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(30) }} />
        </Grid>
      </Grid>
    </Stack>
  );
}

export default function OrderPage() {
  const { orderId } = useParams();
  const orderQuery = useOrder(orderId);
  const notFound = orderQuery.isError && orderQuery.error.response?.status === 404;

  return (
    <Stack sx={{ gap: 3 }}>
      <Box>
        <Button
          component={RouterLink}
          to={paths.accountOrders}
          startIcon={<ArrowBack />}
          sx={{ ml: -2 }}
        >
          Tutti gli ordini
        </Button>
      </Box>
      {orderQuery.isPending ? <OrderDetailSkeleton /> : null}
      {notFound ? (
        <Card>
          <EmptyState
            icon={<Inventory2Outlined />}
            title="Non abbiamo trovato questo ordine"
            description="Controlla il link oppure cerca l'ordine tra i tuoi ordini."
            action={
              <Button component={RouterLink} to={paths.accountOrders} variant="contained">
                I miei ordini
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
      {orderQuery.isSuccess ? (
        <OrderDetail order={orderQuery.data} onRefresh={() => orderQuery.refetch()} />
      ) : null}
    </Stack>
  );
}
