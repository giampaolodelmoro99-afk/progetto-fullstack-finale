import Box from '@mui/material/Box';
import Link from '@mui/material/Link';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';

export function Logo({
  to = paths.home,
  label = 'Casa del Silicio, vai alla home',
  color = 'text.primary',
  onClick,
}) {
  return (
    <Link
      component={RouterLink}
      to={to}
      underline="none"
      onClick={onClick}
      aria-label={label}
      sx={{ color, whiteSpace: 'nowrap' }}
    >
      <Typography variant="logo" aria-hidden="true">
        Casa <Box component="em">del</Box> Silicio
      </Typography>
    </Link>
  );
}
