import Chip from '@mui/material/Chip';
import { orderStatusColors } from '@/theme/statusColors';

export function OrderStatusChip({ status }) {
  return (
    <Chip
      size="small"
      variant="soft"
      color={orderStatusColors[status.value]}
      label={status.label}
    />
  );
}
