import ChevronRight from '@mui/icons-material/ChevronRight';
import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { OrderStatusChip } from '@/components/data-display/OrderStatusChip';
import { formatDate, formatItemCount, formatPrice } from '@/utils/format';

export function OrderCardList({ orders }) {
  return (
    <Stack
      component="ul"
      aria-label="I miei ordini"
      sx={{ listStyle: 'none', m: 0, p: 0, gap: 1.5 }}
    >
      {orders.map((order) => (
        <li key={order.id}>
          <Card>
            <CardActionArea
              component={RouterLink}
              to={paths.accountOrder(order.id)}
              aria-label={`Ordine ${order.number}`}
            >
              <CardContent>
                <Stack direction="row" sx={{ gap: 1, alignItems: 'center' }}>
                  <Stack sx={{ gap: 0.5, flexGrow: 1, minWidth: 0 }}>
                    <Stack
                      direction="row"
                      sx={{ gap: 1, justifyContent: 'space-between', alignItems: 'center' }}
                    >
                      <Typography variant="subtitle2" component="h3">
                        {order.number}
                      </Typography>
                      <OrderStatusChip status={order.status} />
                    </Stack>
                    <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                      {formatDate(order.paid_at ?? order.created_at)} ·{' '}
                      {formatItemCount(order.items_count)}
                    </Typography>
                    <Typography variant="price" component="p">
                      {formatPrice(order.total)}
                    </Typography>
                  </Stack>
                  <ChevronRight sx={{ color: 'text.secondary' }} />
                </Stack>
              </CardContent>
            </CardActionArea>
          </Card>
        </li>
      ))}
    </Stack>
  );
}
