import { Outlet } from 'react-router';
import { LoginRedirect } from '@/app/router/LoginRedirect';
import { PageLoader } from '@/components/feedback/PageLoader';
import { useAuth } from '@/features/auth/hooks/useAuth';

export function ProtectedRoute() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return <PageLoader />;
  }

  if (!isAuthenticated) {
    return <LoginRedirect />;
  }

  return <Outlet />;
}
