import ShoppingBagOutlined from '@mui/icons-material/ShoppingBagOutlined';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { ProductGrid } from '@/components/data-display/ProductGrid';
import { EmptyState } from '@/components/feedback/EmptyState';
import { useProducts } from '@/features/catalog/hooks/useProducts';

const SUGGESTIONS = { in_stock: 1, sort: 'newest', per_page: 4 };

function Suggestions() {
  const productsQuery = useProducts(SUGGESTIONS);
  const products = productsQuery.data?.data ?? [];

  if (productsQuery.isError || (productsQuery.isSuccess && products.length === 0)) {
    return null;
  }

  return (
    <Stack component="section" aria-labelledby="cart-suggestions-title" sx={{ gap: 3 }}>
      <Typography id="cart-suggestions-title" variant="h4" component="h2">
        Potrebbero piacerti
      </Typography>
      <ProductGrid
        products={products}
        loading={productsQuery.isPending}
        label="Potrebbero piacerti"
      />
    </Stack>
  );
}

export function EmptyCart() {
  return (
    <Stack sx={{ gap: { xs: 5, md: 8 } }}>
      <Card>
        <EmptyState
          icon={<ShoppingBagOutlined />}
          title="Il tuo carrello è vuoto"
          description="Quando aggiungi un prodotto, lo ritrovi qui."
          action={
            <Button component={RouterLink} to={paths.products} variant="contained">
              Scopri il negozio
            </Button>
          }
        />
      </Card>
      <Suggestions />
    </Stack>
  );
}
