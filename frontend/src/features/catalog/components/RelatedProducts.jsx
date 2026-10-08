import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { ProductGrid } from '@/components/data-display/ProductGrid';

export function RelatedProducts({ products }) {
  if (products.length === 0) {
    return null;
  }

  return (
    <Stack component="section" aria-labelledby="related-products-title" sx={{ gap: 3 }}>
      <Typography id="related-products-title" variant="h4" component="h2">
        Prodotti correlati
      </Typography>
      <ProductGrid products={products} label="Prodotti correlati" />
    </Stack>
  );
}
