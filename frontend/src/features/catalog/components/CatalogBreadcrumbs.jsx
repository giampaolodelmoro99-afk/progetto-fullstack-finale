import NavigateNext from '@mui/icons-material/NavigateNext';
import Breadcrumbs from '@mui/material/Breadcrumbs';
import Link from '@mui/material/Link';
import Skeleton from '@mui/material/Skeleton';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';

export function CatalogBreadcrumbs({ items }) {
  const links = [{ label: 'Home', to: paths.home }, ...items];

  return (
    <Breadcrumbs aria-label="Percorso" separator={<NavigateNext fontSize="small" />}>
      {links.map((item, index) => {
        if (item.label === null) {
          return <Skeleton key={index} variant="text" sx={{ width: '10ch' }} />;
        }

        if (index === links.length - 1) {
          return (
            <Typography
              key={index}
              variant="body2"
              aria-current="page"
              sx={{ color: 'text.primary' }}
            >
              {item.label}
            </Typography>
          );
        }

        return (
          <Link
            key={index}
            component={RouterLink}
            to={item.to}
            variant="body2"
            underline="hover"
            sx={{ color: 'text.secondary' }}
          >
            {item.label}
          </Link>
        );
      })}
    </Breadcrumbs>
  );
}
