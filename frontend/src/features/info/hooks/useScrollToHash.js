import { useEffect, useRef } from 'react';
import { useLocation } from 'react-router';

export function useScrollToHash(ready) {
  const { hash } = useLocation();
  const wasReady = useRef(ready);

  useEffect(() => {
    const becameReady = ready && !wasReady.current;

    wasReady.current = ready;

    if (!becameReady || hash === '') {
      return;
    }

    let id;

    try {
      id = decodeURIComponent(hash.slice(1));
    } catch {
      return;
    }

    document.getElementById(id)?.scrollIntoView({ block: 'start' });
  }, [hash, ready]);
}
