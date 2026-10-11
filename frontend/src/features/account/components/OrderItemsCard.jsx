import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { OrderTotals } from '@/components/data-display/OrderTotals';
import { ProductThumbnail } from '@/components/data-display/ProductThumbnail';
import { formatPrice } from '@/utils/format';

export function OrderItemsCard({ order }) {
  return (
    <Card component="section" aria-labelledby="order-items-title">
      <CardContent>
        <Stack sx={{ gap: 3 }}>
          <Typography id="order-items-title" variant="h5" component="h3">
            Prodotti
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
                <Stack sx={{ flexGrow: 1, minWidth: 0 }}>
                  <Typography variant="subtitle2" component="p">
                    {item.product_name}
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                    Quantità {item.quantity} · {formatPrice(item.unit_price)} cad.
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
            compactShipping
          />
        </Stack>
      </CardContent>
    </Card>
  );
}
