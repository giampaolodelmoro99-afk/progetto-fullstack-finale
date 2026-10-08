import LinearProgress from '@mui/material/LinearProgress';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useId } from 'react';
import { formatPrice } from '@/utils/format';

export function FreeShippingProgress({ remaining, threshold }) {
  const labelId = useId();

  if (remaining === null || remaining <= 0) {
    return null;
  }

  const value = Math.round(Math.min(100, Math.max(0, ((threshold - remaining) / threshold) * 100)));

  return (
    <Stack sx={{ gap: 1 }}>
      <Typography id={labelId} variant="body2">
        Ti mancano <strong>{formatPrice(remaining)}</strong> per la spedizione gratuita
      </Typography>
      <LinearProgress
        variant="determinate"
        value={value}
        aria-labelledby={labelId}
        color="secondary"
      />
    </Stack>
  );
}
