import ChevronRight from '@mui/icons-material/ChevronRight';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { OrderStatusChip } from '@/components/data-display/OrderStatusChip';
import { formatDate, formatItemCount, formatPrice } from '@/utils/format';

const HEADER_SX = { typography: 'body2', color: 'text.secondary' };

export function OrdersTable({ orders }) {
  return (
    <Card>
      <TableContainer>
        <Table aria-label="I miei ordini">
          <TableHead>
            <TableRow>
              <TableCell sx={HEADER_SX}>Numero d&apos;ordine</TableCell>
              <TableCell sx={HEADER_SX}>Data</TableCell>
              <TableCell sx={HEADER_SX}>Articoli</TableCell>
              <TableCell sx={HEADER_SX} align="right">
                Totale
              </TableCell>
              <TableCell sx={HEADER_SX}>Stato</TableCell>
              <TableCell sx={HEADER_SX}>
                <Typography component="span" sx={(theme) => theme.mixins.visuallyHidden}>
                  Azioni
                </Typography>
              </TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {orders.map((order) => (
              <TableRow key={order.id}>
                <TableCell>
                  <Typography variant="subtitle2" component="span">
                    {order.number}
                  </Typography>
                </TableCell>
                <TableCell>{formatDate(order.paid_at ?? order.created_at)}</TableCell>
                <TableCell sx={{ color: 'text.secondary' }}>
                  {formatItemCount(order.items_count)}
                </TableCell>
                <TableCell align="right">
                  <Typography variant="price" component="span">
                    {formatPrice(order.total)}
                  </Typography>
                </TableCell>
                <TableCell>
                  <OrderStatusChip status={order.status} />
                </TableCell>
                <TableCell align="right">
                  <Button
                    component={RouterLink}
                    to={paths.accountOrder(order.id)}
                    size="small"
                    endIcon={<ChevronRight />}
                    aria-label={`Dettagli dell'ordine ${order.number}`}
                  >
                    Dettagli
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Card>
  );
}
