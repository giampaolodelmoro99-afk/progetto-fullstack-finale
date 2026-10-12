import Link from '@mui/material/Link';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';

export function OrderNumberLink({ order }) {
  return (
    <Link
      component={RouterLink}
      to={paths.adminOrder(order.id)}
      underline="always"
      color="inherit"
      variant="subtitle2"
    >
      {order.number}
    </Link>
  );
}
