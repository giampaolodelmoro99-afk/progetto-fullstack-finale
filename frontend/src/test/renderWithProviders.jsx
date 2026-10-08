import { QueryClient } from '@tanstack/react-query';
import { render, renderHook } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { queryKeys } from '@/api/queryKeys';
import { AppProviders } from '@/app/providers/AppProviders';

export function createTestQueryClient() {
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

function prepareClient(user) {
  const queryClient = createTestQueryClient();

  if (user !== undefined) {
    queryClient.setQueryData(queryKeys.me, user);
  }

  return queryClient;
}

export function renderWithProviders(ui, { route = '/', user, routes } = {}) {
  const queryClient = prepareClient(user);
  const router = createMemoryRouter(routes ?? [{ path: '*', element: ui }], {
    initialEntries: [route],
  });

  const utils = render(
    <AppProviders client={queryClient}>
      <RouterProvider router={router} />
    </AppProviders>,
  );

  return { ...utils, queryClient, router };
}

export function renderHookWithProviders(hook, { user } = {}) {
  const queryClient = prepareClient(user);

  const utils = renderHook(hook, {
    wrapper: ({ children }) => <AppProviders client={queryClient}>{children}</AppProviders>,
  });

  return { ...utils, queryClient };
}
