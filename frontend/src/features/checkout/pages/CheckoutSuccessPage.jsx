import CheckCircleOutlined from '@mui/icons-material/CheckCircleOutlined';
import Inventory2Outlined from '@mui/icons-material/Inventory2Outlined';
import Avatar from '@mui/material/Avatar';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Chip from '@mui/material/Chip';
import Container from '@mui/material/Container';
import Divider from '@mui/material/Divider';
import Grid from '@mui/material/Grid';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink, useParams } from 'react-router';
import { paths } from '@/app/router/paths';
import { OrderTotals } from '@/components/data-display/OrderTotals';
import { ProductThumbnail } from '@/components/data-display/ProductThumbnail';
import { EmptyState } from '@/components/feedback/EmptyState';
import { ErrorState } from '@/components/feedback/ErrorState';
import { useOrder } from '@/features/account/hooks/useOrder';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { orderStatusColors } from '@/theme/statusColors';
import { formatDate, formatPostalLine, formatPrice } from '@/utils/format';

function Detail({ label, children }) {
  return (
    <Stack sx={{ gap: 0.5 }}>
      <Typography variant="caption" sx={{ color: 'text.secondary' }}>
        {label}
      </Typography>
      {children}
    </Stack>
  );
}

function OrderConfirmation({ order }) {
  const { user } = useAuth();
  const address = order.shipping_address;

  return (
    <Stack sx={{ gap: 4 }}>
      <Stack sx={{ gap: 2, alignItems: 'flex-start' }}>
        <Avatar sx={{ bgcolor: 'success.light', color: 'success.main' }}>
          <CheckCircleOutlined />
        </Avatar>
        <Typography variant="h2" component="h1">
          Grazie per il tuo ordine
        </Typography>
        <Typography variant="body1" sx={{ color: 'text.secondary' }}>
          Ti abbiamo inviato un'email di conferma a <strong>{user.email}</strong>. Ti scriveremo di
          nuovo quando l'ordine sarà spedito.
        </Typography>
      </Stack>
      <Card component="section" aria-label="Dati dell'ordine">
        <CardContent>
          <Stack sx={{ gap: 3 }}>
            <Grid container spacing={3}>
              <Grid size={6}>
                <Detail label="Numero d'ordine">
                  <Typography variant="subtitle2" component="p">
                    {order.number}
                  </Typography>
                </Detail>
              </Grid>
              <Grid size={6}>
                <Detail label="Data">
                  <Typography variant="body2">
                    {formatDate(order.paid_at ?? order.created_at)}
                  </Typography>
                </Detail>
              </Grid>
              <Grid size={6}>
                <Detail label="Stato">
                  <Chip
                    size="small"
                    label={order.status.label}
                    color={orderStatusColors[order.status.value]}
                    sx={{ alignSelf: 'flex-start' }}
                  />
                </Detail>
              </Grid>
              {order.payment ? (
                <Grid size={6}>
                  <Detail label="Pagamento">
                    <Typography variant="body2">
                      Carta che termina con {order.payment.card_last_four}
                    </Typography>
                  </Detail>
                </Grid>
              ) : null}
            </Grid>
            <Divider />
            <Stack sx={{ gap: 0.5 }}>
              <Typography variant="subtitle2" component="h2">
                Indirizzo di spedizione
              </Typography>
              <Typography variant="body2">{address.full_name}</Typography>
              <Typography variant="body2">
                {address.street}, {formatPostalLine(address)}
              </Typography>
              <Typography variant="body2">{address.phone}</Typography>
              <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                Spedizione standard, consegna in 24/48 ore
              </Typography>
            </Stack>
          </Stack>
        </CardContent>
      </Card>
      <Card component="section" aria-labelledby="order-recap-title">
        <CardContent>
          <Stack sx={{ gap: 3 }}>
            <Typography id="order-recap-title" variant="h5" component="h2">
              Riepilogo dell'ordine
            </Typography>
            <Stack component="ul" sx={{ listStyle: 'none', m: 0, p: 0, gap: 2 }}>
              {order.items.map((item, index) => (
                <Stack
                  key={`${item.product_sku}-${index}`}
                  component="li"
                  direction="row"
                  sx={{ gap: 2, alignItems: 'center' }}
                >
                  <ProductThumbnail cover={item.cover} name={item.product_name} />
                  <Stack sx={{ flexGrow: 1 }}>
                    <Typography variant="subtitle2" component="p">
                      {item.product_name}
                    </Typography>
                    <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                      Quantità {item.quantity}
                    </Typography>
                  </Stack>
                  <Typography variant="subtitle2" component="p">
                    {formatPrice(item.line_total)}
                  </Typography>
                </Stack>
              ))}
            </Stack>
            <Divider />
            <OrderTotals
              subtotal={order.subtotal}
              shippingCost={order.shipping_cost}
              total={order.total}
            />
          </Stack>
        </CardContent>
      </Card>
      <Stack direction={{ xs: 'column', sm: 'row' }} sx={{ gap: 1.5 }}>
        <Button
          component={RouterLink}
          to={paths.accountOrder(order.id)}
          variant="contained"
          size="large"
        >
          Vedi il tuo ordine
        </Button>
        <Button component={RouterLink} to={paths.products} variant="outlined" size="large">
          Continua lo shopping
        </Button>
      </Stack>
    </Stack>
  );
}

function ConfirmationSkeleton() {
  return (
    <Stack sx={{ gap: 3 }} role="status" aria-label="Caricamento dell'ordine">
      <Skeleton
        variant="circular"
        sx={{ width: (theme) => theme.spacing(5), height: (theme) => theme.spacing(5) }}
      />
      <Skeleton variant="text" width="60%" sx={{ typography: 'h2' }} />
      <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(25) }} />
      <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(30) }} />
    </Stack>
  );
}

export default function CheckoutSuccessPage() {
  const { orderId } = useParams();
  const orderQuery = useOrder(orderId);
  const notFound = orderQuery.isError && orderQuery.error.response?.status === 404;

  return (
    <Container maxWidth="sm" sx={{ py: { xs: 4, md: 6 } }}>
      {orderQuery.isPending ? <ConfirmationSkeleton /> : null}
      {notFound ? (
        <Card>
          <EmptyState
            icon={<Inventory2Outlined />}
            title="Non abbiamo trovato questo ordine"
            titleComponent="h1"
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
      {orderQuery.isSuccess ? <OrderConfirmation order={orderQuery.data} /> : null}
    </Container>
  );
}
