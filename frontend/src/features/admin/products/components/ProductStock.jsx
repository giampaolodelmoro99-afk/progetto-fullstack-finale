import ErrorOutline from '@mui/icons-material/ErrorOutlineOutlined';
import WarningAmberOutlined from '@mui/icons-material/WarningAmberOutlined';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { stockStatusColors } from '@/theme/statusColors';

export function ProductStock({ product }) {
  const { status } = product.stock_status;

  if (status === 'available') {
    return (
      <Typography variant="body2" component="span">
        {product.stock}
      </Typography>
    );
  }

  const Icon = status === 'out_of_stock' ? ErrorOutline : WarningAmberOutlined;

  return (
    <Stack
      component="span"
      direction="row"
      sx={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: 0.5,
        color: stockStatusColors[status],
      }}
    >
      <Icon fontSize="small" aria-hidden="true" />
      <Typography variant="subtitle2" component="span" sx={{ color: 'inherit' }}>
        {status === 'out_of_stock' ? product.stock_status.label : product.stock}
      </Typography>
      <Typography component="span" sx={(theme) => theme.mixins.visuallyHidden}>
        , scorte basse
      </Typography>
    </Stack>
  );
}
