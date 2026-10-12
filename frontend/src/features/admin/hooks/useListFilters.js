import { useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router';

export function useListFilters(parse, serialize) {
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = useMemo(() => parse(searchParams), [parse, searchParams]);

  const update = useCallback(
    (changes) => setSearchParams(serialize({ ...filters, page: 1, ...changes })),
    [filters, serialize, setSearchParams],
  );

  const reset = useCallback(() => setSearchParams(new URLSearchParams()), [setSearchParams]);

  return { filters, update, reset };
}
