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
import { SecondFactorSection } from '@/features/security/components/SecondFactorSection';
import { useChangePassword } from '@/features/security/hooks/useChangePassword';
import { changePasswordSchema } from '@/features/security/schemas';
import {
  PASSKEY_FAILED,
  isPasskeyCancelled,
} from '@/features/security/utils/authenticateWithPasskey';
import {
  NO_SECOND_FACTOR,
  SECOND_FACTOR_METHODS,
  recoveryCodesNotice,
} from '@/features/security/utils/secondFactor';
import { useNotify } from '@/hooks/useNotify';
import { useRateLimit } from '@/hooks/useRateLimit';
import { applyFieldErrors } from '@/utils/applyFieldErrors';

const FIELDS = ['current_password', 'password', 'password_confirmation', 'code', 'credential'];

function availableMethods(overview) {
  const methods = [SECOND_FACTOR_METHODS.totp, SECOND_FACTOR_METHODS.recoveryCode];

  return overview.passkeys_count > 0 ? [...methods, SECOND_FACTOR_METHODS.passkey] : methods;
}

export function ChangePasswordForm({ overview, onClose }) {
  const requirementsId = useId();
  const twoFactorEnabled = overview.two_factor_enabled;
  const changePassword = useChangePassword();
  const rateLimit = useRateLimit();
  const notify = useNotify();
  const [formError, setFormError] = useState(null);
  const {
    control,
    handleSubmit,
    setError,
    setValue,
    clearErrors,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(changePasswordSchema),
    defaultValues: {
      current_password: '',
      password: '',
      password_confirmation: '',
      method: twoFactorEnabled ? SECOND_FACTOR_METHODS.totp : NO_SECOND_FACTOR,
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
    changePassword.mutate(values, {
      onSuccess: (result) => {
        const notice = recoveryCodesNotice(result?.meta);

        onClose();
        notify(
          notice
            ? { message: `Password cambiata. ${notice}`, severity: 'warning' }
            : { message: 'Password cambiata' },
        );
      },
      onError: handleError,
    });
  }

  return (
    <Stack component="form" noValidate onSubmit={handleSubmit(submit)} sx={{ gap: 2 }}>
      <TooManyAttemptsAlert rateLimit={rateLimit} />
      {formError ? <Alert severity="error">{formError}</Alert> : null}
      <FormTextField
        name="current_password"
        control={control}
        component={PasswordField}
        label="Password attuale"
        autoComplete="current-password"
        autoFocus
        fullWidth
      />
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
      {twoFactorEnabled ? (
        <Stack sx={{ gap: 2 }}>
          <Divider />
          <Typography variant="subtitle1" component="h4">
            Verifica in due passaggi
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            La doppia autenticazione è attiva: inserisci anche il codice dell&apos;app.
          </Typography>
          <SecondFactorSection
            control={control}
            method={method}
            onMethodChange={changeMethod}
            methods={availableMethods(overview)}
            credentialError={errors.credential?.message}
          />
        </Stack>
      ) : null}
      <Stack direction={{ xs: 'column', sm: 'row' }} sx={{ gap: 1 }}>
        <Button
          type="submit"
          variant="contained"
          loading={changePassword.isPending}
          disabled={rateLimit.isBlocking}
        >
          Salva la nuova password
        </Button>
        <Button onClick={onClose} disabled={changePassword.isPending}>
          Annulla
        </Button>
      </Stack>
    </Stack>
  );
}
