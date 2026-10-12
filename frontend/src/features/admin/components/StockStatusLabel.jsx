import Typography from '@mui/material/Typography';
import { stockStatusColors } from '@/theme/statusColors';

export function StockStatusLabel({ stockStatus }) {
  return (
    <Typography variant="caption" sx={{ color: stockStatusColors[stockStatus.status] }}>
      {stockStatus.label}
    </Typography>
  );
}
