import { useCallback } from 'react';
import { ErrorState } from '@/components/feedback/ErrorState';
import { AuthResult } from '@/features/auth/components/AuthResult';
import { AuthResultLoading } from '@/features/auth/components/AuthResultLoading';
import { EmailConfirmed } from '@/features/auth/components/EmailConfirmed';
import { ExpiredVerificationLink } from '@/features/auth/components/ExpiredVerificationLink';
import { useRunOnce } from '@/features/auth/hooks/useRunOnce';
import { useVerifyEmail } from '@/features/auth/hooks/useVerifyEmail';

export function VerifyEmailLink({ parameters }) {
  const verification = useVerifyEmail();
  const { mutate } = verification;
  const verify = useCallback(() => mutate(parameters), [mutate, parameters]);

  useRunOnce(verify);

  if (verification.isSuccess) {
    return <EmailConfirmed email={verification.data.email} />;
  }

  if (verification.error?.status === 403) {
    return <ExpiredVerificationLink />;
  }

  if (verification.error?.status === 429) {
    return (
      <AuthResult title="Troppi tentativi">
        Hai aperto troppi link in poco tempo. Riapri il link dall'email tra qualche minuto.
      </AuthResult>
    );
  }

  if (verification.isError) {
    return <ErrorState message={verification.error.message} onRetry={verify} />;
  }

  return <AuthResultLoading />;
}
