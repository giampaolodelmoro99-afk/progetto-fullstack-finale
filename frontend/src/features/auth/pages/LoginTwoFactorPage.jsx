import { Navigate, useLocation } from 'react-router';
import { paths } from '@/app/router/paths';
import { TwoFactorForm } from '@/features/auth/components/TwoFactorForm';

export default function LoginTwoFactorPage() {
  const location = useLocation();
  const methods = location.state?.methods;
  const from = location.state?.from;

  if (!Array.isArray(methods)) {
    return <Navigate to={paths.login} replace state={from ? { from } : undefined} />;
  }

  return <TwoFactorForm methods={methods} from={from} />;
}
