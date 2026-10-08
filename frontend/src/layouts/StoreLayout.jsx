import Box from '@mui/material/Box';
import { Outlet } from 'react-router';
import { useDocumentTitle } from '@/app/router/useDocumentTitle';
import { StoreFooter } from '@/layouts/store/StoreFooter';
import { StoreHeader } from '@/layouts/store/StoreHeader';

export function StoreLayout() {
  useDocumentTitle();

  return (
    <Box
      sx={{
        minHeight: (theme) => theme.layout.minPageHeight,
        display: 'flex',
        flexDirection: 'column',
        bgcolor: 'background.default',
      }}
    >
      <StoreHeader />
      <Box component="main" sx={{ flexGrow: 1 }}>
        <Outlet />
      </Box>
      <StoreFooter />
    </Box>
  );
}
