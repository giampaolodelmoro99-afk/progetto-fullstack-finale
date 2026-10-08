import { zodResolver } from '@hookform/resolvers/zod';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { TooManyAttemptsAlert } from '@/components/feedback/TooManyAttemptsAlert';
import { FormTextField } from '@/components/form/FormTextField';
import { AuthHeader } from '@/features/auth/components/AuthHeader';
import { AuthResult } from '@/features/auth/components/AuthResult';
import { useRequestPasswordReset } from '@/features/auth/hooks/useRequestPasswordReset';
import { forgotPasswordSchema } from '@/features/auth/schemas';
import { useRateLimit } from '@/hooks/useRateLimit';
import { applyFieldErrors } from '@/utils/applyFieldErrors';

export default function ForgotPasswordPage() {
  const passwordReset = useRequestPasswordReset();
  const rateLimit = useRateLimit();
  const [formError, setFormError] = useState(null);
  const { control, handleSubmit, setError } = useForm({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: { email: '' },
  });

  function handleError(error) {
    if (error.status === 429) {
      rateLimit.trigger(error);
      return;
    }

    if (!applyFieldErrors(setError, error.fieldErrors, ['email'])) {
      setFormError(error.message);
    }
  }

  function submit(values) {
    setFormError(null);
    rateLimit.clear();
    passwordReset.mutate(values, { onError: handleError });
  }

  if (passwordReset.isSuccess) {
    return (
      <AuthResult
        title="Controlla la tua email"
        actions={
          <Button variant="outlined" component={RouterLink} to={paths.login} fullWidth>
            Torna all'accesso
          </Button>
        }
      >
        <Stack sx={{ gap: 1 }}>
          <span>
            Se l'indirizzo è registrato, riceverai un'email con il link per scegliere una nuova
            password.
          </span>
          <span>
            Il link resta valido per 60 minuti. Non la trovi? Controlla anche nella cartella spam.
          </span>
        </Stack>
      </AuthResult>
    );
  }

  return (
    <Stack sx={{ gap: 3 }}>
      <AuthHeader title="Password dimenticata">
        Inserisci l'email del tuo account: ti invieremo un link per sceglierne una nuova.
      </AuthHeader>
      <TooManyAttemptsAlert rateLimit={rateLimit} />
      {formError ? <Alert severity="error">{formError}</Alert> : null}
      <Stack component="form" noValidate onSubmit={handleSubmit(submit)} sx={{ gap: 2 }}>
        <FormTextField
          name="email"
          control={control}
          label="Email"
          type="email"
          autoComplete="email"
          fullWidth
        />
        <Button
          type="submit"
          variant="contained"
          fullWidth
          loading={passwordReset.isPending}
          disabled={rateLimit.isBlocking}
        >
          Invia il link
        </Button>
      </Stack>
      <Link component={RouterLink} to={paths.login} variant="body2" sx={{ alignSelf: 'center' }}>
        Torna all'accesso
      </Link>
    </Stack>
  );
}
