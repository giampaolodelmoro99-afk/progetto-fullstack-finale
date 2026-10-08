import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Grid from '@mui/material/Grid';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';

export function CheckoutSkeleton() {
  return (
    <Stack sx={{ gap: { xs: 2, md: 4 } }} role="status" aria-label="Caricamento del checkout">
      <Skeleton variant="text" sx={{ typography: 'subtitle1' }} />
      <Grid container spacing={{ xs: 2, md: 4 }}>
        <Grid size={{ xs: 12, md: 8 }}>
          <Card>
            <CardContent>
              <Stack sx={{ gap: 2 }}>
                <Skeleton variant="text" width="50%" sx={{ typography: 'h4' }} />
                <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(15) }} />
                <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(15) }} />
              </Stack>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, md: 4 }}>
          <Card>
            <CardContent>
              <Stack sx={{ gap: 2 }}>
                <Skeleton variant="text" width="60%" />
                <Skeleton variant="text" />
                <Skeleton variant="text" />
                <Skeleton variant="text" width="40%" />
              </Stack>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Stack>
  );
}
