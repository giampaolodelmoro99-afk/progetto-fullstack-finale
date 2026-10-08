import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import { Outlet } from 'react-router';

export function AuthLayout() {
  return (
    <Box sx={{ display: 'flex', justifyContent: 'center', px: 2, py: { xs: 4, md: 8 } }}>
      <Card sx={{ width: 1, maxWidth: (theme) => theme.layout.authCardMaxWidth }}>
        <CardContent sx={{ p: { xs: 3, md: 5 } }}>
          <Outlet />
        </CardContent>
      </Card>
    </Box>
  );
}
