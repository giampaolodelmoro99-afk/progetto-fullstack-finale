import Skeleton from '@mui/material/Skeleton';

export function InlineAmountSkeleton() {
  return (
    <Skeleton
      variant="text"
      role="progressbar"
      aria-label="Caricamento dell'importo"
      sx={{ display: 'inline-block', width: '7ch', verticalAlign: 'middle' }}
    />
  );
}
