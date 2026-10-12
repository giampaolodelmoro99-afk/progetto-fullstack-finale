import Stack from '@mui/material/Stack';
import { paths } from '@/app/router/paths';
import { BackLink } from '@/features/admin/components/BackLink';
import { ProductForm } from '@/features/admin/products/components/ProductForm';

export default function ProductNewPage() {
  return (
    <Stack sx={{ gap: 3 }}>
      <Stack direction="row">
        <BackLink to={paths.adminProducts}>Tutti i prodotti</BackLink>
      </Stack>
      <ProductForm product={null} />
    </Stack>
  );
}
