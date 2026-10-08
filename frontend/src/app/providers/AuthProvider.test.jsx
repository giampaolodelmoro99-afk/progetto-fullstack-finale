import { renderHook, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { queryKeys } from '@/api/queryKeys';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { fetchCart } from '@/features/cart/api/cartApi';
import { buildAdmin, buildCart, buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { renderHookWithProviders } from '@/test/renderWithProviders';

describe('AuthProvider', () => {
  it('treats a 401 on me as a guest', async () => {
    const { result } = renderHookWithProviders(() => useAuth());

    expect(result.current.isLoading).toBe(true);
    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current).toMatchObject({
      user: null,
      isAuthenticated: false,
      isAdmin: false,
      isEmailVerified: false,
    });
  });

  it('exposes the authenticated customer', async () => {
    const user = buildUser({ name: 'Giulia Ferri' });
    server.use(http.get(apiUrl('/api/v1/me'), () => HttpResponse.json({ data: user })));

    const { result } = renderHookWithProviders(() => useAuth());

    await waitFor(() => expect(result.current.isAuthenticated).toBe(true));
    expect(result.current.user.name).toBe('Giulia Ferri');
    expect(result.current.isAdmin).toBe(false);
    expect(result.current.isEmailVerified).toBe(true);
  });

  it('recognises an admin', () => {
    const { result } = renderHookWithProviders(() => useAuth(), { user: buildAdmin() });

    expect(result.current.isAdmin).toBe(true);
    expect(result.current.isLoading).toBe(false);
  });

  it('reports an email still to verify', () => {
    const { result } = renderHookWithProviders(() => useAuth(), {
      user: buildUser({ email_verified_at: null }),
    });

    expect(result.current.isEmailVerified).toBe(false);
  });

  it('logs out, forgetting the user and every cached data', async () => {
    let logoutCalls = 0;
    server.use(
      http.post(apiUrl('/api/v1/logout'), () => {
        logoutCalls += 1;
        return new HttpResponse(null, { status: 204 });
      }),
    );
    const { result, queryClient } = renderHookWithProviders(() => useAuth(), {
      user: buildUser(),
    });
    queryClient.setQueryData(queryKeys.cart, buildCart({ summary: { items_count: 2 } }));
    queryClient.setQueryData(queryKeys.orders.all, [{ id: 7, number: 'ORD-2026-000007' }]);
    queryClient.setQueryData(queryKeys.settings.admin, { low_stock_threshold: 5 });

    await result.current.logout();

    await waitFor(() => expect(result.current.isAuthenticated).toBe(false));
    expect(logoutCalls).toBe(1);
    expect(queryClient.getQueryData(queryKeys.cart)).toBeUndefined();
    expect(queryClient.getQueryData(queryKeys.orders.all)).toBeUndefined();
    expect(queryClient.getQueryData(queryKeys.settings.admin)).toBeUndefined();
  });

  it('keeps the user when the logout request fails', async () => {
    server.use(
      http.post(apiUrl('/api/v1/logout'), () =>
        HttpResponse.json({ message: 'Errore.' }, { status: 500 }),
      ),
    );
    const { result } = renderHookWithProviders(() => useAuth(), { user: buildUser() });

    await expect(result.current.logout()).rejects.toMatchObject({ response: { status: 500 } });
    expect(result.current.isAuthenticated).toBe(true);
  });

  it('becomes a guest when another request answers 401', async () => {
    server.use(
      http.get(apiUrl('/api/v1/cart'), () =>
        HttpResponse.json({ message: 'Devi accedere per continuare.' }, { status: 401 }),
      ),
    );
    const { result, queryClient } = renderHookWithProviders(() => useAuth(), {
      user: buildUser(),
    });

    await queryClient
      .fetchQuery({
        queryKey: queryKeys.cart,
        queryFn: fetchCart,
      })
      .catch(() => undefined);

    await waitFor(() => expect(result.current.isAuthenticated).toBe(false));
    expect(queryClient.getQueryData(queryKeys.cart)).toBeUndefined();
  });

  it('refuses to work outside the provider', () => {
    expect(() => renderHook(() => useAuth())).toThrow('useAuth must be used inside AuthProvider');
  });
});
