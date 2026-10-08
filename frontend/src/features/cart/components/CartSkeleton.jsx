import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Divider from '@mui/material/Divider';
import Grid from '@mui/material/Grid';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';

const ROWS = [0, 1, 2];

export function CartSkeleton() {
  return (
    <Grid container spacing={{ xs: 3, md: 4 }} role="status" aria-label="Caricamento del carrello">
      <Grid size={{ xs: 12, md: 8 }}>
        <Stack divider={<Divider />}>
          {ROWS.map((row) => (
            <Stack key={row} direction="row" sx={{ gap: 3, py: 3 }}>
              <Skeleton
                variant="rounded"
                sx={{ width: (theme) => theme.spacing(13), height: (theme) => theme.spacing(13) }}
              />
              <Stack sx={{ flexGrow: 1, gap: 1 }}>
                <Skeleton variant="text" width="30%" />
                <Skeleton variant="text" width="60%" />
                <Skeleton variant="text" width="25%" />
              </Stack>
            </Stack>
          ))}
        </Stack>
      </Grid>
      <Grid size={{ xs: 12, md: 4 }}>
        <Card>
          <CardContent>
            <Stack sx={{ gap: 2 }}>
              <Skeleton variant="text" width="40%" />
              <Skeleton variant="text" />
              <Skeleton variant="text" />
              <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(6) }} />
            </Stack>
          </CardContent>
        </Card>
      </Grid>
    </Grid>
  );
}
