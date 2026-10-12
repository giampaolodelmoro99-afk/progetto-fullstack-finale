import ChevronRight from '@mui/icons-material/ChevronRight';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { OrderStatusChip } from '@/components/data-display/OrderStatusChip';
import { OrderNumberLink } from '@/features/admin/components/OrderNumberLink';
import { FailedPaymentNote } from '@/features/admin/orders/components/FailedPaymentNote';
import { formatDateTimeShort, formatPrice } from '@/utils/format';

export function OrderRows({ orders }) {
  return (
    <TableBody>
      {orders.map((order) => (
        <TableRow key={order.id}>
          <TableCell>
            <OrderNumberLink order={order} />
          </TableCell>
          <TableCell>
            <Stack>
              <Typography variant="subtitle2" component="span">
                {order.customer.name}
              </Typography>
              {order.customer.email ? (
                <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                  {order.customer.email}
                </Typography>
              ) : null}
            </Stack>
          </TableCell>
          <TableCell sx={{ color: 'text.secondary' }}>
            {formatDateTimeShort(order.created_at)}
          </TableCell>
          <TableCell align="right">{order.items_count}</TableCell>
          <TableCell align="right">
            <Typography variant="subtitle2" component="span">
              {formatPrice(order.total)}
            </Typography>
          </TableCell>
          <TableCell>
            <Stack sx={{ alignItems: 'flex-start', gap: 0.5 }}>
              <OrderStatusChip status={order.status} />
              {order.failed_payment ? <FailedPaymentNote /> : null}
            </Stack>
          </TableCell>
          <TableCell align="right">
            <IconButton
              component={RouterLink}
              to={paths.adminOrder(order.id)}
              aria-label={`Dettagli dell'ordine ${order.number}`}
            >
              <ChevronRight />
            </IconButton>
          </TableCell>
        </TableRow>
      ))}
    </TableBody>
  );
}
