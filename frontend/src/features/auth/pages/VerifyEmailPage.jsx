import { useMemo } from 'react';
import { useSearchParams } from 'react-router';
import { ExpiredVerificationLink } from '@/features/auth/components/ExpiredVerificationLink';
import { VerifyEmailLink } from '@/features/auth/components/VerifyEmailLink';
import { VerifyEmailPrompt } from '@/features/auth/components/VerifyEmailPrompt';

const LINK_PARAMETERS = ['id', 'hash', 'expires', 'signature'];

export default function VerifyEmailPage() {
  const [searchParams] = useSearchParams();
  const query = searchParams.toString();

  const parameters = useMemo(() => {
    const values = new URLSearchParams(query);

    if (!LINK_PARAMETERS.some((name) => values.has(name))) {
      return null;
    }

    return Object.fromEntries(LINK_PARAMETERS.map((name) => [name, values.get(name) ?? '']));
  }, [query]);

  if (parameters === null) {
    return <VerifyEmailPrompt />;
  }

  if (Object.values(parameters).some((value) => value === '')) {
    return <ExpiredVerificationLink />;
  }

  return <VerifyEmailLink parameters={parameters} />;
}
