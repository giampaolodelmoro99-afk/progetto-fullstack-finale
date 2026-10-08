import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';

export function PageLoader() {
  return (
    <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', py: 10 }}>
      <CircularProgress aria-label="Caricamento in corso" />
    </Box>
  );
}
