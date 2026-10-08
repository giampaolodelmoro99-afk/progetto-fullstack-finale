import { waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { useCategories } from '@/features/catalog/hooks/useCategories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { renderHookWithProviders } from '@/test/renderWithProviders';

describe('useCategories', () => {
  it('returns the categories of the response', async () => {
    server.use(
      http.get(apiUrl('/api/v1/categories'), () =>
        HttpResponse.json({
          data: [{ id: 9, name: 'Viaggio', slug: 'viaggio', description: null }],
        }),
      ),
    );

    const { result } = renderHookWithProviders(() => useCategories(), { user: null });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data).toEqual([
      { id: 9, name: 'Viaggio', slug: 'viaggio', description: null },
    ]);
  });
});
