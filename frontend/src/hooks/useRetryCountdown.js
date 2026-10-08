import { useCallback, useEffect, useState } from 'react';

const TICK_MILLISECONDS = 1000;

export function useRetryCountdown() {
  const [deadline, setDeadline] = useState(null);
  const [now, setNow] = useState(0);

  useEffect(() => {
    if (deadline === null) {
      return undefined;
    }

    const interval = setInterval(() => {
      const current = Date.now();

      setNow(current);

      if (current >= deadline) {
        setDeadline(null);
      }
    }, TICK_MILLISECONDS);

    return () => clearInterval(interval);
  }, [deadline]);

  const start = useCallback((seconds) => {
    const current = Date.now();

    setNow(current);
    setDeadline(seconds > 0 ? current + seconds * TICK_MILLISECONDS : null);
  }, []);

  const secondsLeft =
    deadline === null ? 0 : Math.max(0, Math.ceil((deadline - now) / TICK_MILLISECONDS));

  return { secondsLeft, isActive: secondsLeft > 0, start };
}
