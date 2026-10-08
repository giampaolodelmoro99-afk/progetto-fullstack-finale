import CssBaseline from '@mui/material/CssBaseline';
import { ThemeProvider } from '@mui/material/styles';
import { QueryClientProvider } from '@tanstack/react-query';
import { useState } from 'react';
import { setActiveQueryClient } from '@/api/httpClient';
import { queryClient } from '@/api/queryClient';
import { AuthProvider } from '@/app/providers/AuthProvider';
import { IdentityConfirmationProvider } from '@/app/providers/IdentityConfirmationProvider';
import { NotificationProvider } from '@/app/providers/NotificationProvider';
import { theme } from '@/theme';

export function AppProviders({ children, client = queryClient }) {
  useState(() => setActiveQueryClient(client));

  return (
    <ThemeProvider theme={theme}>
      <CssBaseline />
      <QueryClientProvider client={client}>
        <AuthProvider>
          <NotificationProvider>
            <IdentityConfirmationProvider>{children}</IdentityConfirmationProvider>
          </NotificationProvider>
        </AuthProvider>
      </QueryClientProvider>
    </ThemeProvider>
  );
}
