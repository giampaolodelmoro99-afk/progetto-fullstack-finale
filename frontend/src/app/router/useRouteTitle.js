import { useMatches } from 'react-router';

export function useRouteTitle() {
  const matches = useMatches();

  return matches.findLast((match) => match.handle?.title)?.handle.title ?? '';
}
