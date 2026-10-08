import { zodResolver } from '@hookform/resolvers/zod';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { Link as RouterLink, useNavigate } from 'react-router';
import { paths } from '@/app/router/paths';
import { TooManyAttemptsAlert } from '@/components/feedback/TooManyAttemptsAlert';
import { AuthHeader } from '@/features/auth/components/AuthHeader';
import { useTwoFactorLogin } from '@/features/auth/hooks/useTwoFactorLogin';
import { twoFactorSchema } from '@/features/auth/schemas';
import { SecondFactorSection } from '@/features/security/components/SecondFactorSection';
import {
  PASSKEY_FAILED,
  isPasskeyCancelled,
} from '@/features/security/utils/authenticateWithPasskey';
import { SECOND_FACTOR_METHODS } from '@/features/security/utils/secondFactor';
import { useRateLimit } from '@/hooks/useRateLimit';
import { applyFieldErrors } from '@/utils/applyFieldErrors';

const CHALLENGE_EXPIRED = 'two_factor_challenge_expired';

const DESCRIPTIONS = {
  [SECOND_FACTOR_METHODS.totp]: "Apri l'app di autenticazione e inserisci il codice a 6 cifre.",
  [SECOND_FACTOR_METHODS.recoveryCode]:
    'Inserisci uno dei codici di recupero che hai salvato quando hai attivato la doppia autenticazione.',
  [SECOND_FACTOR_METHODS.passkey]: 'Usa la passkey registrata su questo dispositivo.',
};

export function TwoFactorForm({ methods, from }) {
  const navigate = useNavigate();
  const twoFactorLogin = useTwoFactorLogin();
  const rateLimit = useRateLimit();
  const [formError, setFormError] = useState(null);
  const {
    control,
    handleSubmit,
    setError,
    setValue,
    clearErrors,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(twoFactorSchema),
    defaultValues: { method: SECOND_FACTOR_METHODS.totp, code: '' },
  });
  const method = useWatch({ control, name: 'method' });

  function changeMethod(value) {
    setValue('method', value);
    setValue('code', '');
    clearErrors(['code', 'credential']);
    setFormError(null);
  }

  function handleError(error) {
    if (isPasskeyCancelled(error)) {
      return;
    }

    if (error.code === CHALLENGE_EXPIRED) {
      navigate(paths.login, {
        replace: true,
        state: { from, notice: { severity: 'warning', message: error.message } },
      });
      return;
    }

    if (error.status === 429) {
      rateLimit.trigger(error);
      return;
    }

    if (error.code === PASSKEY_FAILED) {
      setError('credential', { type: 'server', message: error.message });
      return;
    }

    if (!applyFieldErrors(setError, error.fieldErrors, ['code', 'credential'])) {
      setFormError(error.message);
    }
  }

  function submit(values) {
    setFormError(null);
    rateLimit.clear();
    twoFactorLogin.mutate(values, { onError: handleError });
  }

  return (
    <Stack sx={{ gap: 3 }}>
      <AuthHeader title="Verifica in due passaggi">{DESCRIPTIONS[method]}</AuthHeader>
      <TooManyAttemptsAlert rateLimit={rateLimit} />
      {formError ? <Alert severity="error">{formError}</Alert> : null}
      <Stack component="form" noValidate onSubmit={handleSubmit(submit)} sx={{ gap: 2 }}>
        <SecondFactorSection
          control={control}
          method={method}
          onMethodChange={changeMethod}
          methods={methods}
          credentialError={errors.credential?.message}
        />
        <Button
          type="submit"
          variant="contained"
          fullWidth
          loading={twoFactorLogin.isPending}
          disabled={rateLimit.isBlocking}
        >
          {method === SECOND_FACTOR_METHODS.passkey ? 'Usa la passkey' : 'Verifica'}
        </Button>
      </Stack>
      <Link
        component={RouterLink}
        to={paths.login}
        state={from ? { from } : undefined}
        variant="body2"
        sx={{ alignSelf: 'center' }}
      >
        Torna all'accesso
      </Link>
    </Stack>
  );
}
