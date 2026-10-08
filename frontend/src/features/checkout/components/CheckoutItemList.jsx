import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { ProductThumbnail } from '@/components/data-display/ProductThumbnail';
import { availabilityColors } from '@/theme/statusColors';
import { formatPrice } from '@/utils/format';

function hasIssue(item) {
  return item.exceeds_stock || !item.is_purchasable;
}

export function CheckoutItemList({ items, detailed = false, label }) {
  return (
    <Stack component="ul" aria-label={label} sx={{ listStyle: 'none', m: 0, p: 0, gap: 2 }}>
      {items.map((item) => (
        <Stack key={item.id} component="li" direction="row" sx={{ gap: 2, alignItems: 'center' }}>
          <ProductThumbnail cover={item.product.cover} name={item.product.name} />
          <Stack sx={{ flexGrow: 1, minWidth: 0 }}>
            <Typography variant="subtitle2" component="p">
              {item.product.name}
            </Typography>
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              {detailed
                ? `Quantità ${item.quantity} · ${formatPrice(item.unit_price)} cad.`
                : `Quantità ${item.quantity}`}
            </Typography>
            {hasIssue(item) ? (
              <Typography
                variant="captionStrong"
                component="p"
                sx={{ color: availabilityColors[item.product.availability.status] }}
              >
                {item.product.availability.label}
              </Typography>
            ) : null}
          </Stack>
          <Typography variant="subtitle2" component="p" sx={{ flexShrink: 0 }}>
            {item.line_total === null ? '—' : formatPrice(item.line_total)}
          </Typography>
        </Stack>
      ))}
    </Stack>
  );
}
