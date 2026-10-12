import Inventory2Outlined from '@mui/icons-material/Inventory2Outlined';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Grid from '@mui/material/Grid';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink, useParams } from 'react-router';
import { paths } from '@/app/router/paths';
import { EmptyState } from '@/components/feedback/EmptyState';
import { ErrorState } from '@/components/feedback/ErrorState';
import { BackLink } from '@/features/admin/components/BackLink';
import { ProductForm } from '@/features/admin/products/components/ProductForm';
import { useAdminProduct } from '@/features/admin/products/hooks/useAdminProduct';
import { formatDate, formatTime } from '@/utils/format';

function ProductFormSkeleton() {
  return (
    <Grid container spacing={3} role="status" aria-label="Caricamento del prodotto">
      <Grid size={{ xs: 12, md: 8 }}>
        <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(60) }} />
      </Grid>
      <Grid size={{ xs: 12, md: 4 }}>
        <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(40) }} />
      </Grid>
    </Grid>
  );
}

export default function ProductEditPage() {
  const { productId } = useParams();
  const productQuery = useAdminProduct(productId);
  const notFound = productQuery.isError && productQuery.error.response?.status === 404;
  const product = productQuery.data;

  return (
    <Stack sx={{ gap: 3 }}>
      <Stack
        direction="row"
        sx={{ justifyContent: 'space-between', alignItems: 'center', gap: 2, flexWrap: 'wrap' }}
      >
        <BackLink to={paths.adminProducts}>Tutti i prodotti</BackLink>
        {product?.updated_at ? (
          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
            Ultima modifica il {formatDate(product.updated_at)} alle{' '}
            {formatTime(product.updated_at)}
          </Typography>
        ) : null}
      </Stack>
      {productQuery.isPending ? <ProductFormSkeleton /> : null}
      {notFound ? (
        <Card>
          <EmptyState
            icon={<Inventory2Outlined />}
            title="Non abbiamo trovato questo prodotto"
            description="Potrebbe essere stato eliminato."
            action={
              <Button component={RouterLink} to={paths.adminProducts} variant="contained">
                Tutti i prodotti
              </Button>
            }
          />
        </Card>
      ) : null}
      {productQuery.isError && !notFound ? (
        <ErrorState
          title="Non è stato possibile caricare il prodotto"
          message="Controlla la connessione e riprova."
          onRetry={() => productQuery.refetch()}
        />
      ) : null}
      {product ? <ProductForm key={product.id} product={product} /> : null}
    </Stack>
  );
}
