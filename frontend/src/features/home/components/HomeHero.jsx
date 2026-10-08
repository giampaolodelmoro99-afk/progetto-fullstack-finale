import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import Grid from '@mui/material/Grid';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import fallbackImage from '@/assets/fallback.webp';
import { useProduct } from '@/features/catalog/hooks/useProduct';
import { heroContent } from '@/features/home/heroContent';
import { formatPrice } from '@/utils/format';

function HeroImage({ productQuery }) {
  if (productQuery.isPending) {
    return (
      <Skeleton
        variant="rectangular"
        role="progressbar"
        aria-label="Caricamento dell'immagine"
        sx={{ width: 1, height: 'auto', aspectRatio: '4 / 5', borderRadius: 2 }}
      />
    );
  }

  if (productQuery.isError) {
    return null;
  }

  const product = productQuery.data;
  const image = product.images[0] ?? product.cover;

  return (
    <Box
      component="img"
      src={image?.url ?? fallbackImage}
      alt={image?.alt ?? product.name}
      sx={{ width: 1, aspectRatio: '4 / 5', objectFit: 'contain', display: 'block' }}
    />
  );
}

export function HomeHero() {
  const productQuery = useProduct(heroContent.productSlug);
  const product = productQuery.data;

  return (
    <Box component="section" aria-labelledby="home-hero-title" sx={{ bgcolor: 'background.media' }}>
      <Container maxWidth="lg" sx={{ py: { xs: 5, md: 8 } }}>
        <Grid container spacing={{ xs: 4, md: 6 }} sx={{ alignItems: 'center' }}>
          <Grid size={{ xs: 12, md: 6 }}>
            <Stack sx={{ gap: 2, alignItems: 'flex-start' }}>
              <Typography variant="overline" component="p" sx={{ color: 'text.secondary' }}>
                {heroContent.overline}
              </Typography>
              <Typography id="home-hero-title" variant="h1" component="h1">
                {heroContent.title}
              </Typography>
              <Typography
                variant="body1"
                sx={{ maxWidth: (theme) => theme.layout.heroTextMaxWidth }}
              >
                {heroContent.description}
              </Typography>
              {productQuery.isPending ? (
                <Skeleton
                  variant="text"
                  role="progressbar"
                  aria-label="Caricamento del prezzo"
                  sx={{ width: '8ch', typography: 'priceLarge' }}
                />
              ) : null}
              {productQuery.isSuccess ? (
                <Typography variant="priceLarge" component="p">
                  {formatPrice(product.price)}
                </Typography>
              ) : null}
              {productQuery.isError ? (
                <Button
                  variant="contained"
                  size="large"
                  component={RouterLink}
                  to={paths.products}
                  sx={{ mt: 1 }}
                >
                  Vedi tutti i prodotti
                </Button>
              ) : (
                <Button
                  variant="contained"
                  size="large"
                  component={RouterLink}
                  to={paths.product(heroContent.productSlug)}
                  sx={{ mt: 1 }}
                >
                  {heroContent.buttonLabel}
                </Button>
              )}
            </Stack>
          </Grid>
          <Grid size={{ xs: 12, md: 6 }}>
            <Box sx={{ maxWidth: { xs: 1, md: 0.85 }, mx: 'auto' }}>
              <HeroImage productQuery={productQuery} />
            </Box>
          </Grid>
        </Grid>
      </Container>
    </Box>
  );
}
