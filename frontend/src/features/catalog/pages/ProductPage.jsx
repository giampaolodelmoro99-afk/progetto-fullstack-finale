import Container from '@mui/material/Container';
import Divider from '@mui/material/Divider';
import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useParams } from 'react-router';
import { paths } from '@/app/router/paths';
import { useDocumentTitle } from '@/app/router/useDocumentTitle';
import { AvailabilityChip } from '@/components/data-display/AvailabilityChip';
import { ErrorState } from '@/components/feedback/ErrorState';
import { NotFoundContent } from '@/components/feedback/NotFoundContent';
import { AddToCartForm } from '@/features/catalog/components/AddToCartForm';
import { CatalogBreadcrumbs } from '@/features/catalog/components/CatalogBreadcrumbs';
import { ProductDetails } from '@/features/catalog/components/ProductDetails';
import { ProductGallery } from '@/features/catalog/components/ProductGallery';
import { ProductPageSkeleton } from '@/features/catalog/components/ProductPageSkeleton';
import { ProductPriceNote } from '@/features/catalog/components/ProductPriceNote';
import { RelatedProducts } from '@/features/catalog/components/RelatedProducts';
import { useProduct } from '@/features/catalog/hooks/useProduct';
import { useRelatedProducts } from '@/features/catalog/hooks/useRelatedProducts';
import { Reassurances } from '@/features/settings/components/Reassurances';
import { formatPrice } from '@/utils/format';

function ProductContent({ product, related }) {
  return (
    <Stack sx={{ gap: { xs: 5, md: 8 } }}>
      <Grid container spacing={{ xs: 4, md: 6 }}>
        <Grid size={{ xs: 12, md: 7 }}>
          <ProductGallery key={product.slug} product={product} />
        </Grid>
        <Grid size={{ xs: 12, md: 5 }}>
          <Stack sx={{ gap: 2 }}>
            <Stack sx={{ gap: 1 }}>
              <Typography variant="overline" component="p" sx={{ color: 'text.secondary' }}>
                {product.category.name}
              </Typography>
              <Typography variant="h3" component="h1">
                {product.name}
              </Typography>
              <Typography variant="body1" sx={{ color: 'text.secondary' }}>
                {product.short_description}
              </Typography>
            </Stack>
            <Stack sx={{ gap: 0.5 }}>
              <Typography variant="priceLarge" component="p">
                {formatPrice(product.price)}
              </Typography>
              <ProductPriceNote price={product.price} />
            </Stack>
            <AvailabilityChip availability={product.availability} />
            <AddToCartForm key={product.slug} product={product} hasRelated={related.length > 0} />
            <Divider />
            <Reassurances variant="compact" />
          </Stack>
        </Grid>
      </Grid>
      <ProductDetails product={product} />
      <RelatedProducts products={related} />
    </Stack>
  );
}

export default function ProductPage() {
  const { slug } = useParams();
  const productQuery = useProduct(slug);
  const relatedQuery = useRelatedProducts(slug);
  const product = productQuery.data;

  const notFound = productQuery.isError && productQuery.error.response?.status === 404;

  useDocumentTitle(notFound ? 'Pagina non trovata' : (product?.name ?? null));

  if (notFound) {
    return <NotFoundContent />;
  }

  return (
    <Container maxWidth="lg" sx={{ py: { xs: 3, md: 4 } }}>
      <Stack sx={{ gap: { xs: 2, md: 3 } }}>
        <CatalogBreadcrumbs
          items={
            product
              ? [
                  { label: product.category.name, to: paths.category(product.category.slug) },
                  { label: product.name },
                ]
              : [{ label: null }]
          }
        />
        {productQuery.isPending ? <ProductPageSkeleton /> : null}
        {productQuery.isError ? (
          <ErrorState
            title="Non è stato possibile caricare il prodotto"
            message="Controlla la connessione e riprova."
            onRetry={() => productQuery.refetch()}
          />
        ) : null}
        {productQuery.isSuccess ? (
          <ProductContent product={product} related={relatedQuery.data ?? []} />
        ) : null}
      </Stack>
    </Container>
  );
}
