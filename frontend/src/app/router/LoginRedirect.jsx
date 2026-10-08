import { Navigate, useLocation } from 'react-router';
import { paths } from '@/app/router/paths';

export function LoginRedirect() {
  const location = useLocation();

  return <Navigate to={paths.login} replace state={{ from: location }} />;
}
