import Link from '@mui/material/Link';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import { Link as RouterLink, useLocation, useSearchParams } from 'react-router';
import { paths } from '@/app/router/paths';
import { useCategories } from '@/features/catalog/hooks/useCategories';

const SKELETON_ITEMS = 5;

function NavigationLink({ to, active, children }) {
  return (
    <Link
      component={RouterLink}
      to={to}
      variant="body2"
      underline={active ? 'always' : 'hover'}
      aria-current={active ? 'page' : undefined}
      sx={{
        color: 'text.primary',
        display: 'inline-block',
        py: 1.5,
        textUnderlineOffset: (theme) => theme.layout.navigationUnderlineOffset,
      }}
    >
      {children}
    </Link>
  );
}

export function CategoryNavigation() {
  const categoriesQuery = useCategories();
  const { pathname } = useLocation();
  const [searchParams] = useSearchParams();
  const onCatalog = pathname === paths.products;
  const activeCategory = onCatalog ? searchParams.get('category') : null;
  const newArrivalsActive = onCatalog && searchParams.get('new') === '1' && !activeCategory;

  return (
    <Stack
      component="nav"
      aria-label="Menu principale"
      aria-busy={categoriesQuery.isPending}
      sx={{ borderTop: 1, borderColor: 'divider', px: 4 }}
    >
      <Stack
        component="ul"
        direction="row"
        sx={{
          listStyle: 'none',
          m: 0,
          p: 0,
          columnGap: 4,
          justifyContent: 'center',
          alignItems: 'center',
          flexWrap: 'wrap',
        }}
      >
        <li>
          <NavigationLink to={paths.newArrivals} active={newArrivalsActive}>
            Novità
          </NavigationLink>
        </li>
        {categoriesQuery.isPending
          ? Array.from({ length: SKELETON_ITEMS }, (_, index) => (
              <li key={index} aria-hidden="true">
                <Skeleton variant="text" sx={{ width: (theme) => theme.spacing(12) }} />
              </li>
            ))
          : null}
        {categoriesQuery.isSuccess
          ? categoriesQuery.data.map((category) => (
              <li key={category.id}>
                <NavigationLink
                  to={paths.category(category.slug)}
                  active={activeCategory === category.slug}
                >
                  {category.name}
                </NavigationLink>
              </li>
            ))
          : null}
      </Stack>
    </Stack>
  );
}
