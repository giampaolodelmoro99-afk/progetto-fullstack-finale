import { useEffect, useRef } from 'react';

export function useRunOnce(callback) {
  const hasRun = useRef(false);

  useEffect(() => {
    if (hasRun.current) {
      return;
    }

    hasRun.current = true;
    callback();
  }, [callback]);
}
