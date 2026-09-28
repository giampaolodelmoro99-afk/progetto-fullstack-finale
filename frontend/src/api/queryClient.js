import { QueryClient } from '@tanstack/react-query';

const MAX_RETRIES = 2;

export function shouldRetry(failureCount, error) {
  const status = error?.response?.status;

  if (status >= 400 && status < 500) {
    return false;
  }

  return failureCount < MAX_RETRIES;
}

export function createQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        retry: shouldRetry,
        refetchOnWindowFocus: false,
      },
      mutations: {
        retry: false,
      },
    },
  });
}

export const queryClient = createQueryClient();
