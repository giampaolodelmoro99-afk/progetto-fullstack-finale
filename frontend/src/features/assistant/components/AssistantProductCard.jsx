import CheckCircleOutlined from '@mui/icons-material/CheckCircleOutlined';
import RemoveCircleOutlined from '@mui/icons-material/RemoveCircleOutlined';
import WarningAmberOutlined from '@mui/icons-material/WarningAmberOutlined';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink, useLocation, useNavigate } from 'react-router';
import { paths } from '@/app/router/paths';
import { ProductThumbnail } from '@/components/data-display/ProductThumbnail';
import { useAssistant } from '@/features/assistant/hooks/useAssistant';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useAddToCart } from '@/features/cart/hooks/useAddToCart';
import { availabilityColors } from '@/theme/statusColors';
import { formatPrice } from '@/utils/format';

const availabilityIcons = {
  available: CheckCircleOutlined,
  low_stock: WarningAmberOutlined,
  out_of_stock: RemoveCircleOutlined,
};

export function AssistantProductCard({ product }) {
  const { closeOnMobile } = useAssistant();
  const { isAuthenticated, isLoading } = useAuth();
  const addToCart = useAddToCart();
  const navigate = useNavigate();
  const location = useLocation();
  const { availability } = product;
  const AvailabilityIcon = availabilityIcons[availability.status] ?? CheckCircleOutlined;
  const outOfStock = availability.status === 'out_of_stock';

  function handleAddToCart() {
    if (!isAuthenticated) {
      closeOnMobile();
      navigate(paths.login, { state: { from: location } });

      return;
    }

    addToCart.mutate({ productId: product.id, quantity: 1 });
  }

  return (
    <Card component="article" aria-label={product.name} sx={{ display: 'flex', gap: 1.5, p: 1.5 }}>
      <ProductThumbnail cover={product.cover} name={product.name} />
      <Stack sx={{ flexGrow: 1, minWidth: 0, gap: 0.5 }}>
        <Typography variant="subtitle2" component="h4">
          {product.name}
        </Typography>
        <Stack direction="row" sx={{ alignItems: 'center', flexWrap: 'wrap', columnGap: 1.5 }}>
          <Typography variant="price" component="p">
            {formatPrice(product.price)}
          </Typography>
          <Stack
            direction="row"
            sx={{ alignItems: 'center', gap: 0.5, color: availabilityColors[availability.status] }}
          >
            <AvailabilityIcon fontSize="inherit" />
            <Typography variant="captionStrong" component="span">
              {availability.label}
            </Typography>
          </Stack>
        </Stack>
        <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 1, mt: 0.5 }}>
          <Button
            component={RouterLink}
            to={paths.product(product.slug)}
            variant="text"
            size="small"
            onClick={closeOnMobile}
          >
            Vedi prodotto
          </Button>
          <Button
            variant="outlined"
            size="small"
            disabled={outOfStock || isLoading}
            loading={addToCart.isPending}
            onClick={handleAddToCart}
          >
            Aggiungi al carrello
          </Button>
        </Stack>
      </Stack>
    </Card>
  );
}
