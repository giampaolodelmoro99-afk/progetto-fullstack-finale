import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { OrderStatusChip } from '@/components/data-display/OrderStatusChip';
import { FailedPaymentNote } from '@/features/admin/orders/components/FailedPaymentNote';
import { formatDayTimeShort, formatItemCount, formatPrice } from '@/utils/format';

function customerLine(customer) {
  return customer.email ? `${customer.name} · ${customer.email}` : customer.name;
}

export function OrderCards({ orders }) {
  return (
    <Stack component="ul" aria-label="Ordini" sx={{ listStyle: 'none', m: 0, p: 0, gap: 1.5 }}>
      {orders.map((order) => (
        <li key={order.id}>
          <Card>
            <CardActionArea
              component={RouterLink}
              to={paths.adminOrder(order.id)}
              aria-label={`Ordine ${order.number}`}
            >
              <CardContent>
                <Stack sx={{ gap: 0.5 }}>
                  <Stack
                    direction="row"
                    sx={{ gap: 1, justifyContent: 'space-between', alignItems: 'center' }}
                  >
                    <Typography variant="subtitle2" component="h3">
                      {order.number}
                    </Typography>
                    <OrderStatusChip status={order.status} />
                  </Stack>
                  <Typography variant="body2" sx={{ overflowWrap: 'anywhere' }}>
                    {customerLine(order.customer)}
                  </Typography>
                  <Stack
                    direction="row"
                    sx={{ gap: 1, justifyContent: 'space-between', alignItems: 'center' }}
                  >
                    <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                      {formatDayTimeShort(order.created_at)} · {formatItemCount(order.items_count)}
                    </Typography>
                    <Typography variant="subtitle2" component="span">
                      {formatPrice(order.total)}
                    </Typography>
                  </Stack>
                  {order.failed_payment ? <FailedPaymentNote /> : null}
                </Stack>
              </CardContent>
            </CardActionArea>
          </Card>
        </li>
      ))}
    </Stack>
  );
}
