import { useEffect } from 'react';
import { useMatches } from 'react-router';
import { useRouteTitle } from '@/app/router/useRouteTitle';

const SHOP_NAME = 'Casa del Silicio';

export function useDocumentTitle(pageTitle) {
  const routeTitle = useRouteTitle();
  const matches = useMatches();
  const isLayout = pageTitle === undefined;
  const pageSetsTitle = isLayout && matches.some((match) => match.handle?.pageTitle);
  const title = pageTitle ?? routeTitle;

  useEffect(() => {
    if (pageSetsTitle) {
      return;
    }

    document.title = title && title !== SHOP_NAME ? `${title} · ${SHOP_NAME}` : SHOP_NAME;
  }, [title, pageSetsTitle]);
}
