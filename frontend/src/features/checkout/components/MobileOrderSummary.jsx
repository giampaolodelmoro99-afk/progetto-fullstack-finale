import ExpandMore from '@mui/icons-material/ExpandMore';
import ShoppingBagOutlined from '@mui/icons-material/ShoppingBagOutlined';
import Accordion from '@mui/material/Accordion';
import AccordionDetails from '@mui/material/AccordionDetails';
import AccordionSummary from '@mui/material/AccordionSummary';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { EditCartLink } from '@/features/checkout/components/EditCartLink';
import { OrderSummaryContent } from '@/features/checkout/components/OrderSummaryContent';
import { formatItemCount, formatPrice } from '@/utils/format';

export function MobileOrderSummary({ cart }) {
  return (
    <Accordion disableGutters variant="outlined" sx={{ borderRadius: 2 }}>
      <AccordionSummary expandIcon={<ExpandMore />}>
        <Stack direction="row" sx={{ alignItems: 'center', gap: 1.5, flexGrow: 1, mr: 1 }}>
          <ShoppingBagOutlined fontSize="small" />
          <Stack sx={{ flexGrow: 1 }}>
            <Typography variant="subtitle2" component="h2">
              Riepilogo ordine
            </Typography>
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              {formatItemCount(cart.summary.items_count)}
            </Typography>
          </Stack>
          <Typography variant="price" component="p">
            {formatPrice(cart.summary.total)}
          </Typography>
        </Stack>
      </AccordionSummary>
      <AccordionDetails>
        <Stack sx={{ gap: 2 }}>
          <Stack direction="row" sx={{ justifyContent: 'flex-end' }}>
            <EditCartLink />
          </Stack>
          <OrderSummaryContent cart={cart} />
        </Stack>
      </AccordionDetails>
    </Accordion>
  );
}
