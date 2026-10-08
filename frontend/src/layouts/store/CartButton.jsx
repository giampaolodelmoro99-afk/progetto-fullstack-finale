import ShoppingBagOutlined from '@mui/icons-material/ShoppingBagOutlined';
import Badge from '@mui/material/Badge';
import IconButton from '@mui/material/IconButton';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useCart } from '@/features/cart/hooks/useCart';
import { formatItemCount } from '@/utils/format';

function cartLabel(count) {
  if (count === 0) {
    return 'Carrello';
  }

  return `Carrello, ${formatItemCount(count)}`;
}

export function CartButton() {
  const { isAuthenticated, isAdmin, isLoading } = useAuth();
  const isCustomer = isAuthenticated && !isAdmin;
  const cartQuery = useCart({ enabled: isCustomer });

  if (isLoading || isAdmin) {
    return null;
  }

  const count = isCustomer ? (cartQuery.data?.summary.items_count ?? 0) : 0;

  return (
    <IconButton component={RouterLink} to={paths.cart} aria-label={cartLabel(count)}>
      <Badge badgeContent={count} color="secondary" invisible={count === 0}>
        <ShoppingBagOutlined />
      </Badge>
    </IconButton>
  );
}
