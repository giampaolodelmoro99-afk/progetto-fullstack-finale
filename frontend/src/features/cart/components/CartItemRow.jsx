import DeleteOutlined from '@mui/icons-material/DeleteOutlined';
import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { AvailabilityChip } from '@/components/data-display/AvailabilityChip';
import { ProductThumbnail } from '@/components/data-display/ProductThumbnail';
import {
  UNAVAILABLE_ITEM_MESSAGE,
  exceedsStockMessage,
  unavailableItemTitle,
} from '@/features/cart/cartMessages';
import { QuantitySelect } from '@/features/cart/components/QuantitySelect';
import { useRemoveCartItem } from '@/features/cart/hooks/useRemoveCartItem';
import { useUpdateCartItem } from '@/features/cart/hooks/useUpdateCartItem';
import { availabilityColors } from '@/theme/statusColors';
import { formatPrice } from '@/utils/format';

export function CartItemRow({ item }) {
  const updateItem = useUpdateCartItem();
  const removeItem = useRemoveCartItem();
  const { product } = item;
  const busy = updateItem.isPending || removeItem.isPending;
  const unavailable = !item.is_purchasable;
  const remove = () => removeItem.mutate(item.id);

  return (
    <Box
      component="li"
      aria-label={product.name}
      sx={
        unavailable
          ? { border: 1, borderColor: 'error.main', borderRadius: 2, p: 2, my: 2 }
          : { py: 3 }
      }
    >
      <Stack direction="row" sx={{ gap: { xs: 2, md: 3 }, alignItems: 'flex-start' }}>
        <ProductThumbnail cover={product.cover} name={product.name} size="large" />
        <Stack
          direction={{ xs: 'column', md: 'row' }}
          sx={{ flexGrow: 1, gap: 2, justifyContent: 'space-between', minWidth: 0 }}
        >
          <Stack sx={{ gap: 0.5, minWidth: 0 }}>
            <Typography variant="overline" component="p" sx={{ color: 'text.secondary' }}>
              {product.category.name}
            </Typography>
            <Link
              component={RouterLink}
              to={paths.product(product.slug)}
              variant="subtitle1"
              underline="hover"
              color="inherit"
            >
              {product.name}
            </Link>
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
              {formatPrice(item.unit_price)} cad.
            </Typography>
            {item.exceeds_stock && !unavailable ? (
              <Typography
                variant="captionStrong"
                component="p"
                sx={{ color: availabilityColors[product.availability.status] }}
              >
                {product.availability.label}
              </Typography>
            ) : null}
            <Box>
              <Button
                size="small"
                color="inherit"
                startIcon={<DeleteOutlined />}
                disabled={busy}
                onClick={remove}
                aria-label={`Rimuovi ${product.name}`}
                sx={{ mt: 1, ml: -1 }}
              >
                Rimuovi
              </Button>
            </Box>
          </Stack>
          <Stack
            direction="row"
            sx={{ gap: 3, alignItems: 'flex-start', justifyContent: 'space-between' }}
          >
            {unavailable ? (
              <AvailabilityChip availability={product.availability} />
            ) : (
              <QuantitySelect
                productName={product.name}
                value={item.quantity}
                max={item.max_quantity}
                disabled={busy}
                onChange={(quantity) => updateItem.mutate({ itemId: item.id, quantity })}
              />
            )}
            <Typography
              variant={item.line_total === null ? 'subtitle1' : 'price'}
              component="p"
              sx={{
                minWidth: (theme) => theme.spacing(12),
                textAlign: 'right',
                color: item.line_total === null ? 'text.secondary' : 'text.primary',
              }}
            >
              {item.line_total === null ? '—' : formatPrice(item.line_total)}
            </Typography>
          </Stack>
        </Stack>
      </Stack>
      {unavailable ? (
        <Alert severity="error" sx={{ mt: 2 }}>
          <AlertTitle>{unavailableItemTitle(product.availability)}</AlertTitle>
          {UNAVAILABLE_ITEM_MESSAGE}
          <Box sx={{ mt: 1.5 }}>
            <Button
              variant="outlined"
              color="error"
              size="small"
              disabled={busy}
              loading={removeItem.isPending}
              onClick={remove}
            >
              Rimuovi dal carrello
            </Button>
          </Box>
        </Alert>
      ) : null}
      {item.exceeds_stock && !unavailable ? (
        <Alert severity="warning" sx={{ mt: 2 }}>
          {exceedsStockMessage(item.max_quantity)}
        </Alert>
      ) : null}
    </Box>
  );
}
