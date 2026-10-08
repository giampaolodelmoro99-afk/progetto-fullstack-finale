import { useCallback, useState } from 'react';
import { useRetryCountdown } from '@/hooks/useRetryCountdown';

export function useRateLimit() {
  const { secondsLeft, isActive, start } = useRetryCountdown();
  const [limit, setLimit] = useState(null);

  const trigger = useCallback(
    (error) => {
      const retryAfter = error?.retryAfter ?? null;

      setLimit({ retryAfter });
      start(retryAfter ?? 0);
    },
    [start],
  );

  const clear = useCallback(() => setLimit(null), []);

  const isLimited = limit !== null && (limit.retryAfter === null || isActive);

  return {
    isLimited,
    isBlocking: isActive,
    retryAfter: limit?.retryAfter ?? null,
    secondsLeft,
    trigger,
    clear,
  };
}
