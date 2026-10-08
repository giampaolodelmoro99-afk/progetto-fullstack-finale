import Close from '@mui/icons-material/Close';
import Logout from '@mui/icons-material/Logout';
import StorefrontOutlined from '@mui/icons-material/StorefrontOutlined';
import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import IconButton from '@mui/material/IconButton';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink, useLocation } from 'react-router';
import { paths } from '@/app/router/paths';
import { Logo } from '@/components/navigation/Logo';
import { adminNavigationItems, isAdminItemActive } from '@/features/admin/navigation';
import { useLogoutToHome } from '@/features/auth/hooks/useLogoutToHome';

export function AdminDrawerContent({ onNavigate, onClose }) {
  const { pathname } = useLocation();
  const logoutToHome = useLogoutToHome();

  return (
    <Stack sx={{ height: 1 }}>
      <Stack
        direction="row"
        sx={{ alignItems: 'flex-start', justifyContent: 'space-between', px: 2, py: 2.5 }}
      >
        <Stack>
          <Logo
            to={paths.admin}
            label="Casa del Silicio, vai alla dashboard"
            onClick={onNavigate}
          />
          <Typography variant="overline" sx={{ color: 'text.secondary' }}>
            Amministrazione
          </Typography>
        </Stack>
        {onClose ? (
          <IconButton aria-label="Chiudi il menu" onClick={onClose}>
            <Close />
          </IconButton>
        ) : null}
      </Stack>
      <Divider />
      <Box component="nav" aria-label="Amministrazione" sx={{ flexGrow: 1, p: 1 }}>
        <List disablePadding>
          {adminNavigationItems.map((item) => {
            const active = isAdminItemActive(item, pathname);

            return (
              <ListItemButton
                key={item.to}
                component={RouterLink}
                to={item.to}
                selected={active}
                aria-current={active ? 'page' : undefined}
                onClick={onNavigate}
              >
                <ListItemIcon>
                  <item.icon fontSize="small" />
                </ListItemIcon>
                <ListItemText primary={item.label} />
              </ListItemButton>
            );
          })}
        </List>
      </Box>
      <Divider />
      <List sx={{ p: 1 }}>
        <ListItemButton component={RouterLink} to={paths.home} onClick={onNavigate}>
          <ListItemIcon>
            <StorefrontOutlined fontSize="small" />
          </ListItemIcon>
          <ListItemText primary="Vai al negozio" />
        </ListItemButton>
        <ListItemButton onClick={logoutToHome}>
          <ListItemIcon>
            <Logout fontSize="small" />
          </ListItemIcon>
          <ListItemText primary="Esci" />
        </ListItemButton>
      </List>
    </Stack>
  );
}
