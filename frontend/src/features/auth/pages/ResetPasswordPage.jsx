import { useCallback } from 'react';
import { useSearchParams } from 'react-router';
import { ErrorState } from '@/components/feedback/ErrorState';
import { AuthResultLoading } from '@/features/auth/components/AuthResultLoading';
import { ExpiredResetLink } from '@/features/auth/components/ExpiredResetLink';
import { ResetPasswordForm } from '@/features/auth/components/ResetPasswordForm';
import { useCheckPasswordResetToken } from '@/features/auth/hooks/useCheckPasswordResetToken';
import { useRunOnce } from '@/features/auth/hooks/useRunOnce';

const INVALID_RESET_TOKEN = 'invalid_reset_token';

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const email = searchParams.get('email') ?? '';
  const hasParameters = token !== '' && email !== '';
  const check = useCheckPasswordResetToken();
  const { mutate } = check;

  const runCheck = useCallback(() => {
    if (hasParameters) {
      mutate({ token, email });
    }
  }, [hasParameters, mutate, token, email]);

  useRunOnce(runCheck);

  if (!hasParameters || check.error?.code === INVALID_RESET_TOKEN) {
    return <ExpiredResetLink />;
  }

  if (check.isError) {
    return <ErrorState message={check.error.message} onRetry={runCheck} />;
  }

  if (!check.isSuccess) {
    return <AuthResultLoading />;
  }

  return (
    <ResetPasswordForm
      token={token}
      email={email}
      requiresSecondFactor={check.data.requires_second_factor}
      methods={check.data.methods ?? []}
    />
  );
}
