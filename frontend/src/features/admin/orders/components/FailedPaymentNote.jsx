import ErrorOutline from '@mui/icons-material/ErrorOutlineOutlined';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

export function FailedPaymentNote() {
  return (
    <Stack
      component="span"
      direction="row"
      sx={{ display: 'inline-flex', alignItems: 'center', gap: 0.5, color: 'error.main' }}
    >
      <ErrorOutline fontSize="inherit" aria-hidden="true" />
      <Typography variant="caption" sx={{ color: 'inherit' }}>
        Pagamento non riuscito
      </Typography>
    </Stack>
  );
}
