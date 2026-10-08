import Grid from '@mui/material/Grid';
import { ProductCard } from '@/components/data-display/ProductCard';
import { ProductCardSkeleton } from '@/components/data-display/ProductCardSkeleton';

const DEFAULT_COLUMNS = { xs: 6, md: 3 };

export function ProductGrid({
  products,
  loading = false,
  skeletonCount = 4,
  size = DEFAULT_COLUMNS,
  headingComponent,
  label,
  busy = false,
}) {
  if (loading) {
    return (
      <Grid container spacing={{ xs: 2, md: 3 }} role="status" aria-busy="true" aria-label={label}>
        {Array.from({ length: skeletonCount }, (_, index) => (
          <Grid key={index} size={size}>
            <ProductCardSkeleton />
          </Grid>
        ))}
      </Grid>
    );
  }

  return (
    <Grid
      component="ul"
      container
      spacing={{ xs: 2, md: 3 }}
      aria-label={label}
      aria-busy={busy}
      sx={{
        listStyle: 'none',
        m: 0,
        p: 0,
        opacity: busy ? 0.6 : 1,
        transition: (theme) => theme.transitions.create('opacity'),
      }}
    >
      {products.map((product) => (
        <Grid component="li" key={product.id} size={size}>
          <ProductCard product={product} headingComponent={headingComponent} />
        </Grid>
      ))}
    </Grid>
  );
}
