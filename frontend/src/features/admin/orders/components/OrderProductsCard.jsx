import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';
import { OrderTotals } from '@/components/data-display/OrderTotals';
import { ProductThumbnail } from '@/components/data-display/ProductThumbnail';
import { AdminSectionCard } from '@/features/admin/components/AdminSectionCard';
import { AdminTableHead } from '@/features/admin/components/AdminTableHead';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { formatPrice } from '@/utils/format';

const COLUMNS = [
  { key: 'product', label: 'Prodotto' },
  { key: 'unit', label: 'Prezzo unitario', align: 'right' },
  { key: 'quantity', label: 'Quantità', align: 'right' },
  { key: 'total', label: 'Totale', align: 'right' },
];

function ProductName({ item }) {
  return (
    <Stack direction="row" sx={{ gap: 2, alignItems: 'center' }}>
      <ProductThumbnail cover={item.cover} name={item.product_name} />
      <Stack sx={{ minWidth: 0 }}>
        <Typography variant="subtitle2" component="p">
          {item.product_name}
        </Typography>
        <Typography variant="caption" sx={{ color: 'text.secondary' }}>
          {item.product_sku}
        </Typography>
      </Stack>
    </Stack>
  );
}

function ItemsTable({ items }) {
  return (
    <TableContainer>
      <Table aria-label="Prodotti dell'ordine">
        <AdminTableHead columns={COLUMNS} />
        <TableBody>
          {items.map((item, index) => (
            <TableRow key={`${item.product_sku}-${index}`}>
              <TableCell>
                <ProductName item={item} />
              </TableCell>
              <TableCell align="right">{formatPrice(item.unit_price)}</TableCell>
              <TableCell align="right">{item.quantity}</TableCell>
              <TableCell align="right">
                <Typography variant="subtitle2" component="span">
                  {formatPrice(item.line_total)}
                </Typography>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}

function ItemsList({ items }) {
  return (
    <Stack
      component="ul"
      aria-label="Prodotti dell'ordine"
      sx={{ listStyle: 'none', m: 0, px: 3, py: 2, gap: 2 }}
    >
      {items.map((item, index) => (
        <Stack key={`${item.product_sku}-${index}`} component="li" sx={{ gap: 1 }}>
          <ProductName item={item} />
          <Stack direction="row" sx={{ justifyContent: 'space-between', gap: 1 }}>
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              Quantità {item.quantity} · {formatPrice(item.unit_price)} cad.
            </Typography>
            <Typography variant="subtitle2" component="span">
              {formatPrice(item.line_total)}
            </Typography>
          </Stack>
        </Stack>
      ))}
    </Stack>
  );
}

export function OrderProductsCard({ order }) {
  const isDesktop = useIsDesktop();

  return (
    <AdminSectionCard
      title="Prodotti"
      disableContentPadding
      aside={
        <Typography variant="caption" sx={{ color: 'text.secondary' }}>
          Prezzi al momento dell&apos;acquisto
        </Typography>
      }
    >
      <Divider />
      {isDesktop ? <ItemsTable items={order.items} /> : <ItemsList items={order.items} />}
      <Box sx={{ p: 3, display: 'flex', justifyContent: 'flex-end' }}>
        <Box sx={{ width: { xs: 1, md: 1 / 2 } }}>
          <OrderTotals
            subtotal={order.subtotal}
            shippingCost={order.shipping_cost}
            total={order.total}
            compactShipping
          />
        </Box>
      </Box>
    </AdminSectionCard>
  );
}
