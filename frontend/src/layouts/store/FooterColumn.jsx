import Link from '@mui/material/Link';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';

const SKELETON_ITEMS = 5;

export function FooterColumn({ title, links, loading = false }) {
  return (
    <Stack component="nav" aria-label={title} sx={{ gap: 2 }}>
      <Typography variant="overline" component="h2" sx={{ color: 'text.inverseAccent' }}>
        {title}
      </Typography>
      <Stack component="ul" sx={{ listStyle: 'none', m: 0, p: 0, gap: 1.5 }}>
        {loading
          ? Array.from({ length: SKELETON_ITEMS }, (_, index) => (
              <li key={index} aria-hidden="true">
                <Skeleton variant="text" sx={{ bgcolor: 'inverseDivider' }} />
              </li>
            ))
          : links.map((link) => (
              <li key={link.to}>
                <Link
                  component={RouterLink}
                  to={link.to}
                  variant="body2"
                  underline="hover"
                  sx={{ color: 'text.inverse' }}
                >
                  {link.label}
                </Link>
              </li>
            ))}
      </Stack>
    </Stack>
  );
}
