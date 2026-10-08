import ChevronRight from '@mui/icons-material/ChevronRight';
import Close from '@mui/icons-material/Close';
import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemText from '@mui/material/ListItemText';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { Logo } from '@/components/navigation/Logo';
import { getAccountNavigation } from '@/features/account/navigation';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useLogoutToHome } from '@/features/auth/hooks/useLogoutToHome';
import { useCategories } from '@/features/catalog/hooks/useCategories';
import { DrawerSection } from '@/layouts/store/DrawerSection';

const SKELETON_ITEMS = 5;

function DrawerLink({ to, onClick, children, chevron = false }) {
  return (
    <ListItemButton component={RouterLink} to={to} onClick={onClick}>
      <ListItemText primary={children} />
      {chevron ? <ChevronRight fontSize="small" /> : null}
    </ListItemButton>
  );
}

function AccountLinks({ onClose }) {
  const { isAuthenticated, isAdmin } = useAuth();
  const logoutToHome = useLogoutToHome();

  if (!isAuthenticated) {
    return (
      <>
        <DrawerLink to={paths.login} onClick={onClose}>
          Accedi o registrati
        </DrawerLink>
        <DrawerLink to={paths.accountOrders} onClick={onClose}>
          I miei ordini
        </DrawerLink>
        <DrawerLink to={paths.support} onClick={onClose}>
          Assistenza
        </DrawerLink>
      </>
    );
  }

  function handleLogout() {
    onClose();
    logoutToHome();
  }

  return (
    <>
      {getAccountNavigation(isAdmin).map((item) => (
        <DrawerLink key={item.to} to={item.to} onClick={onClose}>
          {item.label}
        </DrawerLink>
      ))}
      <DrawerLink to={paths.support} onClick={onClose}>
        Assistenza
      </DrawerLink>
      <ListItemButton onClick={handleLogout}>
        <ListItemText primary="Esci" />
      </ListItemButton>
    </>
  );
}

export function StoreDrawer({ open, onClose }) {
  const categoriesQuery = useCategories();

  return (
    <Drawer
      anchor="left"
      open={open}
      onClose={onClose}
      slotProps={{
        paper: {
          sx: {
            width: (theme) => theme.layout.storeDrawerWidth,
            maxWidth: (theme) => theme.layout.drawerMaxWidth,
          },
        },
      }}
    >
      <Stack
        direction="row"
        sx={{
          alignItems: 'center',
          justifyContent: 'space-between',
          px: 2,
          py: 2,
          borderBottom: 1,
          borderColor: 'divider',
        }}
      >
        <Logo onClick={onClose} />
        <IconButton aria-label="Chiudi il menu" onClick={onClose}>
          <Close />
        </IconButton>
      </Stack>
      <Box component="nav" aria-label="Menu del negozio" sx={{ px: 1 }}>
        <DrawerSection title="Categorie">
          {categoriesQuery.isPending
            ? Array.from({ length: SKELETON_ITEMS }, (_, index) => (
                <Box key={index} aria-hidden="true" sx={{ px: 2, py: 1 }}>
                  <Skeleton variant="text" />
                </Box>
              ))
            : null}
          {categoriesQuery.isSuccess
            ? categoriesQuery.data.map((category) => (
                <DrawerLink
                  key={category.id}
                  to={paths.category(category.slug)}
                  onClick={onClose}
                  chevron
                >
                  {category.name}
                </DrawerLink>
              ))
            : null}
          <DrawerLink to={paths.newArrivals} onClick={onClose}>
            Novità
          </DrawerLink>
        </DrawerSection>
        <Divider sx={{ mx: 1 }} />
        <DrawerSection title="Il tuo account">
          <AccountLinks onClose={onClose} />
        </DrawerSection>
      </Box>
    </Drawer>
  );
}
