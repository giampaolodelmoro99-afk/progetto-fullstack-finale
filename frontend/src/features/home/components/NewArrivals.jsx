import ArrowForward from '@mui/icons-material/ArrowForward';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import Stack from '@mui/material/Stack';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { ErrorState } from '@/components/feedback/ErrorState';
import { ProductGrid } from '@/components/data-display/ProductGrid';
import { useProducts } from '@/features/catalog/hooks/useProducts';
import { SectionHeading } from '@/features/home/components/SectionHeading';
import { useIsDesktop } from '@/hooks/useIsDesktop';

const NEW_ARRIVALS_PARAMS = { new: 1, sort: 'newest', per_page: 4 };

function SeeAllLink({ fullWidth = false }) {
  return (
    <Button
      component={RouterLink}
      to={paths.newArrivals}
      variant={fullWidth ? 'outlined' : 'text'}
      fullWidth={fullWidth}
      endIcon={<ArrowForward />}
    >
      Vedi tutte le novità
    </Button>
  );
}

export function NewArrivals() {
  const productsQuery = useProducts(NEW_ARRIVALS_PARAMS);
  const isDesktop = useIsDesktop();

  if (productsQuery.isSuccess && productsQuery.data.data.length === 0) {
    return null;
  }

  return (
    <Container
      component="section"
      maxWidth="lg"
      aria-labelledby="new-arrivals-title"
      sx={{ pb: { xs: 5, md: 8 } }}
    >
      <Stack sx={{ gap: { xs: 3, md: 4 } }}>
        <Stack
          direction="row"
          sx={{ justifyContent: 'space-between', alignItems: 'flex-end', gap: 2 }}
        >
          <SectionHeading
            id="new-arrivals-title"
            title="Novità"
            description="Gli ultimi arrivi della linea Casa del Silicio."
          />
          {isDesktop ? <SeeAllLink /> : null}
        </Stack>
        {productsQuery.isError ? (
          <ErrorState
            message="Non è stato possibile caricare le novità."
            onRetry={() => productsQuery.refetch()}
          />
        ) : (
          <ProductGrid
            products={productsQuery.data?.data ?? []}
            loading={productsQuery.isPending}
            label="Novità"
          />
        )}
        {isDesktop ? null : <SeeAllLink fullWidth />}
      </Stack>
    </Container>
  );
}
