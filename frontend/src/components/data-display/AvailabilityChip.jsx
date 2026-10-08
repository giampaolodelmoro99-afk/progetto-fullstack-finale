import CheckCircleOutlined from '@mui/icons-material/CheckCircleOutlined';
import RemoveCircleOutlined from '@mui/icons-material/RemoveCircleOutlined';
import WarningAmberOutlined from '@mui/icons-material/WarningAmberOutlined';
import Chip from '@mui/material/Chip';
import { availabilityChipColors } from '@/theme/statusColors';

const icons = {
  available: CheckCircleOutlined,
  low_stock: WarningAmberOutlined,
  out_of_stock: RemoveCircleOutlined,
};

export function AvailabilityChip({ availability, size = 'small' }) {
  const Icon = icons[availability.status];
  const colors = availabilityChipColors[availability.status];

  return (
    <Chip
      size={size}
      icon={<Icon fontSize="small" />}
      label={availability.label}
      sx={{
        alignSelf: 'flex-start',
        color: colors.color,
        bgcolor: colors.bgcolor,
        '& .MuiChip-icon': { color: colors.color },
      }}
    />
  );
}
