import { Outlet } from 'react-router';
import { LoginRedirect } from '@/app/router/LoginRedirect';
import { UserLoadError } from '@/app/router/UserLoadError';
import { PageLoader } from '@/components/feedback/PageLoader';
import { useAuth } from '@/features/auth/hooks/useAuth';

export function ProtectedRoute() {
  const { isAuthenticated, isLoading, isError } = useAuth();

  if (isLoading) {
    return <PageLoader />;
  }

  if (!isAuthenticated && isError) {
    return <UserLoadError />;
  }

  if (!isAuthenticated) {
    return <LoginRedirect />;
  }

  return <Outlet />;
}
