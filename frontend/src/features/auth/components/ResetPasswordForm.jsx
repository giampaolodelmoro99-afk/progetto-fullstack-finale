import { zodResolver } from '@hookform/resolvers/zod';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useId, useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { TooManyAttemptsAlert } from '@/components/feedback/TooManyAttemptsAlert';
import { FormTextField } from '@/components/form/FormTextField';
import { PasswordField } from '@/components/form/PasswordField';
import { PasswordRequirements } from '@/components/form/PasswordRequirements';
import { AuthHeader } from '@/features/auth/components/AuthHeader';
import { ExpiredResetLink } from '@/features/auth/components/ExpiredResetLink';
import { useResetPassword } from '@/features/auth/hooks/useResetPassword';
import { resetPasswordSchema } from '@/features/auth/schemas';
import { SecondFactorSection } from '@/features/security/components/SecondFactorSection';
import {
  PASSKEY_FAILED,
  isPasskeyCancelled,
} from '@/features/security/utils/authenticateWithPasskey';
import { NO_SECOND_FACTOR, SECOND_FACTOR_METHODS } from '@/features/security/utils/secondFactor';
import { useRateLimit } from '@/hooks/useRateLimit';
import { applyFieldErrors } from '@/utils/applyFieldErrors';

const INVALID_RESET_TOKEN = 'invalid_reset_token';
const FIELDS = ['password', 'password_confirmation', 'code', 'credential'];

export function ResetPasswordForm({ token, email, requiresSecondFactor, methods }) {
  const requirementsId = useId();
  const reset = useResetPassword();
  const rateLimit = useRateLimit();
  const [formError, setFormError] = useState(null);
  const [linkExpired, setLinkExpired] = useState(false);
  const {
    control,
    handleSubmit,
    setError,
    setValue,
    clearErrors,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: {
      password: '',
      password_confirmation: '',
      method: requiresSecondFactor ? SECOND_FACTOR_METHODS.totp : NO_SECOND_FACTOR,
      code: '',
    },
  });
  const [password, method] = useWatch({ control, name: ['password', 'method'] });

  function changeMethod(value) {
    setValue('method', value);
    setValue('code', '');
    clearErrors(['code', 'credential']);
  }

  function handleError(error) {
    if (isPasskeyCancelled(error)) {
      return;
    }

    if (error.code === INVALID_RESET_TOKEN) {
      setLinkExpired(true);
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

    if (!applyFieldErrors(setError, error.fieldErrors, FIELDS)) {
      setFormError(error.message);
    }
  }

  function submit(values) {
    setFormError(null);
    rateLimit.clear();
    reset.mutate({ token, email, values }, { onError: handleError });
  }

  if (linkExpired) {
    return <ExpiredResetLink />;
  }

  return (
    <Stack sx={{ gap: 3 }}>
      <AuthHeader title="Scegli una nuova password">
        Per l'account{' '}
        <Typography component="strong" variant="subtitle2" sx={{ color: 'text.primary' }}>
          {email}
        </Typography>
        . Scegline una che non usi su altri siti.
      </AuthHeader>
      <TooManyAttemptsAlert rateLimit={rateLimit} />
      {formError ? <Alert severity="error">{formError}</Alert> : null}
      <Stack component="form" noValidate onSubmit={handleSubmit(submit)} sx={{ gap: 2 }}>
        <FormTextField
          name="password"
          control={control}
          component={PasswordField}
          label="Nuova password"
          autoComplete="new-password"
          describedBy={requirementsId}
          fullWidth
        />
        <PasswordRequirements id={requirementsId} value={password} />
        <FormTextField
          name="password_confirmation"
          control={control}
          component={PasswordField}
          label="Conferma la nuova password"
          autoComplete="new-password"
          fullWidth
        />
        {requiresSecondFactor ? (
          <Stack sx={{ gap: 2 }}>
            <Divider />
            <Typography variant="subtitle1" component="h2">
              Verifica in due passaggi
            </Typography>
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
              Sul tuo account è attiva la doppia autenticazione: inserisci il codice dell'app per
              confermare che sei tu.
            </Typography>
            <SecondFactorSection
              control={control}
              method={method}
              onMethodChange={changeMethod}
              methods={methods}
              credentialError={errors.credential?.message}
            />
          </Stack>
        ) : null}
        <Button
          type="submit"
          variant="contained"
          fullWidth
          loading={reset.isPending}
          disabled={rateLimit.isBlocking}
        >
          Salva la nuova password
        </Button>
      </Stack>
    </Stack>
  );
}
