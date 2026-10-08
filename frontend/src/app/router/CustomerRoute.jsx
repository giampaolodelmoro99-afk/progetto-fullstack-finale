import { Navigate, Outlet } from 'react-router';
import { LoginRedirect } from '@/app/router/LoginRedirect';
import { UserLoadError } from '@/app/router/UserLoadError';
import { PageLoader } from '@/components/feedback/PageLoader';
import { useAuth } from '@/features/auth/hooks/useAuth';

export function CustomerRoute({ adminRedirect }) {
  const { isAuthenticated, isAdmin, isLoading, isError } = useAuth();

  if (isLoading) {
    return <PageLoader />;
  }

  if (!isAuthenticated && isError) {
    return <UserLoadError />;
  }

  if (!isAuthenticated) {
    return <LoginRedirect />;
  }

  if (isAdmin) {
    return <Navigate to={adminRedirect} replace />;
  }

  return <Outlet />;
}
