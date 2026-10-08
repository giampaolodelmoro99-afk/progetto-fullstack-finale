import { lazy } from 'react';
import { createBrowserRouter, Navigate } from 'react-router';
import { AdminRoute } from '@/app/router/AdminRoute';
import { CustomerRoute } from '@/app/router/CustomerRoute';
import { GuestRoute } from '@/app/router/GuestRoute';
import { paths } from '@/app/router/paths';
import { ProtectedRoute } from '@/app/router/ProtectedRoute';
import CancelEmailChangePage from '@/features/auth/pages/CancelEmailChangePage';
import ConfirmEmailChangePage from '@/features/auth/pages/ConfirmEmailChangePage';
import ForgotPasswordPage from '@/features/auth/pages/ForgotPasswordPage';
import LoginPage from '@/features/auth/pages/LoginPage';
import LoginTwoFactorPage from '@/features/auth/pages/LoginTwoFactorPage';
import RegisterPage from '@/features/auth/pages/RegisterPage';
import ResetPasswordPage from '@/features/auth/pages/ResetPasswordPage';
import VerifyEmailPage from '@/features/auth/pages/VerifyEmailPage';
import { AccountLayout } from '@/layouts/AccountLayout';
import { AdminLayout } from '@/layouts/AdminLayout';
import { AuthLayout } from '@/layouts/AuthLayout';
import { StoreLayout } from '@/layouts/StoreLayout';
import ErrorPage from '@/pages/ErrorPage';
import NotFoundPage from '@/pages/NotFoundPage';
import PlaceholderPage from '@/pages/PlaceholderPage';

const AdminPlaceholderPage = lazy(() => import('@/pages/PlaceholderPage'));

const storePage = <PlaceholderPage contained />;
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
            handle: { hideEmailBanner: true },
            children: [
              {
                element: <GuestRoute />,
                children: [
                  page(paths.login, 'Accedi', <LoginPage />),
                  page(paths.loginTwoFactor, 'Verifica in due passaggi', <LoginTwoFactorPage />),
                  page(paths.register, 'Crea il tuo account', <RegisterPage />),
                  page(paths.forgotPassword, 'Password dimenticata', <ForgotPasswordPage />),
                ],
              },
              page(paths.resetPassword, 'Scegli una nuova password', <ResetPasswordPage />),
              page(paths.verifyEmail, 'Conferma la tua email', <VerifyEmailPage />),
              page(
                paths.confirmEmailChange,
                'Conferma della nuova email',
                <ConfirmEmailChangePage />,
              ),
              page(paths.cancelEmailChange, 'Annulla il cambio email', <CancelEmailChangePage />),
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
