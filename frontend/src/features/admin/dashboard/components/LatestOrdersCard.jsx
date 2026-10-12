import ArrowForward from '@mui/icons-material/ArrowForward';
import Button from '@mui/material/Button';
import Divider from '@mui/material/Divider';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import ListItemButton from '@mui/material/ListItemButton';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';
import { Fragment } from 'react';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { OrderStatusChip } from '@/components/data-display/OrderStatusChip';
import { AdminSectionCard } from '@/features/admin/components/AdminSectionCard';
import { AdminTableHead } from '@/features/admin/components/AdminTableHead';
import { OrderNumberLink } from '@/features/admin/components/OrderNumberLink';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { formatDateTimeShort, formatDayTimeShort, formatPrice } from '@/utils/format';

const COLUMNS = [
  { key: 'number', label: 'Ordine' },
  { key: 'customer', label: 'Cliente' },
  { key: 'date', label: 'Data' },
  { key: 'total', label: 'Totale', align: 'right' },
  { key: 'status', label: 'Stato' },
];

function OrdersTable({ orders }) {
  return (
    <TableContainer>
      <Table aria-label="Ultimi ordini">
        <AdminTableHead columns={COLUMNS} />
        <TableBody>
          {orders.map((order) => (
            <TableRow key={order.id}>
              <TableCell>
                <OrderNumberLink order={order} />
              </TableCell>
              <TableCell>{order.customer.name}</TableCell>
              <TableCell sx={{ color: 'text.secondary' }}>
                {formatDateTimeShort(order.created_at)}
              </TableCell>
              <TableCell align="right">
                <Typography variant="subtitle2" component="span">
                  {formatPrice(order.total)}
                </Typography>
              </TableCell>
              <TableCell>
                <OrderStatusChip status={order.status} />
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}

function OrdersList({ orders }) {
  return (
    <List disablePadding aria-label="Ultimi ordini">
      {orders.map((order, index) => (
        <Fragment key={order.id}>
          {index > 0 ? <Divider component="li" /> : null}
          <ListItem disablePadding>
            <ListItemButton
              component={RouterLink}
              to={paths.adminOrder(order.id)}
              aria-label={`Ordine ${order.number}`}
              sx={{ px: 3, py: 1.5 }}
            >
              <Stack sx={{ gap: 0.5, width: 1 }}>
                <Stack
                  direction="row"
                  sx={{ justifyContent: 'space-between', alignItems: 'center', gap: 1 }}
                >
                  <Typography variant="subtitle2" component="span">
                    {order.number}
                  </Typography>
                  <OrderStatusChip status={order.status} />
                </Stack>
                <Stack
                  direction="row"
                  sx={{ justifyContent: 'space-between', alignItems: 'center', gap: 1 }}
                >
                  <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                    {order.customer.name} · {formatDayTimeShort(order.created_at)}
                  </Typography>
                  <Typography variant="subtitle2" component="span">
                    {formatPrice(order.total)}
                  </Typography>
                </Stack>
              </Stack>
            </ListItemButton>
          </ListItem>
        </Fragment>
      ))}
    </List>
  );
}

export function LatestOrdersCard({ orders }) {
  const isDesktop = useIsDesktop();

  return (
    <AdminSectionCard
      title="Ultimi ordini"
      disableContentPadding
      aside={
        <Button component={RouterLink} to={paths.adminOrders} endIcon={<ArrowForward />}>
          Vedi tutti
        </Button>
      }
    >
      <Divider />
      {orders.length === 0 ? (
        <Typography variant="body2" sx={{ color: 'text.secondary', p: 3 }}>
          Non ci sono ancora ordini pagati.
        </Typography>
      ) : null}
      {orders.length > 0 && isDesktop ? <OrdersTable orders={orders} /> : null}
      {orders.length > 0 && !isDesktop ? <OrdersList orders={orders} /> : null}
    </AdminSectionCard>
  );
}
