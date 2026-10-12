import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useDebouncedValue } from '@/hooks/useDebouncedValue';

describe('useDebouncedValue', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('returns the new value only after the pause', () => {
    const { result, rerender } = renderHook(({ value }) => useDebouncedValue(value, 300), {
      initialProps: { value: 'cuf' },
    });

    rerender({ value: 'cuffie' });
    act(() => vi.advanceTimersByTime(299));
    expect(result.current).toBe('cuf');

    act(() => vi.advanceTimersByTime(1));
    expect(result.current).toBe('cuffie');
  });

  it('restarts the wait at every change', () => {
    const { result, rerender } = renderHook(({ value }) => useDebouncedValue(value, 300), {
      initialProps: { value: '' },
    });

    rerender({ value: 'a' });
    act(() => vi.advanceTimersByTime(200));
    rerender({ value: 'ar' });
    act(() => vi.advanceTimersByTime(200));
    expect(result.current).toBe('');

    act(() => vi.advanceTimersByTime(100));
    expect(result.current).toBe('ar');
  });
});
