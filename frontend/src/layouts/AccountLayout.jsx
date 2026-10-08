import Logout from '@mui/icons-material/Logout';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Divider from '@mui/material/Divider';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Stack from '@mui/material/Stack';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
import Typography from '@mui/material/Typography';
import { Link as RouterLink, Outlet, useLocation } from 'react-router';
import { getAccountNavigation, isAccountItemActive } from '@/features/account/navigation';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useLogoutToHome } from '@/features/auth/hooks/useLogoutToHome';
import { useIsDesktop } from '@/hooks/useIsDesktop';

function SideNavigation({ items, pathname }) {
  const logoutToHome = useLogoutToHome();

  return (
    <Box
      component="nav"
      aria-label="Area account"
      sx={{ width: (theme) => theme.layout.accountNavigationWidth, flexShrink: 0 }}
    >
      <List disablePadding>
        {items.map((item) => {
          const active = isAccountItemActive(item, pathname);

          return (
            <ListItemButton
              key={item.to}
              component={RouterLink}
              to={item.to}
              selected={active}
              aria-current={active ? 'page' : undefined}
            >
              <ListItemIcon>
                <item.icon fontSize="small" />
              </ListItemIcon>
              <ListItemText primary={item.label} />
            </ListItemButton>
          );
        })}
      </List>
      <Divider sx={{ my: 1 }} />
      <List disablePadding>
        <ListItemButton onClick={logoutToHome}>
          <ListItemIcon>
            <Logout fontSize="small" />
          </ListItemIcon>
          <ListItemText primary="Esci" />
        </ListItemButton>
      </List>
    </Box>
  );
}

function TabNavigation({ items, pathname }) {
  const activeItem = items.find((item) => isAccountItemActive(item, pathname));

  return (
    <Tabs
      value={activeItem?.to ?? false}
      variant="scrollable"
      scrollButtons="auto"
      aria-label="Area account"
      sx={{ borderBottom: 1, borderColor: 'divider' }}
    >
      {items.map((item) => (
        <Tab key={item.to} value={item.to} label={item.label} component={RouterLink} to={item.to} />
      ))}
    </Tabs>
  );
}

export function AccountLayout() {
  const { user, isAdmin } = useAuth();
  const isDesktop = useIsDesktop();
  const { pathname } = useLocation();
  const items = getAccountNavigation(isAdmin);

  return (
    <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
      <Stack sx={{ gap: 0.5, mb: { xs: 2, md: 5 } }}>
        <Typography variant="h2" component="h1">
          Il tuo account
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          {user.name} · {user.email}
        </Typography>
      </Stack>
      {isDesktop ? (
        <Stack direction="row" sx={{ gap: 6, alignItems: 'flex-start' }}>
          <SideNavigation items={items} pathname={pathname} />
          <Box sx={{ flexGrow: 1, minWidth: 0 }}>
            <Outlet />
          </Box>
        </Stack>
      ) : (
        <>
          <TabNavigation items={items} pathname={pathname} />
          <Box sx={{ mt: 3 }}>
            <Outlet />
          </Box>
        </>
      )}
    </Container>
  );
}
