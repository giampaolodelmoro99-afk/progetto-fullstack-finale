import Link from '@mui/material/Link';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';

export function EditCartLink() {
  return (
    <Link component={RouterLink} to={paths.cart} variant="body2" sx={{ flexShrink: 0 }}>
      Modifica carrello
    </Link>
  );
}
