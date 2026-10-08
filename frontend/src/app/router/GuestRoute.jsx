import { Navigate, Outlet, useLocation } from 'react-router';
import { paths } from '@/app/router/paths';
import { PageLoader } from '@/components/feedback/PageLoader';
import { useAuth } from '@/features/auth/hooks/useAuth';

export function GuestRoute() {
  const { isAuthenticated, isLoading } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return <PageLoader />;
  }

  if (isAuthenticated) {
    return <Navigate to={location.state?.from ?? paths.home} replace />;
  }

  return <Outlet />;
}
