import MenuOutlined from '@mui/icons-material/MenuOutlined';
import AppBar from '@mui/material/AppBar';
import Box from '@mui/material/Box';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import Toolbar from '@mui/material/Toolbar';
import { useState } from 'react';
import { useLocation, useSearchParams } from 'react-router';
import { paths } from '@/app/router/paths';
import { Logo } from '@/components/navigation/Logo';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { AccountMenu } from '@/layouts/store/AccountMenu';
import { CartButton } from '@/layouts/store/CartButton';
import { CategoryNavigation } from '@/layouts/store/CategoryNavigation';
import { HeaderSearch } from '@/layouts/store/HeaderSearch';
import { StoreDrawer } from '@/layouts/store/StoreDrawer';

export function StoreHeader() {
  const isDesktop = useIsDesktop();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const { pathname } = useLocation();
  const [searchParams] = useSearchParams();
  const currentSearch = pathname === paths.products ? (searchParams.get('search') ?? '') : '';
  const search = (
    <HeaderSearch
      key={`${pathname}?${currentSearch}`}
      initialValue={currentSearch}
      sx={
        isDesktop
          ? { flexGrow: 1, maxWidth: (theme) => theme.spacing(46) }
          : { px: 2, pb: 2, width: 1 }
      }
    />
  );

  return (
    <>
      <AppBar component="header">
        <Toolbar>
          {isDesktop ? null : (
            <IconButton edge="start" aria-label="Apri il menu" onClick={() => setDrawerOpen(true)}>
              <MenuOutlined />
            </IconButton>
          )}
          <Box sx={{ flex: isDesktop ? 1 : 'none' }}>
            <Logo />
          </Box>
          {isDesktop ? search : null}
          <Stack
            direction="row"
            sx={{ flex: 1, justifyContent: 'flex-end', alignItems: 'center', gap: 1 }}
          >
            <AccountMenu />
            <CartButton />
          </Stack>
        </Toolbar>
        {isDesktop ? <CategoryNavigation /> : search}
      </AppBar>
      {isDesktop ? null : <StoreDrawer open={drawerOpen} onClose={() => setDrawerOpen(false)} />}
    </>
  );
}
