import { lazy } from 'react';
import { createBrowserRouter, Navigate } from 'react-router';
import { AdminRoute } from '@/app/router/AdminRoute';
import { CustomerRoute } from '@/app/router/CustomerRoute';
import { GuestRoute } from '@/app/router/GuestRoute';
import { paths } from '@/app/router/paths';
import { ProtectedRoute } from '@/app/router/ProtectedRoute';
import { AccountLayout } from '@/layouts/AccountLayout';
import { AdminLayout } from '@/layouts/AdminLayout';
import { AuthLayout } from '@/layouts/AuthLayout';
import { StoreLayout } from '@/layouts/StoreLayout';
import ErrorPage from '@/pages/ErrorPage';
import NotFoundPage from '@/pages/NotFoundPage';
import PlaceholderPage from '@/pages/PlaceholderPage';

const AdminPlaceholderPage = lazy(() => import('@/pages/PlaceholderPage'));

const storePage = <PlaceholderPage contained />;
const authPage = <PlaceholderPage />;
const accountPage = <PlaceholderPage headingComponent="h2" />;
const adminPage = <AdminPlaceholderPage headingComponent={null} />;

function page(path, title, element) {
  return { path, element, handle: { title } };
}

export const routes = [
  {
    errorElement: <ErrorPage />,
    children: [
      {
        element: <StoreLayout />,
        children: [
          page(paths.home, 'Casa del Silicio', storePage),
          page(paths.products, 'Catalogo', storePage),
          page('/products/:slug', 'Prodotto', storePage),
          page(paths.support, 'Assistenza', storePage),
          page(paths.legal, 'Note legali', storePage),
          {
            element: <CustomerRoute adminRedirect={paths.home} />,
            children: [
              page(paths.cart, 'Il tuo carrello', storePage),
              page(paths.checkout, 'Checkout', storePage),
              page('/checkout/success/:orderId', 'Grazie per il tuo ordine', storePage),
            ],
          },
          {
            element: <AuthLayout />,
            children: [
              {
                element: <GuestRoute />,
                children: [
                  page(paths.login, 'Accedi', authPage),
                  page(paths.loginTwoFactor, 'Verifica in due passaggi', authPage),
                  page(paths.register, 'Crea il tuo account', authPage),
                  page(paths.forgotPassword, 'Password dimenticata', authPage),
                ],
              },
              page(paths.resetPassword, 'Scegli una nuova password', authPage),
              page(paths.verifyEmail, 'Conferma la tua email', authPage),
              page(paths.confirmEmailChange, 'Conferma della nuova email', authPage),
              page(paths.cancelEmailChange, 'Annulla il cambio email', authPage),
            ],
          },
          {
            element: <ProtectedRoute />,
            children: [
              {
                element: <AccountLayout />,
                children: [
                  { path: paths.account, element: <Navigate to={paths.accountProfile} replace /> },
                  page(paths.accountProfile, 'Profilo', accountPage),
                  page(paths.accountSecurity, 'Sicurezza', accountPage),
                  {
                    element: <CustomerRoute adminRedirect={paths.accountProfile} />,
                    children: [
                      page(paths.accountAddresses, 'Indirizzi', accountPage),
                      page(paths.accountOrders, 'I miei ordini', accountPage),
                      page('/account/orders/:orderId', 'Dettaglio ordine', accountPage),
                    ],
                  },
                ],
              },
            ],
          },
          { path: '*', element: <NotFoundPage /> },
        ],
      },
      {
        element: <AdminRoute />,
        children: [
          {
            element: <AdminLayout />,
            children: [
              page(paths.admin, 'Dashboard', adminPage),
              page(paths.adminProducts, 'Prodotti', adminPage),
              page(paths.adminProductNew, 'Nuovo prodotto', adminPage),
              page('/admin/products/:productId/edit', 'Modifica prodotto', adminPage),
              page(paths.adminCategories, 'Categorie', adminPage),
              page(paths.adminOrders, 'Ordini', adminPage),
              page('/admin/orders/:orderId', 'Dettaglio ordine', adminPage),
            ],
          },
        ],
      },
    ],
  },
];

export const router = createBrowserRouter(routes);
