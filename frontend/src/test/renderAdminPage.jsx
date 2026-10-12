import { AdminLayout } from '@/layouts/AdminLayout';
import { buildAdmin } from '@/test/factories';
import { renderWithProviders } from '@/test/renderWithProviders';

export const OTHER_ADMIN_PAGE = 'Altra pagina admin';

export const OUTSIDE_ADMIN_PAGE = "Pagina fuori dall'area admin";

export function renderAdminPage(page, { path, route = path, title = 'Pagina admin' }) {
  const routes = [
    {
      element: <AdminLayout />,
      children: [
        { path, element: page, handle: { title } },
        { path: '/admin/*', element: <p>{OTHER_ADMIN_PAGE}</p>, handle: { title: 'Altra' } },
      ],
    },
    { path: '*', element: <p>{OUTSIDE_ADMIN_PAGE}</p> },
  ];

  return renderWithProviders(null, { routes, route, user: buildAdmin() });
}
