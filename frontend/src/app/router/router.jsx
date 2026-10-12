import { lazy } from 'react';
import { createBrowserRouter, Navigate } from 'react-router';
import { AdminRoute } from '@/app/router/AdminRoute';
import { CustomerRoute } from '@/app/router/CustomerRoute';
import { GuestRoute } from '@/app/router/GuestRoute';
import { paths } from '@/app/router/paths';
import { ProtectedRoute } from '@/app/router/ProtectedRoute';
import AddressesPage from '@/features/account/pages/AddressesPage';
import OrderPage from '@/features/account/pages/OrderPage';
import OrdersPage from '@/features/account/pages/OrdersPage';
import ProfilePage from '@/features/account/pages/ProfilePage';
import CancelEmailChangePage from '@/features/auth/pages/CancelEmailChangePage';
import ConfirmEmailChangePage from '@/features/auth/pages/ConfirmEmailChangePage';
import ForgotPasswordPage from '@/features/auth/pages/ForgotPasswordPage';
import LoginPage from '@/features/auth/pages/LoginPage';
import LoginTwoFactorPage from '@/features/auth/pages/LoginTwoFactorPage';
import RegisterPage from '@/features/auth/pages/RegisterPage';
import ResetPasswordPage from '@/features/auth/pages/ResetPasswordPage';
import VerifyEmailPage from '@/features/auth/pages/VerifyEmailPage';
import CartPage from '@/features/cart/pages/CartPage';
import CatalogPage from '@/features/catalog/pages/CatalogPage';
import ProductPage from '@/features/catalog/pages/ProductPage';
import CheckoutPage from '@/features/checkout/pages/CheckoutPage';
import CheckoutSuccessPage from '@/features/checkout/pages/CheckoutSuccessPage';
import HomePage from '@/features/home/pages/HomePage';
import LegalPage from '@/features/info/pages/LegalPage';
import SupportPage from '@/features/info/pages/SupportPage';
import SecurityPage from '@/features/security/pages/SecurityPage';
import { AccountLayout } from '@/layouts/AccountLayout';
import { AdminLayout } from '@/layouts/AdminLayout';
import { AuthLayout } from '@/layouts/AuthLayout';
import { StoreLayout } from '@/layouts/StoreLayout';
import ErrorPage from '@/pages/ErrorPage';
import NotFoundPage from '@/pages/NotFoundPage';

const DashboardPage = lazy(() => import('@/features/admin/dashboard/pages/DashboardPage'));
const AdminProductsPage = lazy(() => import('@/features/admin/products/pages/ProductsPage'));
const AdminProductNewPage = lazy(() => import('@/features/admin/products/pages/ProductNewPage'));
const AdminProductEditPage = lazy(() => import('@/features/admin/products/pages/ProductEditPage'));
const AdminCategoriesPage = lazy(() => import('@/features/admin/categories/pages/CategoriesPage'));
const AdminOrdersPage = lazy(() => import('@/features/admin/orders/pages/OrdersPage'));
const AdminOrderPage = lazy(() => import('@/features/admin/orders/pages/OrderPage'));

function page(path, title, element, handle = {}) {
  return { path, element, handle: { title, ...handle } };
}

export const routes = [
  {
    errorElement: <ErrorPage />,
    children: [
      {
        element: <StoreLayout />,
        children: [
          page(paths.home, 'Casa del Silicio', <HomePage />),
          page(paths.products, 'Catalogo', <CatalogPage />),
          page('/products/:slug', 'Prodotto', <ProductPage />, { pageTitle: true }),
          page(paths.support, 'Assistenza', <SupportPage />),
          page(paths.legal, 'Note legali', <LegalPage />),
          {
            element: <CustomerRoute adminRedirect={paths.home} />,
            children: [
              page(paths.cart, 'Il tuo carrello', <CartPage />),
              page(paths.checkout, 'Checkout', <CheckoutPage />),
              page(
                '/checkout/success/:orderId',
                'Grazie per il tuo ordine',
                <CheckoutSuccessPage />,
              ),
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
                  page(paths.accountProfile, 'Profilo', <ProfilePage />),
                  page(paths.accountSecurity, 'Sicurezza', <SecurityPage />),
                  {
                    element: <CustomerRoute adminRedirect={paths.accountProfile} />,
                    children: [
                      page(paths.accountAddresses, 'Indirizzi', <AddressesPage />),
                      page(paths.accountOrders, 'I miei ordini', <OrdersPage />),
                      page('/account/orders/:orderId', 'Dettaglio ordine', <OrderPage />),
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
              page(paths.admin, 'Dashboard', <DashboardPage />),
              page(paths.adminProducts, 'Prodotti', <AdminProductsPage />),
              page(paths.adminProductNew, 'Nuovo prodotto', <AdminProductNewPage />),
              page(
                '/admin/products/:productId/edit',
                'Modifica prodotto',
                <AdminProductEditPage />,
              ),
              page(paths.adminCategories, 'Categorie', <AdminCategoriesPage />),
              page(paths.adminOrders, 'Ordini', <AdminOrdersPage />),
              page('/admin/orders/:orderId', 'Dettaglio ordine', <AdminOrderPage />),
            ],
          },
        ],
      },
    ],
  },
];

export const router = createBrowserRouter(routes);
