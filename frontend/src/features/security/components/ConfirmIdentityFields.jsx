import { zodResolver } from '@hookform/resolvers/zod';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { TooManyAttemptsAlert } from '@/components/feedback/TooManyAttemptsAlert';
import { FormTextField } from '@/components/form/FormTextField';
import { PasswordField } from '@/components/form/PasswordField';
import { IdentitySecondFactorChoice } from '@/features/security/components/IdentitySecondFactorChoice';
import { useConfirmIdentity } from '@/features/security/hooks/useConfirmIdentity';
import { usePasskeySupport } from '@/features/security/hooks/usePasskeySupport';
import { confirmIdentitySchema } from '@/features/security/schemas';
import {
  PASSKEY_FAILED,
  isPasskeyCancelled,
} from '@/features/security/utils/authenticateWithPasskey';
import { NO_SECOND_FACTOR, SECOND_FACTOR_METHODS } from '@/features/security/utils/secondFactor';
import { useRateLimit } from '@/hooks/useRateLimit';
import { applyFieldErrors } from '@/utils/applyFieldErrors';

const SERVER_FIELDS = ['password', 'code', 'credential'];

export function ConfirmIdentityFields({ overview, prompt, onConfirmed, onCancel }) {
  const passkeySupported = usePasskeySupport();
  const twoFactorEnabled = overview.two_factor_enabled;
  const [formError, setFormError] = useState(null);
  const rateLimit = useRateLimit();
  const confirmation = useConfirmIdentity();
  const {
    control,
    handleSubmit,
    setError,
    setValue,
    clearErrors,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(confirmIdentitySchema),
    defaultValues: {
      password: '',
      method: twoFactorEnabled ? SECOND_FACTOR_METHODS.totp : NO_SECOND_FACTOR,
      code: '',
    },
  });
  const method = useWatch({ control, name: 'method' });

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

    if (!applyFieldErrors(setError, error.fieldErrors, SERVER_FIELDS)) {
      setFormError(error.message);
    }
  }

  function submit(values) {
    setFormError(null);
    rateLimit.clear();
    confirmation.mutate(values, { onSuccess: () => onConfirmed(), onError: handleError });
  }

  return (
    <form noValidate onSubmit={handleSubmit(submit)}>
      <DialogContent sx={{ pt: 0 }}>
        <Stack sx={{ gap: 2 }}>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            {prompt}
          </Typography>
          <TooManyAttemptsAlert rateLimit={rateLimit} />
          {formError ? <Alert severity="error">{formError}</Alert> : null}
          <FormTextField
            name="password"
            control={control}
            component={PasswordField}
            label="Password"
            autoComplete="current-password"
            autoFocus
            fullWidth
          />
          {twoFactorEnabled ? (
            <IdentitySecondFactorChoice
              control={control}
              method={method}
              onMethodChange={changeMethod}
              recoveryCodesRemaining={overview.recovery_codes_remaining}
              passkeyAllowed={passkeySupported && overview.passkeys_count > 0}
              credentialError={errors.credential?.message}
            />
          ) : null}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 3 }}>
        <Button onClick={onCancel} disabled={confirmation.isPending}>
          Annulla
        </Button>
        <Button
          type="submit"
          variant="contained"
          loading={confirmation.isPending}
          disabled={rateLimit.isBlocking}
        >
          Conferma
        </Button>
      </DialogActions>
    </form>
  );
}
