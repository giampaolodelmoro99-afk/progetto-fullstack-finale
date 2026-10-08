import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import { OrderTotals } from '@/components/data-display/OrderTotals';
import { CheckoutItemList } from '@/features/checkout/components/CheckoutItemList';

export function OrderSummaryContent({ cart }) {
  return (
    <Stack sx={{ gap: 3 }}>
      <CheckoutItemList items={cart.items} label="Prodotti dell'ordine" />
      <Divider />
      <OrderTotals
        subtotal={cart.summary.subtotal}
        shippingCost={cart.summary.shipping_cost}
        total={cart.summary.total}
      />
    </Stack>
  );
}
