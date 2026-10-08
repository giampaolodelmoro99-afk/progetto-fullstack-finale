import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import Divider from '@mui/material/Divider';
import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { ErrorState } from '@/components/feedback/ErrorState';
import { MobileActionBar } from '@/components/layout/MobileActionBar';
import { CartItemRow } from '@/features/cart/components/CartItemRow';
import { CartSkeleton } from '@/features/cart/components/CartSkeleton';
import { CartSummary } from '@/features/cart/components/CartSummary';
import { EmptyCart } from '@/features/cart/components/EmptyCart';
import { useCart } from '@/features/cart/hooks/useCart';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { formatItemCount } from '@/utils/format';

function CartContent({ cart }) {
  const isDesktop = useIsDesktop();

  return (
    <>
      <Grid container spacing={{ xs: 3, md: 4 }} sx={{ alignItems: 'flex-start' }}>
        <Grid size={{ xs: 12, md: 8 }}>
          <Stack
            component="ul"
            aria-label="Prodotti nel carrello"
            divider={<Divider component="li" aria-hidden="true" />}
            sx={{
              listStyle: 'none',
              m: 0,
              p: 0,
              borderTop: 1,
              borderBottom: 1,
              borderColor: 'divider',
            }}
          >
            {cart.items.map((item) => (
              <CartItemRow key={item.id} item={item} />
            ))}
          </Stack>
        </Grid>
        <Grid
          size={{ xs: 12, md: 4 }}
          sx={(theme) => ({
            position: { md: 'sticky' },
            top: { md: theme.layout.stickySummaryTop },
          })}
        >
          <CartSummary cart={cart} />
        </Grid>
      </Grid>
      {isDesktop ? null : (
        <MobileActionBar total={cart.summary.total}>
          <Button
            component={RouterLink}
            to={paths.checkout}
            variant="contained"
            disabled={!cart.can_checkout}
          >
            Procedi al checkout
          </Button>
        </MobileActionBar>
      )}
    </>
  );
}

export default function CartPage() {
  const cartQuery = useCart();
  const cart = cartQuery.data;
  const isEmpty = cart?.items.length === 0;

  return (
    <Container maxWidth="lg" sx={{ py: { xs: 3, md: 5 } }}>
      <Stack sx={{ gap: { xs: 3, md: 4 } }}>
        <Stack direction="row" sx={{ alignItems: 'baseline', gap: 2, flexWrap: 'wrap' }}>
          <Typography variant="h2" component="h1">
            Il tuo carrello
          </Typography>
          {cart && !isEmpty ? (
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
              {formatItemCount(cart.summary.items_count)}
            </Typography>
          ) : null}
        </Stack>
        {cartQuery.isPending ? <CartSkeleton /> : null}
        {cartQuery.isError && !cart ? (
          <ErrorState
            title="Non è stato possibile caricare il carrello"
            message="Controlla la connessione e riprova."
            onRetry={() => cartQuery.refetch()}
          />
        ) : null}
        {cart && isEmpty ? <EmptyCart /> : null}
        {cart && !isEmpty ? <CartContent cart={cart} /> : null}
      </Stack>
    </Container>
  );
}
