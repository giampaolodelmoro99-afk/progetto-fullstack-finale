import { waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { queryKeys } from '@/api/queryKeys';
import { useAdminSettings } from '@/features/settings/hooks/useAdminSettings';
import { useShopSettings } from '@/features/settings/hooks/useShopSettings';
import { useSupportContent } from '@/features/settings/hooks/useSupportContent';
import { buildAdmin } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { renderHookWithProviders } from '@/test/renderWithProviders';

describe('settings hooks', () => {
  it('reads the shop settings from the api', async () => {
    server.use(
      http.get(apiUrl('/api/v1/shop/settings'), () =>
        HttpResponse.json({ data: { shipping_cost: 500, free_shipping_threshold: 12000 } }),
      ),
    );

    const { result, queryClient } = renderHookWithProviders(() => useShopSettings(), {
      user: null,
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data).toEqual({ shipping_cost: 500, free_shipping_threshold: 12000 });
    expect(queryClient.getQueryCache().find({ queryKey: queryKeys.settings.shop }).isStale()).toBe(
      false,
    );
  });

  it('reads the admin settings with the low stock threshold', async () => {
    server.use(
      http.get(apiUrl('/api/v1/admin/settings'), () =>
        HttpResponse.json({
          data: { shipping_cost: 690, free_shipping_threshold: 9900, low_stock_threshold: 3 },
        }),
      ),
    );

    const { result } = renderHookWithProviders(() => useAdminSettings(), { user: buildAdmin() });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data.low_stock_threshold).toBe(3);
  });

  it('reads the support sections in the order received', async () => {
    server.use(
      http.get(apiUrl('/api/v1/shop/support'), () =>
        HttpResponse.json({
          data: [
            { id: 'contatti', title: 'Scrivici', paragraphs: ['Rispondiamo in giornata.'] },
            { id: 'spedizioni', title: 'Consegne', paragraphs: ['In 24/48 ore.'] },
          ],
        }),
      ),
    );

    const { result } = renderHookWithProviders(() => useSupportContent(), { user: null });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data.map((section) => section.id)).toEqual(['contatti', 'spedizioni']);
  });

  it('exposes the error of a failed request', async () => {
    server.use(
      http.get(apiUrl('/api/v1/shop/settings'), () =>
        HttpResponse.json({ message: 'Errore.' }, { status: 500 }),
      ),
    );

    const { result } = renderHookWithProviders(() => useShopSettings(), { user: null });

    await waitFor(() => expect(result.current.isError).toBe(true));
    expect(result.current.error.response.status).toBe(500);
  });
});
