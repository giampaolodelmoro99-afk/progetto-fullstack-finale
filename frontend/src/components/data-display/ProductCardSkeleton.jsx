import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Skeleton from '@mui/material/Skeleton';

export function ProductCardSkeleton() {
  return (
    <Card aria-hidden="true" sx={{ height: 1 }}>
      <Skeleton variant="rectangular" sx={{ width: 1, height: 'auto', aspectRatio: '4 / 5' }} />
      <CardContent>
        <Skeleton variant="text" sx={{ width: 0.4 }} />
        <Skeleton variant="text" sx={{ width: 0.8 }} />
        <Skeleton variant="text" sx={{ width: 0.3, typography: 'price' }} />
      </CardContent>
    </Card>
  );
}
