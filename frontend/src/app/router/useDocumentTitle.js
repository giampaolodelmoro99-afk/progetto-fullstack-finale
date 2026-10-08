import { useEffect } from 'react';
import { useRouteTitle } from '@/app/router/useRouteTitle';

const SHOP_NAME = 'Casa del Silicio';

export function useDocumentTitle() {
  const title = useRouteTitle();

  useEffect(() => {
    document.title = title && title !== SHOP_NAME ? `${title} · ${SHOP_NAME}` : SHOP_NAME;
  }, [title]);
}
