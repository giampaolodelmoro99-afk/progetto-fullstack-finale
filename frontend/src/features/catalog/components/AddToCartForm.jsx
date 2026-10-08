import ShoppingBagOutlined from '@mui/icons-material/ShoppingBagOutlined';
import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router';
import { paths } from '@/app/router/paths';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useAddToCart } from '@/features/cart/hooks/useAddToCart';

export function AddToCartForm({ product, hasRelated }) {
  const { isAuthenticated, isAdmin, isLoading } = useAuth();
  const addToCart = useAddToCart();
  const navigate = useNavigate();
  const location = useLocation();
  const [quantity, setQuantity] = useState(1);
  const outOfStock = product.availability.status === 'out_of_stock' || product.max_quantity < 1;
  const quantities = Array.from(
    { length: Math.max(product.max_quantity, 1) },
    (_, index) => index + 1,
  );

  if (isAdmin) {
    return <Alert severity="info">Gli account di amministrazione non possono fare acquisti.</Alert>;
  }

  function handleSubmit(event) {
    event.preventDefault();

    if (!isAuthenticated) {
      navigate(paths.login, { state: { from: location } });

      return;
    }

    addToCart.mutate({ productId: product.id, quantity });
  }

  return (
    <Stack component="form" onSubmit={handleSubmit} sx={{ gap: 2 }}>
      <Stack direction="row" sx={{ gap: 1.5 }}>
        <TextField
          select
          label="Quantità"
          value={quantity}
          disabled={outOfStock}
          onChange={(event) => setQuantity(Number(event.target.value))}
          sx={{ width: (theme) => theme.spacing(12), flexShrink: 0 }}
        >
          {quantities.map((value) => (
            <MenuItem key={value} value={value}>
              {value}
            </MenuItem>
          ))}
        </TextField>
        <Button
          type="submit"
          variant="contained"
          size="large"
          startIcon={<ShoppingBagOutlined />}
          disabled={outOfStock || isLoading}
          loading={addToCart.isPending}
          sx={{ flexGrow: 1, height: 'auto' }}
        >
          Aggiungi al carrello
        </Button>
      </Stack>
      {outOfStock ? (
        <Alert severity="info">
          <AlertTitle>Prodotto esaurito</AlertTitle>
          Al momento questo prodotto non è disponibile.
          {hasRelated ? ' Più in basso trovi alcuni prodotti simili.' : null}
        </Alert>
      ) : null}
    </Stack>
  );
}
