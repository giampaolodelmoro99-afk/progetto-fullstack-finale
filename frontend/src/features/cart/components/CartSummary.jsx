import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { OrderTotals } from '@/components/data-display/OrderTotals';
import { checkoutBlockedReason } from '@/features/cart/cartMessages';
import { FreeShippingProgress } from '@/features/cart/components/FreeShippingProgress';

export function CartSummary({ cart }) {
  const { summary } = cart;
  const blockedReason = cart.can_checkout ? null : checkoutBlockedReason(cart);

  return (
    <Card component="section" aria-labelledby="cart-summary-title">
      <CardContent>
        <Stack sx={{ gap: 3 }}>
          <Typography id="cart-summary-title" variant="h5" component="h2">
            Riepilogo
          </Typography>
          <FreeShippingProgress
            remaining={summary.free_shipping_remaining}
            threshold={summary.free_shipping_threshold}
          />
          <OrderTotals
            subtotal={summary.subtotal}
            shippingCost={summary.shipping_cost}
            total={summary.total}
          />
          {blockedReason ? <Alert severity="error">{blockedReason}</Alert> : null}
          <Button
            component={RouterLink}
            to={paths.checkout}
            variant="contained"
            size="large"
            fullWidth
            disabled={!cart.can_checkout}
          >
            Procedi al checkout
          </Button>
        </Stack>
      </CardContent>
    </Card>
  );
}
