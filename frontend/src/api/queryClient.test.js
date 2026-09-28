import { describe, expect, it } from 'vitest';
import { createQueryClient, shouldRetry } from '@/api/queryClient';

function httpError(status) {
  return { response: { status } };
}

describe('shouldRetry', () => {
  it.each([400, 401, 403, 404, 419, 422, 423, 429])('does not retry a %i response', (status) => {
    expect(shouldRetry(0, httpError(status))).toBe(false);
  });

  it('retries server errors up to two times', () => {
    expect(shouldRetry(0, httpError(500))).toBe(true);
    expect(shouldRetry(1, httpError(503))).toBe(true);
    expect(shouldRetry(2, httpError(500))).toBe(false);
  });

  it('retries network errors', () => {
    expect(shouldRetry(0, new Error('Network Error'))).toBe(true);
  });
});

describe('createQueryClient', () => {
  it('applies the shared query defaults', () => {
    const defaults = createQueryClient().getDefaultOptions();

    expect(defaults.queries.retry).toBe(shouldRetry);
    expect(defaults.queries.staleTime).toBe(30_000);
    expect(defaults.mutations.retry).toBe(false);
  });
});
