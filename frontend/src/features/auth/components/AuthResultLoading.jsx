import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';

export function AuthResultLoading() {
  return (
    <Stack sx={{ gap: 2 }} role="status" aria-busy="true" aria-label="Caricamento in corso">
      <Skeleton variant="text" sx={{ typography: 'h3' }} />
      <Skeleton variant="text" />
      <Skeleton variant="text" />
      <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(5.5) }} />
    </Stack>
  );
}
