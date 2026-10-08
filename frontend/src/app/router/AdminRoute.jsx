import { Navigate, Outlet } from 'react-router';
import { LoginRedirect } from '@/app/router/LoginRedirect';
import { paths } from '@/app/router/paths';
import { PageLoader } from '@/components/feedback/PageLoader';
import { useAuth } from '@/features/auth/hooks/useAuth';

export function AdminRoute() {
  const { isAuthenticated, isAdmin, isLoading } = useAuth();

  if (isLoading) {
    return <PageLoader />;
  }

  if (!isAuthenticated) {
    return <LoginRedirect />;
  }

  if (!isAdmin) {
    return <Navigate to={paths.home} replace />;
  }

  return <Outlet />;
}
