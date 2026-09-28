import { QueryClient } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { queryKeys } from '@/api/queryKeys';
import { AppProviders } from '@/app/providers/AppProviders';

function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        gcTime: Infinity,
      },
      mutations: {
        retry: false,
      },
    },
  });
}

export function renderWithProviders(ui, { route = '/', user = null } = {}) {
  const queryClient = createTestQueryClient();

  if (user) {
    queryClient.setQueryData(queryKeys.me, user);
  }

  const router = createMemoryRouter([{ path: '*', element: ui }], {
    initialEntries: [route],
  });

  const utils = render(
    <AppProviders client={queryClient}>
      <RouterProvider router={router} />
    </AppProviders>,
  );

  return { ...utils, queryClient, router };
}
