import Typography from '@mui/material/Typography';
import { useShopSettings } from '@/features/settings/hooks/useShopSettings';

export function ProductPriceNote({ price }) {
  const settingsQuery = useShopSettings();
  const freeShipping =
    settingsQuery.isSuccess && price >= settingsQuery.data.free_shipping_threshold;

  return (
    <Typography variant="caption" component="p" sx={{ color: 'text.secondary' }}>
      {freeShipping ? 'IVA inclusa · Spedizione gratuita' : 'IVA inclusa'}
    </Typography>
  );
}
