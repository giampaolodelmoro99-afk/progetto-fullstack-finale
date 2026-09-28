import { createBrowserRouter } from 'react-router';
import { paths } from '@/app/router/paths';
import SystemStatusPage from '@/features/system/pages/SystemStatusPage';

export const routes = [
  {
    path: paths.home,
    element: <SystemStatusPage />,
  },
];

export const router = createBrowserRouter(routes);
