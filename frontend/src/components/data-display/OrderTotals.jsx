import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { formatPrice } from '@/utils/format';

function TotalsRow({ label, value, note }) {
  return (
    <Stack direction="row" sx={{ justifyContent: 'space-between', gap: 2 }}>
      <Stack>
        <Typography variant="body2">{label}</Typography>
        {note ? (
          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
            {note}
          </Typography>
        ) : null}
      </Stack>
      <Typography variant="subtitle2" component="p">
        {value}
      </Typography>
    </Stack>
  );
}

export function OrderTotals({ subtotal, shippingCost, total, compactShipping = false }) {
  const shipping = shippingCost === 0 ? 'Gratuita' : formatPrice(shippingCost);

  return (
    <Stack sx={{ gap: 2 }}>
      <Stack sx={{ gap: 1.5 }}>
        <TotalsRow label="Subtotale" value={formatPrice(subtotal)} />
        {compactShipping ? (
          <TotalsRow label="Spedizione standard" value={shipping} />
        ) : (
          <TotalsRow label="Spedizione" value={shipping} note="Standard, consegna in 24/48 ore" />
        )}
      </Stack>
      <Divider />
      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', gap: 2 }}>
        <Stack>
          <Typography variant="subtitle1" component="p">
            Totale
          </Typography>
          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
            IVA inclusa
          </Typography>
        </Stack>
        <Typography variant="priceLarge" component="p">
          {formatPrice(total)}
        </Typography>
      </Stack>
    </Stack>
  );
}
