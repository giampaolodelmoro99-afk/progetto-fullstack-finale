import { zodResolver } from '@hookform/resolvers/zod';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useId, useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { TooManyAttemptsAlert } from '@/components/feedback/TooManyAttemptsAlert';
import { FormTextField } from '@/components/form/FormTextField';
import { PasswordField } from '@/components/form/PasswordField';
import { PasswordRequirements } from '@/components/form/PasswordRequirements';
import { AuthHeader } from '@/features/auth/components/AuthHeader';
import { useRegister } from '@/features/auth/hooks/useRegister';
import { registerSchema } from '@/features/auth/schemas';
import { legalSections } from '@/features/info/sections';
import { useRateLimit } from '@/hooks/useRateLimit';
import { applyFieldErrors } from '@/utils/applyFieldErrors';

const FIELDS = ['name', 'email', 'password', 'password_confirmation'];

export default function RegisterPage() {
  const requirementsId = useId();
  const registration = useRegister();
  const rateLimit = useRateLimit();
  const [formError, setFormError] = useState(null);
  const { control, handleSubmit, setError } = useForm({
    resolver: zodResolver(registerSchema),
    defaultValues: { name: '', email: '', password: '', password_confirmation: '' },
  });
  const password = useWatch({ control, name: 'password' });

  function handleError(error) {
    if (error.status === 429) {
      rateLimit.trigger(error);
      return;
    }

    if (!applyFieldErrors(setError, error.fieldErrors, FIELDS)) {
      setFormError(error.message);
    }
  }

  function submit(values) {
    setFormError(null);
    rateLimit.clear();
    registration.mutate(values, { onError: handleError });
  }

  return (
    <Stack sx={{ gap: 3 }}>
      <AuthHeader title="Crea il tuo account">
        Con un account segui i tuoi ordini e salvi i tuoi indirizzi.
      </AuthHeader>
      <TooManyAttemptsAlert rateLimit={rateLimit} />
      {formError ? <Alert severity="error">{formError}</Alert> : null}
      <Stack component="form" noValidate onSubmit={handleSubmit(submit)} sx={{ gap: 2 }}>
        <FormTextField
          name="name"
          control={control}
          label="Nome e cognome"
          autoComplete="name"
          fullWidth
        />
        <FormTextField
          name="email"
          control={control}
          label="Email"
          type="email"
          autoComplete="email"
          fullWidth
        />
        <FormTextField
          name="password"
          control={control}
          component={PasswordField}
          label="Password"
          autoComplete="new-password"
          describedBy={requirementsId}
          fullWidth
        />
        <PasswordRequirements id={requirementsId} value={password} />
        <FormTextField
          name="password_confirmation"
          control={control}
          component={PasswordField}
          label="Conferma password"
          autoComplete="new-password"
          fullWidth
        />
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Creando l'account confermi di aver letto l'
          <Link component={RouterLink} to={paths.legalSection(legalSections.privacy)}>
            informativa sulla privacy
          </Link>
          .
        </Typography>
        <Button
          type="submit"
          variant="contained"
          fullWidth
          loading={registration.isPending}
          disabled={rateLimit.isBlocking}
        >
          Crea account
        </Button>
      </Stack>
      <Typography variant="body2" sx={{ textAlign: 'center' }}>
        Hai già un account?{' '}
        <Link component={RouterLink} to={paths.login}>
          Accedi
        </Link>
      </Typography>
    </Stack>
  );
}
