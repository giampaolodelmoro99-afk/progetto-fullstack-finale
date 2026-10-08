import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { stockIssueText } from '@/features/checkout/checkoutErrors';

function BackToCart() {
  return (
    <Box sx={{ mt: 1.5 }}>
      <Button
        component={RouterLink}
        to={paths.cart}
        variant="outlined"
        color="warning"
        size="small"
      >
        Torna al carrello
      </Button>
    </Box>
  );
}

export function CheckoutAlert({ error, availabilityChanged, onEditCard }) {
  if (error?.kind === 'payment_failed') {
    return (
      <Alert severity="error">
        <AlertTitle>Pagamento non riuscito</AlertTitle>
        {error.message}
        <Box sx={{ mt: 1.5 }}>
          <Button variant="outlined" color="error" size="small" onClick={onEditCard}>
            Modifica la carta
          </Button>
        </Box>
      </Alert>
    );
  }

  if (error?.kind === 'insufficient_stock') {
    return (
      <Alert severity="warning">
        <AlertTitle>Disponibilità cambiata</AlertTitle>
        {error.message}
        {error.products.length > 0 ? (
          <Box component="ul" sx={{ m: 0, mt: 1, pl: 2.5 }}>
            {error.products.map((product) => (
              <li key={product.product}>{stockIssueText(product)}</li>
            ))}
          </Box>
        ) : null}
        <BackToCart />
      </Alert>
    );
  }

  if (error?.kind === 'cart_empty') {
    return (
      <Alert severity="warning">
        {error.message}
        <BackToCart />
      </Alert>
    );
  }

  if (error?.kind === 'generic') {
    return <Alert severity="error">{error.message}</Alert>;
  }

  if (availabilityChanged) {
    return (
      <Alert severity="warning">
        <AlertTitle>Disponibilità cambiata</AlertTitle>
        Aggiorna il carrello per completare l'ordine.
        <BackToCart />
      </Alert>
    );
  }

  return null;
}
