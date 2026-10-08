import { http, HttpResponse } from 'msw';
import { beforeEach, describe, expect, it } from 'vitest';
import { httpClient, setActiveQueryClient } from '@/api/httpClient';
import { queryKeys } from '@/api/queryKeys';
import { endUserSession } from '@/api/session';
import { buildCategories, buildShopSettings, buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { createTestQueryClient } from '@/test/renderWithProviders';

function fillCache(client, user) {
  client.setQueryData(queryKeys.me, user);
  client.setQueryData(queryKeys.categories, buildCategories());
  client.setQueryData(queryKeys.settings.shop, buildShopSettings());
  client.setQueryData(queryKeys.orders.all, [{ id: 7, number: 'ORD-2026-000007' }]);
  client.setQueryData(queryKeys.security.overview, { two_factor_enabled: true });
}

function answerUnauthenticated(path) {
  server.use(
    http.get(apiUrl(path), () =>
      HttpResponse.json({ message: 'Devi accedere per continuare.' }, { status: 401 }),
    ),
  );
}

describe('endUserSession', () => {
  let client;

  beforeEach(() => {
    client = createTestQueryClient();
    setActiveQueryClient(client);
  });

  it('empties the whole cache when an authenticated user loses the session', () => {
    fillCache(client, buildUser());
    client.getMutationCache().build(client, { mutationKey: ['profile'] });

    endUserSession(client);

    expect(client.getQueryData(queryKeys.me)).toBeNull();
    expect(
      client
        .getQueryCache()
        .getAll()
        .map((query) => query.queryKey),
    ).toEqual([queryKeys.me]);
    expect(client.getMutationCache().getAll()).toHaveLength(0);
  });

  it('keeps the cache of a guest', () => {
    fillCache(client, null);

    endUserSession(client);

    expect(client.getQueryData(queryKeys.me)).toBeNull();
    expect(client.getQueryData(queryKeys.categories)).toHaveLength(5);
    expect(client.getQueryData(queryKeys.settings.shop)).toEqual({
      shipping_cost: 690,
      free_shipping_threshold: 9900,
    });
  });

  it('empties the cache when a request of an authenticated user answers 401', async () => {
    fillCache(client, buildUser());
    answerUnauthenticated('/api/v1/orders');

    await expect(httpClient.get('/api/v1/orders')).rejects.toMatchObject({
      response: { status: 401 },
    });

    expect(client.getQueryData(queryKeys.me)).toBeNull();
    expect(client.getQueryData(queryKeys.orders.all)).toBeUndefined();
    expect(client.getQueryData(queryKeys.security.overview)).toBeUndefined();
    expect(client.getQueryData(queryKeys.categories)).toBeUndefined();
  });

  it('keeps categories and settings when a guest receives a 401', async () => {
    fillCache(client, null);
    answerUnauthenticated('/api/v1/cart');

    await expect(httpClient.get('/api/v1/cart')).rejects.toMatchObject({
      response: { status: 401 },
    });

    expect(client.getQueryData(queryKeys.me)).toBeNull();
    expect(client.getQueryData(queryKeys.categories)).toHaveLength(5);
    expect(client.getQueryData(queryKeys.settings.shop)).toEqual({
      shipping_cost: 690,
      free_shipping_threshold: 9900,
    });
  });

  it('keeps the cache while the user is still loading', async () => {
    client.setQueryData(queryKeys.categories, buildCategories());
    answerUnauthenticated('/api/v1/me');

    await expect(httpClient.get('/api/v1/me')).rejects.toMatchObject({
      response: { status: 401 },
    });

    expect(client.getQueryData(queryKeys.me)).toBeNull();
    expect(client.getQueryData(queryKeys.categories)).toHaveLength(5);
  });
});
