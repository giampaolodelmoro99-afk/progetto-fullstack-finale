import { http, HttpResponse } from 'msw';
import { afterEach, describe, expect, it } from 'vitest';
import { httpClient } from '@/api/httpClient';
import { queryClient } from '@/api/queryClient';
import { queryKeys } from '@/api/queryKeys';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';

function countingCsrfHandler(counter) {
  return http.get(apiUrl('/sanctum/csrf-cookie'), () => {
    counter.calls += 1;
    return new HttpResponse(null, { status: 204 });
  });
}

describe('httpClient', () => {
  afterEach(() => {
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
  });

  it('sends json accept header and the xsrf token from the cookie', async () => {
    document.cookie = 'XSRF-TOKEN=test-token; path=/';
    let receivedHeaders;
    server.use(
      http.post(apiUrl('/api/v1/echo'), ({ request }) => {
        receivedHeaders = request.headers;
        return HttpResponse.json({ ok: true });
      }),
    );

    await httpClient.post('/api/v1/echo', {});

    expect(receivedHeaders.get('accept')).toContain('application/json');
    expect(receivedHeaders.get('x-xsrf-token')).toBe('test-token');
  });

  it('refreshes the csrf cookie and retries once after a 419', async () => {
    const csrf = { calls: 0 };
    let attempts = 0;
    server.use(
      countingCsrfHandler(csrf),
      http.post(apiUrl('/api/v1/echo'), () => {
        attempts += 1;
        if (attempts === 1) {
          return HttpResponse.json({ message: 'CSRF token mismatch.' }, { status: 419 });
        }
        return HttpResponse.json({ ok: true });
      }),
    );

    const response = await httpClient.post('/api/v1/echo', {});

    expect(response.data).toEqual({ ok: true });
    expect(csrf.calls).toBe(1);
    expect(attempts).toBe(2);
  });

  it('gives up after a second 419', async () => {
    const csrf = { calls: 0 };
    let attempts = 0;
    server.use(
      countingCsrfHandler(csrf),
      http.post(apiUrl('/api/v1/echo'), () => {
        attempts += 1;
        return HttpResponse.json({ message: 'CSRF token mismatch.' }, { status: 419 });
      }),
    );

    await expect(httpClient.post('/api/v1/echo', {})).rejects.toMatchObject({
      response: { status: 419 },
    });
    expect(csrf.calls).toBe(1);
    expect(attempts).toBe(2);
  });

  it('removes the current user from the cache after a 401', async () => {
    queryClient.setQueryData(queryKeys.me, { id: 1, name: 'Mario Rossi' });
    server.use(
      http.get(apiUrl('/api/v1/me'), () =>
        HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 }),
      ),
    );

    await expect(httpClient.get('/api/v1/me')).rejects.toMatchObject({
      response: { status: 401 },
    });
    expect(queryClient.getQueryData(queryKeys.me)).toBeUndefined();
  });

  it('passes other errors through unchanged', async () => {
    queryClient.setQueryData(queryKeys.me, { id: 1, name: 'Mario Rossi' });
    server.use(
      http.get(apiUrl('/api/v1/echo'), () =>
        HttpResponse.json({ message: 'Errore.' }, { status: 500 }),
      ),
    );

    await expect(httpClient.get('/api/v1/echo')).rejects.toMatchObject({
      response: { status: 500 },
    });
    expect(queryClient.getQueryData(queryKeys.me)).toEqual({ id: 1, name: 'Mario Rossi' });
  });
});
