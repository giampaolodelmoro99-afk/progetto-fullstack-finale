import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { formatPrice } from '@/utils/format';

export function MobileActionBar({ total, children }) {
  return (
    <Paper
      component="section"
      aria-label="Totale e prossimo passo"
      square
      elevation={3}
      data-mobile-action-bar=""
      sx={{
        position: 'fixed',
        left: 0,
        right: 0,
        bottom: 0,
        zIndex: 'appBar',
        minHeight: (theme) => theme.layout.mobileActionBarHeight,
        display: 'flex',
        alignItems: 'center',
        gap: 2,
        px: 2,
        pt: 1.5,
        pb: (theme) => `calc(${theme.spacing(1.5)} + env(safe-area-inset-bottom))`,
        borderTop: 1,
        borderColor: 'divider',
      }}
    >
      <Stack sx={{ flexShrink: 0 }}>
        <Typography variant="caption" sx={{ color: 'text.secondary' }}>
          Totale, IVA inclusa
        </Typography>
        <Typography variant="price" component="p">
          {formatPrice(total)}
        </Typography>
      </Stack>
      <Stack sx={{ flexGrow: 1, alignItems: 'stretch' }}>{children}</Stack>
    </Paper>
  );
}
