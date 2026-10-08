import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { EditCartLink } from '@/features/checkout/components/EditCartLink';
import { OrderSummaryContent } from '@/features/checkout/components/OrderSummaryContent';

export function OrderSummaryCard({ cart }) {
  return (
    <Card component="section" aria-labelledby="order-summary-title">
      <CardContent>
        <Stack sx={{ gap: 3 }}>
          <Stack
            direction="row"
            sx={{ justifyContent: 'space-between', alignItems: 'baseline', gap: 2 }}
          >
            <Typography id="order-summary-title" variant="h5" component="h2">
              Riepilogo ordine
            </Typography>
            <EditCartLink />
          </Stack>
          <OrderSummaryContent cart={cart} />
        </Stack>
      </CardContent>
    </Card>
  );
}
