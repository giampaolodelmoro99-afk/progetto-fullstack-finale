import { zodResolver } from '@hookform/resolvers/zod';
import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import Button from '@mui/material/Button';
import Divider from '@mui/material/Divider';
import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link as RouterLink, useLocation, useNavigate } from 'react-router';
import { paths } from '@/app/router/paths';
import { TooManyAttemptsAlert } from '@/components/feedback/TooManyAttemptsAlert';
import { FormTextField } from '@/components/form/FormTextField';
import { PasswordField } from '@/components/form/PasswordField';
import { AuthHeader } from '@/features/auth/components/AuthHeader';
import { useLogin } from '@/features/auth/hooks/useLogin';
import { usePasskeyLogin } from '@/features/auth/hooks/usePasskeyLogin';
import { loginSchema } from '@/features/auth/schemas';
import { usePasskeySupport } from '@/features/security/hooks/usePasskeySupport';
import { isPasskeyCancelled } from '@/features/security/utils/authenticateWithPasskey';
import { useRateLimit } from '@/hooks/useRateLimit';
import { applyFieldErrors } from '@/utils/applyFieldErrors';

const INVALID_CREDENTIALS = 'invalid_credentials';

export default function LoginPage() {
  const location = useLocation();
  const navigate = useNavigate();
  const passkeySupported = usePasskeySupport();
  const login = useLogin();
  const passkeyLogin = usePasskeyLogin();
  const rateLimit = useRateLimit();
  const [failure, setFailure] = useState(null);
  const from = location.state?.from;
  const [notice] = useState(() => location.state?.notice ?? null);
  const { control, handleSubmit, setError } = useForm({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  });

  useEffect(() => {
    if (location.state?.notice) {
      navigate('.', { replace: true, state: from ? { from } : null });
    }
  }, [location.state, from, navigate]);

  function handleError(error) {
    if (isPasskeyCancelled(error)) {
      return;
    }

    if (error.status === 429) {
      rateLimit.trigger(error);
      return;
    }

    if (error.code === INVALID_CREDENTIALS) {
      setFailure({ kind: INVALID_CREDENTIALS });
      return;
    }

    if (!applyFieldErrors(setError, error.fieldErrors, ['email', 'password'])) {
      setFailure({ kind: 'other', message: error.message });
    }
  }

  function resetFeedback() {
    setFailure(null);
    rateLimit.clear();
  }

  function submit(values) {
    resetFeedback();
    login.mutate(values, {
      onSuccess: (result) => {
        if (result.two_factor_required) {
          navigate(paths.loginTwoFactor, { state: { methods: result.methods, from } });
        }
      },
      onError: handleError,
    });
  }

  function signInWithPasskey() {
    resetFeedback();
    passkeyLogin.mutate(undefined, { onError: handleError });
  }

  const busy = login.isPending || passkeyLogin.isPending;

  return (
    <Stack sx={{ gap: 3 }}>
      <AuthHeader title="Accedi">
        Accedi per seguire i tuoi ordini e gestire il tuo account.
      </AuthHeader>
      {notice ? <Alert severity={notice.severity}>{notice.message}</Alert> : null}
      <TooManyAttemptsAlert rateLimit={rateLimit}>
        Nel frattempo puoi scegliere una nuova password o accedere con una passkey.
      </TooManyAttemptsAlert>
      {failure?.kind === INVALID_CREDENTIALS ? (
        <Alert severity="error">
          <AlertTitle>Credenziali non valide</AlertTitle>
          Controlla l'email e la password e riprova.
        </Alert>
      ) : null}
      {failure?.kind === 'other' ? <Alert severity="error">{failure.message}</Alert> : null}
      <Stack component="form" noValidate onSubmit={handleSubmit(submit)} sx={{ gap: 2 }}>
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
          autoComplete="current-password"
          fullWidth
        />
        <Link
          component={RouterLink}
          to={paths.forgotPassword}
          variant="body2"
          sx={{ alignSelf: 'flex-end' }}
        >
          Password dimenticata?
        </Link>
        <Button
          type="submit"
          variant="contained"
          fullWidth
          loading={login.isPending}
          disabled={busy || rateLimit.isBlocking}
        >
          Accedi
        </Button>
      </Stack>
      {passkeySupported ? (
        <>
          <Divider>
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
              oppure
            </Typography>
          </Divider>
          <Button
            variant="outlined"
            fullWidth
            onClick={signInWithPasskey}
            loading={passkeyLogin.isPending}
            disabled={busy}
          >
            Accedi con una passkey
          </Button>
        </>
      ) : null}
      <Typography variant="body2" sx={{ textAlign: 'center' }}>
        Non hai un account?{' '}
        <Link component={RouterLink} to={paths.register}>
          Registrati
        </Link>
      </Typography>
    </Stack>
  );
}
