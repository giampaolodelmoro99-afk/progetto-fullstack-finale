import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useRateLimit } from '@/hooks/useRateLimit';
import { useRetryCountdown } from '@/hooks/useRetryCountdown';

describe('useRetryCountdown', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('counts down from the deadline and stops at zero', () => {
    const { result } = renderHook(() => useRetryCountdown());

    act(() => result.current.start(3));
    expect(result.current).toMatchObject({ secondsLeft: 3, isActive: true });

    act(() => vi.advanceTimersByTime(1000));
    expect(result.current.secondsLeft).toBe(2);

    act(() => vi.advanceTimersByTime(2000));
    expect(result.current).toMatchObject({ secondsLeft: 0, isActive: false });
  });

  it('computes the seconds left from the clock, not from the ticks', () => {
    const { result } = renderHook(() => useRetryCountdown());

    act(() => result.current.start(45));
    act(() => {
      vi.setSystemTime(Date.now() + 30_000);
      vi.advanceTimersByTime(1000);
    });

    expect(result.current.secondsLeft).toBe(14);
  });
});

describe('useRateLimit', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('blocks until the retry after expires', () => {
    const { result } = renderHook(() => useRateLimit());

    act(() => result.current.trigger({ status: 429, retryAfter: 2 }));
    expect(result.current).toMatchObject({ isLimited: true, isBlocking: true, retryAfter: 2 });

    act(() => vi.advanceTimersByTime(2000));
    expect(result.current).toMatchObject({ isLimited: false, isBlocking: false });
  });

  it('shows the limit without blocking when the retry after is unknown', () => {
    const { result } = renderHook(() => useRateLimit());

    act(() => result.current.trigger({ status: 429, retryAfter: null }));
    expect(result.current).toMatchObject({ isLimited: true, isBlocking: false, retryAfter: null });

    act(() => result.current.clear());
    expect(result.current.isLimited).toBe(false);
  });
});
