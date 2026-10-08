import Logout from '@mui/icons-material/Logout';
import PersonOutlineOutlined from '@mui/icons-material/PersonOutlineOutlined';
import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import IconButton from '@mui/material/IconButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { getAccountNavigation } from '@/features/account/navigation';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useLogoutToHome } from '@/features/auth/hooks/useLogoutToHome';

const MENU_ID = 'account-menu';

export function AccountMenu() {
  const { user, isAuthenticated, isAdmin } = useAuth();
  const logoutToHome = useLogoutToHome();
  const [anchorElement, setAnchorElement] = useState(null);
  const open = anchorElement !== null;

  if (!isAuthenticated) {
    return (
      <IconButton component={RouterLink} to={paths.login} aria-label="Accedi">
        <PersonOutlineOutlined />
      </IconButton>
    );
  }

  function closeMenu() {
    setAnchorElement(null);
  }

  function handleLogout() {
    closeMenu();
    logoutToHome();
  }

  return (
    <>
      <IconButton
        aria-label="Il tuo account"
        aria-haspopup="true"
        aria-expanded={open}
        aria-controls={open ? MENU_ID : undefined}
        onClick={(event) => setAnchorElement(event.currentTarget)}
      >
        <PersonOutlineOutlined />
      </IconButton>
      <Menu
        id={MENU_ID}
        anchorEl={anchorElement}
        open={open}
        onClose={closeMenu}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        transformOrigin={{ vertical: 'top', horizontal: 'right' }}
      >
        <Box sx={{ px: 2, py: 1.5 }}>
          <Typography variant="subtitle2">{user.name}</Typography>
          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
            {user.email}
          </Typography>
        </Box>
        <Divider />
        {getAccountNavigation(isAdmin).map((item) => (
          <MenuItem key={item.to} component={RouterLink} to={item.to} onClick={closeMenu}>
            <ListItemIcon>
              <item.icon fontSize="small" />
            </ListItemIcon>
            <ListItemText>{item.label}</ListItemText>
          </MenuItem>
        ))}
        <Divider />
        <MenuItem onClick={handleLogout}>
          <ListItemIcon>
            <Logout fontSize="small" />
          </ListItemIcon>
          <ListItemText>Esci</ListItemText>
        </MenuItem>
      </Menu>
    </>
  );
}
