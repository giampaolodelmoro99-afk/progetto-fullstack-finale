import Grid from '@mui/material/Grid';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';

export function ProductPageSkeleton() {
  return (
    <Grid
      container
      spacing={{ xs: 4, md: 6 }}
      role="status"
      aria-busy="true"
      aria-label="Caricamento del prodotto"
    >
      <Grid size={{ xs: 12, md: 7 }}>
        <Skeleton
          variant="rectangular"
          sx={{ width: 1, height: 'auto', aspectRatio: '4 / 5', borderRadius: 2 }}
        />
      </Grid>
      <Grid size={{ xs: 12, md: 5 }}>
        <Stack sx={{ gap: 1.5 }}>
          <Skeleton variant="text" sx={{ width: 0.2 }} />
          <Skeleton variant="text" sx={{ width: 0.7, typography: 'h3' }} />
          <Skeleton variant="text" sx={{ width: 0.9 }} />
          <Skeleton variant="text" sx={{ width: 0.3, typography: 'priceLarge' }} />
          <Skeleton variant="rounded" sx={{ width: 1, height: (theme) => theme.spacing(6.5) }} />
        </Stack>
      </Grid>
    </Grid>
  );
}
