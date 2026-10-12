import MenuOutlined from '@mui/icons-material/MenuOutlined';
import AppBar from '@mui/material/AppBar';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import { Suspense, useState } from 'react';
import { Outlet, ScrollRestoration } from 'react-router';
import { useDocumentTitle } from '@/app/router/useDocumentTitle';
import { useRouteTitle } from '@/app/router/useRouteTitle';
import { PageLoader } from '@/components/feedback/PageLoader';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { LeaveGuardProvider } from '@/components/navigation/LeaveGuardProvider';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { AdminDrawerContent } from '@/layouts/admin/AdminDrawerContent';
import { getInitials } from '@/utils/initials';

const drawerWidth = (theme) => theme.layout.adminDrawerWidth;

export function AdminLayout() {
  const { user } = useAuth();
  const isDesktop = useIsDesktop();
  const title = useRouteTitle();
  const [mobileOpen, setMobileOpen] = useState(false);
  useDocumentTitle();

  function closeDrawer() {
    setMobileOpen(false);
  }

  return (
    <LeaveGuardProvider>
      <Box
        sx={{
          display: 'flex',
          minHeight: (theme) => theme.layout.minPageHeight,
          bgcolor: 'background.default',
        }}
      >
        {isDesktop ? (
          <Drawer
            variant="permanent"
            sx={{ width: drawerWidth, flexShrink: 0 }}
            slotProps={{ paper: { sx: { width: drawerWidth, boxSizing: 'border-box' } } }}
          >
            <AdminDrawerContent />
          </Drawer>
        ) : (
          <Drawer
            variant="temporary"
            open={mobileOpen}
            onClose={closeDrawer}
            slotProps={{
              paper: {
                sx: { width: drawerWidth, maxWidth: (theme) => theme.layout.drawerMaxWidth },
              },
            }}
          >
            <AdminDrawerContent onNavigate={closeDrawer} onClose={closeDrawer} />
          </Drawer>
        )}
        <Box sx={{ flexGrow: 1, minWidth: 0, display: 'flex', flexDirection: 'column' }}>
          <AppBar>
            <Toolbar>
              {isDesktop ? null : (
                <IconButton
                  edge="start"
                  aria-label="Apri il menu"
                  onClick={() => setMobileOpen(true)}
                >
                  <MenuOutlined />
                </IconButton>
              )}
              <Typography variant="h4" component="h1" noWrap sx={{ flexGrow: 1 }}>
                {title}
              </Typography>
              <Stack direction="row" sx={{ alignItems: 'center', gap: 1.5 }}>
                {isDesktop ? (
                  <Stack sx={{ alignItems: 'flex-end' }}>
                    <Typography variant="subtitle2">{user.name}</Typography>
                    <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                      {user.email}
                    </Typography>
                  </Stack>
                ) : null}
                <Avatar
                  role={isDesktop ? undefined : 'img'}
                  aria-label={isDesktop ? undefined : user.name}
                  aria-hidden={isDesktop ? true : undefined}
                  sx={{
                    bgcolor: 'secondary.light',
                    color: 'text.primary',
                    typography: 'subtitle2',
                  }}
                >
                  {getInitials(user.name)}
                </Avatar>
              </Stack>
            </Toolbar>
          </AppBar>
          <Box
            component="main"
            sx={(theme) => ({ flexGrow: 1, p: theme.layout.adminContentPadding })}
          >
            <Suspense fallback={<PageLoader />}>
              <Outlet />
            </Suspense>
          </Box>
        </Box>
        <ScrollRestoration />
      </Box>
    </LeaveGuardProvider>
  );
}
